<?php

namespace App\Services;

use App\Domain\Messaging\TemplateRenderer;
use App\Enums\DestinationScope;
use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Enums\TemplateAppliesTo;
use App\Enums\TemplateKind;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppProviderKey;
use App\Infrastructure\WhatsApp\WhatsAppProviderRegistry;
use App\Jobs\SendWhatsAppMessage;
use App\Models\GameMatch;
use App\Models\Schedule;
use App\Models\WhatsAppDestination;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageAttachment;
use App\Models\WhatsAppTemplate;
use App\Support\CatalanDate;
use App\Support\ClubSettings;
use App\Support\Time;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Avisos de WhatsApp d'una jornada confirmada. Res no s'envia sense una acció explícita del gestor:
 *  1. preview(): crea o actualitza els esborranys (un per partit i grup d'equip, i un resum per canal del club);
 *  2. el gestor revisa i, si cal, edita el text;
 *  3. send(): els esborranys seleccionats passen a la cua (una sola vegada: transició atòmica d'estat);
 *  4. el treball SendWhatsAppMessage els envia i en desa el resultat.
 * Els destins amb el proveïdor manual es publiquen copiant el text i es marquen com a enviats.
 */
class NotificationService
{
    /** Estats d'un missatge que ja ha arribat (o que probablement ha arribat) al destí. */
    private const DELIVERED = [WhatsAppMessageStatus::Sent, WhatsAppMessageStatus::ManualSent];

    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly WhatsAppProviderRegistry $providers,
        private readonly ImageService $images,
        private readonly ClubSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, WhatsAppMessage>
     */
    public function preview(Schedule $schedule): Collection
    {
        $schedule->loadMissing('matchday');
        $this->images->ensureConfirmed($schedule);

        DB::transaction(function () use ($schedule): void {
            $matches = GameMatch::query()->with(['team', 'fieldArea'])
                ->where('matchday_id', $schedule->matchday_id)
                ->whereIn('status', [MatchStatus::Proposed->value, MatchStatus::Confirmed->value])
                ->orderBy('id')->get();

            // Missatges de partit per als destins de cada equip.
            foreach ($matches as $match) {
                $template = $this->templateFor($match->home_away);
                if ($template === null) {
                    continue;
                }

                $body = $this->renderer->render($template->body, $this->matchVariables($match, $schedule), TemplateRenderer::MATCH_VARIABLES);
                $destinations = WhatsAppDestination::query()->where('scope', DestinationScope::Team->value)->where('team_id', $match->team_id)->where('is_active', true)->orderBy('id')->get();

                foreach ($destinations as $destination) {
                    $this->upsertDraft($schedule, $match, $destination, $template, $body);
                }
            }

            // Resum de la jornada per als canals (o llistes) del club.
            $summary = WhatsAppTemplate::query()->where('kind', TemplateKind::WeekendSummary->value)->where('is_active', true)->orderBy('id')->first();
            if ($summary !== null) {
                $body = $this->renderer->render($summary->body, $this->summaryVariables($schedule, $matches, $summary), TemplateRenderer::SUMMARY_VARIABLES);
                $destinations = WhatsAppDestination::query()->where('scope', DestinationScope::Club->value)->where('is_active', true)->orderBy('id')->get();

                foreach ($destinations as $destination) {
                    $message = $this->upsertDraft($schedule, null, $destination, $summary, $body);
                    if ($message !== null && $destination->include_images) {
                        $this->attachImages($message, $schedule);
                    }
                }
            }
        });

        $this->audit->log('whatsapp.previewed', $schedule);

        return $this->messages($schedule);
    }

    /**
     * @return Collection<int, WhatsAppMessage>
     */
    public function messages(Schedule $schedule): Collection
    {
        return WhatsAppMessage::query()
            ->with(['destination.team', 'match.team', 'attachments.generatedImage'])
            ->where('schedule_id', $schedule->id)
            ->orderByRaw('match_id IS NOT NULL')
            ->orderBy('match_id')
            ->orderBy('whatsapp_destination_id')
            ->orderByDesc('id')
            ->get();
    }

    public function updateDraft(WhatsAppMessage $message, string $body): WhatsAppMessage
    {
        return DB::transaction(function () use ($message, $body): WhatsAppMessage {
            $message = WhatsAppMessage::query()->whereKey($message->id)->lockForUpdate()->firstOrFail();

            if ($message->status !== WhatsAppMessageStatus::Draft) {
                throw ValidationException::withMessages(['body' => __('whatsapp.only_drafts')]);
            }

            $key = $this->idempotencyKey($message->schedule_id, $message->match_id, $message->whatsapp_destination_id, $body);
            if (WhatsAppMessage::query()->where('idempotency_key', $key)->whereKeyNot($message->id)->exists()) {
                throw ValidationException::withMessages(['body' => __('whatsapp.duplicate')]);
            }

            $message->forceFill(['body' => $body, 'body_hash' => hash('sha256', $body), 'idempotency_key' => $key])->save();
            $this->audit->log('whatsapp.edited', $message);

            return $message;
        });
    }

    /**
     * Posa a la cua els esborranys seleccionats (automàtics). Cada missatge només es pot posar a la cua un cop.
     *
     * @param  list<int>  $ids
     * @return array{queued: list<int>, skipped: list<int>}
     */
    public function send(Schedule $schedule, array $ids): array
    {
        $this->images->ensureConfirmed($schedule);
        $queued = [];

        foreach (WhatsAppMessage::query()->with('destination')->where('schedule_id', $schedule->id)->whereIn('id', $ids)->orderBy('id')->get() as $message) {
            if ($message->provider === WhatsAppProviderKey::Manual || ! $this->providers->isAvailable($message->provider)) {
                continue;
            }

            // Transició atòmica: un doble clic o dues pestanyes no poden enviar dues vegades el mateix missatge.
            $updated = WhatsAppMessage::query()->whereKey($message->id)->where('status', WhatsAppMessageStatus::Draft->value)
                ->update(['status' => WhatsAppMessageStatus::Queued->value, 'queued_at' => now(), 'sent_by' => Auth::id()]);

            if ($updated === 1) {
                $queued[] = $message->id;
                SendWhatsAppMessage::dispatch($message->id);
            }
        }

        $this->audit->log('whatsapp.send_requested', $schedule, null, ['queued' => $queued]);

        return ['queued' => $queued, 'skipped' => array_values(array_diff($ids, $queued))];
    }

    /**
     * Torna a posar a la cua només els missatges que han fallat. Els d'estat desconegut no es reintenten
     * automàticament (podrien haver arribat).
     *
     * @return list<int>
     */
    public function retryFailed(Schedule $schedule): array
    {
        $this->images->ensureConfirmed($schedule);
        $queued = [];

        foreach (WhatsAppMessage::query()->where('schedule_id', $schedule->id)->where('status', WhatsAppMessageStatus::Failed->value)->orderBy('id')->pluck('id') as $id) {
            $updated = WhatsAppMessage::query()->whereKey($id)->where('status', WhatsAppMessageStatus::Failed->value)
                ->update(['status' => WhatsAppMessageStatus::Queued->value, 'queued_at' => now(), 'sent_by' => Auth::id()]);

            if ($updated === 1) {
                $queued[] = $id;
                SendWhatsAppMessage::dispatch($id);
            }
        }

        $this->audit->log('whatsapp.retry_requested', $schedule, null, ['queued' => $queued]);

        return $queued;
    }

    /**
     * El gestor confirma que ha publicat el missatge (proveïdor manual) o que un missatge d'estat desconegut ha arribat.
     */
    public function markSent(WhatsAppMessage $message): WhatsAppMessage
    {
        $allowed = $message->provider === WhatsAppProviderKey::Manual ? [WhatsAppMessageStatus::Draft] : [WhatsAppMessageStatus::Unknown];

        $updated = WhatsAppMessage::query()->whereKey($message->id)->whereIn('status', array_map(fn ($s) => $s->value, $allowed))
            ->update(['status' => WhatsAppMessageStatus::ManualSent->value, 'sent_at' => now(), 'sent_by' => Auth::id()]);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['message' => __('whatsapp.cannot_mark')]);
        }

        $this->audit->log('whatsapp.marked_sent', $message);

        return $message->refresh();
    }

    public function discard(WhatsAppMessage $message): WhatsAppMessage
    {
        $updated = WhatsAppMessage::query()->whereKey($message->id)->whereIn('status', [WhatsAppMessageStatus::Draft->value, WhatsAppMessageStatus::Failed->value])
            ->update(['status' => WhatsAppMessageStatus::Discarded->value]);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['message' => __('whatsapp.only_drafts')]);
        }

        return $message->refresh();
    }

    /**
     * Enllaç per publicar manualment: api.whatsapp.com/send amb el text (grups i llistes: WhatsApp deixa triar el xat),
     * amb el telèfon per a números i l'enllaç d'invitació per als canals (no admeten text predefinit).
     * Es fa servir directament api.whatsapp.com (i no wa.me) perquè wa.me hi redirigeix i en alguns navegadors
     * l'emoji es perd en la redirecció.
     */
    public function shareUrl(WhatsAppMessage $message): ?string
    {
        $destination = $message->destination;
        $text = rawurlencode($message->body);

        return match ($destination->destination_type->value) {
            'channel' => $destination->invite_url,
            'phone' => $destination->external_id === null ? null : 'https://api.whatsapp.com/send?phone='.preg_replace('/\D/', '', $destination->external_id)."&text={$text}",
            default => "https://api.whatsapp.com/send?text={$text}",
        };
    }

    private function templateFor(?HomeAway $homeAway): ?WhatsAppTemplate
    {
        if ($homeAway === null) {
            return null;
        }

        return WhatsAppTemplate::query()->where('kind', TemplateKind::Match->value)->where('is_active', true)
            ->whereIn('applies_to', [$homeAway->value, TemplateAppliesTo::Any->value])
            ->get()
            ->sortBy(fn (WhatsAppTemplate $t) => [$t->applies_to === TemplateAppliesTo::Any ? 1 : 0, $t->id])
            ->first();
    }

    /**
     * @return array<string, string|null>
     */
    private function matchVariables(GameMatch $match, Schedule $schedule): array
    {
        $home = $match->home_away === HomeAway::Home;

        return [
            'equip' => $match->team->name,
            'rival' => $match->opponent,
            'data' => $match->match_date === null ? null : CatalanDate::weekday($match->match_date).' '.CatalanDate::date($match->match_date),
            'dia' => $match->match_date === null ? null : CatalanDate::weekday($match->match_date),
            'hora_convocatoria' => Time::hm($match->call_time),
            'hora_partit' => Time::hm($match->kickoff_time),
            'installacio' => $home ? $this->settings->homeVenue()->name : $match->venue_name,
            'adreca' => $home ? $this->settings->homeVenue()->address : $match->address,
            'municipi' => $home ? $this->settings->homeVenue()->municipality : $match->municipality,
            'mapa' => $home ? $this->settings->homeVenue()->maps_url : $match->maps_url,
            'observacions' => $match->notes,
            'informacio' => $match->whatsapp_extra,
            'jornada' => $schedule->matchday->label ?? CatalanDate::matchdayLabel($schedule->matchday->starts_on, $schedule->matchday->ends_on),
            'camp' => $home ? $match->fieldArea?->name : null,
        ];
    }

    /**
     * @param  Collection<int, GameMatch>  $matches
     * @return array<string, string|null>
     */
    private function summaryVariables(Schedule $schedule, Collection $matches, WhatsAppTemplate $template): array
    {
        $line = $template->line_body ?? '{{icona}} {{hora_partit}} · {{equip}} - {{rival}}';
        $lines = fn (int $weekday) => $matches
            ->filter(fn (GameMatch $m) => $m->match_date !== null && $m->kickoff_time !== null && $m->match_date->isoWeekday() === $weekday && $m->show_in_image)
            ->sortBy(fn (GameMatch $m) => [$m->kickoff_time, $m->team->sort_order])
            ->map(function (GameMatch $m) use ($line) {
                $away = $m->home_away === HomeAway::Away;
                $text = $this->renderer->render($line, [
                    'icona' => $away ? '🚌' : '🏠',
                    'hora_partit' => Time::hm($m->kickoff_time),
                    'hora_convocatoria' => Time::hm($m->call_time),
                    'equip' => $m->team->name,
                    'rival' => $m->opponent ?? '—',
                    'municipi' => $away ? $m->municipality : null,
                    'installacio' => $away ? $m->venue_name : $this->settings->homeVenue()->name,
                    'camp' => $away ? null : $m->fieldArea?->name,
                ], TemplateRenderer::LINE_VARIABLES);

                // Si la línia no inclou el municipi, s'hi afegeix per als partits a fora.
                return $away && $m->municipality !== null && ! str_contains($line, '{{municipi}}') ? "{$text} ({$m->municipality})" : $text;
            })
            ->implode("\n");

        $saturday = $lines(6);
        $sunday = $lines(7);

        return [
            'jornada' => $schedule->matchday->label ?? CatalanDate::matchdayLabel($schedule->matchday->starts_on, $schedule->matchday->ends_on),
            'club' => $this->settings->clubName(),
            'partits_dissabte' => $saturday !== '' ? $saturday : __('whatsapp.no_matches'),
            'partits_diumenge' => $sunday !== '' ? $sunday : __('whatsapp.no_matches'),
        ];
    }

    private function upsertDraft(Schedule $schedule, ?GameMatch $match, WhatsAppDestination $destination, WhatsAppTemplate $template, string $body): ?WhatsAppMessage
    {
        $hash = hash('sha256', $body);
        $existing = WhatsAppMessage::query()
            ->where('schedule_id', $schedule->id)
            ->where('match_id', $match?->id)
            ->where('whatsapp_destination_id', $destination->id)
            ->whereNotIn('status', [WhatsAppMessageStatus::Discarded->value, WhatsAppMessageStatus::Outdated->value])
            ->lockForUpdate()
            ->orderByDesc('id')
            ->get();

        // Ja s'ha enviat aquest mateix text: no es torna a preparar.
        if ($existing->contains(fn (WhatsAppMessage $m) => in_array($m->status, self::DELIVERED, true) && $m->body_hash === $hash)) {
            return null;
        }

        // Un missatge en curs o amb incidència no es toca.
        if ($existing->contains(fn (WhatsAppMessage $m) => in_array($m->status, [WhatsAppMessageStatus::Queued, WhatsAppMessageStatus::Sending, WhatsAppMessageStatus::Failed, WhatsAppMessageStatus::Unknown], true))) {
            return null;
        }

        // El que es va enviar ha quedat antic (ha canviat l'horari o les dades).
        foreach ($existing->filter(fn (WhatsAppMessage $m) => in_array($m->status, self::DELIVERED, true)) as $sent) {
            $sent->forceFill(['status' => WhatsAppMessageStatus::Outdated])->save();
        }

        $draft = $existing->first(fn (WhatsAppMessage $m) => $m->status === WhatsAppMessageStatus::Draft);
        $key = $this->idempotencyKey($schedule->id, $match?->id, $destination->id, $body);

        if ($draft !== null) {
            // L'esborrany (potser editat pel gestor) es manté mentre la versió confirmada sigui la mateixa.
            if ($draft->schedule_version_id !== $schedule->confirmed_version_id) {
                $draft->forceFill([
                    'body' => $body, 'body_hash' => $hash, 'idempotency_key' => $key,
                    'schedule_version_id' => $schedule->confirmed_version_id, 'whatsapp_template_id' => $template->id,
                    'provider' => $destination->provider,
                ])->save();
            }

            return $draft;
        }

        return WhatsAppMessage::query()->create([
            'schedule_id' => $schedule->id,
            'match_id' => $match?->id,
            'whatsapp_destination_id' => $destination->id,
            'whatsapp_template_id' => $template->id,
            'schedule_version_id' => $schedule->confirmed_version_id,
            'provider' => $destination->provider,
            'body' => $body,
            'body_hash' => $hash,
            'idempotency_key' => $key,
            'status' => WhatsAppMessageStatus::Draft,
            'created_by' => Auth::id(),
        ]);
    }

    private function attachImages(WhatsAppMessage $message, Schedule $schedule): void
    {
        $message->attachments()->delete();

        // Només les imatges de l'última generació del calendari (la resta són a l'històric).
        foreach ($this->images->current($schedule)->values() as $index => $image) {
            WhatsAppMessageAttachment::query()->create(['whatsapp_message_id' => $message->id, 'generated_image_id' => $image->id, 'sort_order' => $index]);
        }
    }

    private function idempotencyKey(int $scheduleId, ?int $matchId, int $destinationId, string $body): string
    {
        return hash('sha256', implode('|', [$scheduleId, $matchId ?? 'resum', $destinationId, hash('sha256', $body)]));
    }
}
