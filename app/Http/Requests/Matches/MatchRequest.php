<?php

namespace App\Http\Requests\Matches;

use App\Enums\FieldAreaKind;
use App\Enums\GameFormat;
use App\Enums\HomeAway;
use App\Enums\MatchStatus;
use App\Models\FieldArea;
use App\Models\GameMatch;
use App\Models\Matchday;
use App\Models\Team;
use App\Rules\TimeOnGrid;
use App\Rules\WithinOperatingHours;
use App\Support\ClubSettings;
use App\Support\Time;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta i modificació d'un partit. Els conflictes d'horari (solapaments, franges, regles)
 * els detecta el motor de programació; aquí només es rebutgen les dades impossibles.
 */
class MatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $match = $this->route('match');

        return $match instanceof GameMatch
            ? $this->user()->can('update', $match)
            : $this->user()->can('create', GameMatch::class);
    }

    public function matchday(): Matchday
    {
        $match = $this->route('match');

        return $match instanceof GameMatch ? $match->matchday : $this->route('matchday');
    }

    private function isUpdate(): bool
    {
        return $this->route('match') instanceof GameMatch;
    }

    private function isHome(): bool
    {
        return $this->input('home_away') === HomeAway::Home->value;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $settings = app(ClubSettings::class);
        $hours = $settings->operatingHours();
        $matchday = $this->matchday();

        $kickoffRules = ['nullable', 'date_format:H:i', 'required_with:field_area_id'];
        if ($this->isHome()) {
            $kickoffRules[] = new TimeOnGrid($settings->kickoffStepMin());
            $kickoffRules[] = new WithinOperatingHours($hours['first_kickoff'], $hours['last_kickoff']);
        }

        return [
            'lock_version' => $this->isUpdate() ? ['required', 'integer', 'min:0'] : ['prohibited'],
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->whereNull('deleted_at')],
            // Sense estat, un partit nou es desa com a confirmat.
            'status' => ['sometimes', Rule::enum(MatchStatus::class)],
            'home_away' => ['required', Rule::enum(HomeAway::class)],
            'opponent' => ['nullable', 'string', 'max:128'],
            'round_label' => ['nullable', 'string', 'max:64'],
            'game_format' => ['nullable', Rule::enum(GameFormat::class)],
            'match_date' => [
                'nullable', 'date_format:Y-m-d', 'required_with:kickoff_time',
                'after_or_equal:'.$matchday->starts_on->toDateString(),
                'before_or_equal:'.$matchday->ends_on->toDateString(),
            ],
            'kickoff_time' => $kickoffRules,
            'call_time' => ['nullable', 'date_format:H:i'],
            'duration_min' => ['nullable', 'integer', 'min:1', 'max:300'],
            'post_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'field_area_id' => ['nullable', 'integer', Rule::exists('field_areas', 'id')->where('venue_id', $settings->homeVenue()->id)],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:128'],
            'maps_url' => ['nullable', 'url:https', 'max:2048'],
            'is_locked' => ['sometimes', 'boolean'],
            'rule_override_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'whatsapp_extra' => ['nullable', 'string', 'max:2000'],
            'show_in_image' => ['sometimes', 'boolean'],
            // Resultat un cop acabat el partit: tots dos gols o cap.
            'score_team' => ['nullable', 'integer', 'min:0', 'max:99', 'required_with:score_opponent'],
            'score_opponent' => ['nullable', 'integer', 'min:0', 'max:99', 'required_with:score_team'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $kickoff = $this->input('kickoff_time');
                $call = $this->input('call_time');

                if ($kickoff !== null && $call !== null && Time::toMinutes($call) > Time::toMinutes($kickoff)) {
                    $validator->errors()->add('call_time', __('messages.validation.call_after_kickoff'));
                }

                if (($this->input('score_team') !== null || $this->input('score_opponent') !== null) && $this->input('status') === MatchStatus::Cancelled->value) {
                    $validator->errors()->add('score_team', __('messages.validation.result_cancelled'));
                }

                $areaId = $this->input('field_area_id');

                if ($areaId !== null && ! $this->isHome()) {
                    $validator->errors()->add('field_area_id', __('messages.validation.area_only_home'));

                    return;
                }

                if (! $this->isHome()) {
                    return;
                }

                $placementParts = array_filter([$this->input('match_date'), $kickoff, $areaId], fn ($v) => $v !== null);
                if (count($placementParts) > 0 && count($placementParts) < 3 && $kickoff !== null) {
                    $validator->errors()->add('field_area_id', __('messages.validation.placement_incomplete'));

                    return;
                }

                if ($areaId !== null) {
                    $format = $this->input('game_format') ?? Team::query()->with('category')->find($this->input('team_id'))?->effectiveGameFormat()->value;
                    $area = FieldArea::query()->find($areaId);

                    if ($format === GameFormat::Football11->value && $area?->kind !== FieldAreaKind::Full) {
                        $validator->errors()->add('field_area_id', __('messages.validation.f11_full_field'));
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'team_id' => 'equip',
            'status' => 'estat',
            'home_away' => 'condició',
            'opponent' => 'rival',
            'round_label' => 'jornada de competició',
            'game_format' => 'modalitat',
            'match_date' => 'data',
            'kickoff_time' => 'hora del partit',
            'call_time' => 'hora de convocatòria',
            'duration_min' => 'durada',
            'post_min' => 'marge posterior',
            'field_area_id' => 'camp',
            'venue_name' => 'instal·lació',
            'address' => 'adreça',
            'municipality' => 'municipi',
            'maps_url' => 'enllaç de Google Maps',
            'notes' => 'observacions',
            'whatsapp_extra' => 'informació addicional per al WhatsApp',
            'rule_override_reason' => 'motiu de l\'excepció',
            'score_team' => 'gols de l\'equip',
            'score_opponent' => 'gols del rival',
        ];
    }

    public function messages(): array
    {
        return [
            'home_away.required' => 'Indica si el partit és a casa o a fora.',
            'match_date.after_or_equal' => 'La data ha de ser dins de la jornada.',
            'match_date.before_or_equal' => 'La data ha de ser dins de la jornada.',
            'match_date.required_with' => 'Cal indicar la data si s\'indica l\'hora.',
            'kickoff_time.required_with' => 'Cal indicar l\'hora del partit per assignar-li un camp.',
            'score_team.required_with' => 'Indica els gols dels dos equips.',
            'score_opponent.required_with' => 'Indica els gols dels dos equips.',
            'maps_url.url' => 'L\'enllaç ha de començar per https://.',
        ];
    }
}
