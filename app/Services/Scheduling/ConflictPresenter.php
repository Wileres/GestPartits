<?php

namespace App\Services\Scheduling;

use App\Domain\Scheduling\Conflicts\Conflict;
use App\Domain\Scheduling\Model\ScheduleInput;
use App\Support\CatalanDate;

/**
 * Converteix els conflictes del domini en dades per a l'API amb el text en català.
 */
class ConflictPresenter
{
    /**
     * @param  list<Conflict>  $conflicts
     * @return list<array<string, mixed>>
     */
    public function present(array $conflicts, ScheduleInput $input): array
    {
        return array_map(fn (Conflict $c) => [...$c->toArray(), 'message' => $this->message($c, $input)], $conflicts);
    }

    public function message(Conflict $conflict, ScheduleInput $input): string
    {
        $teams = implode(', ', array_map(fn (int $id) => $input->match($id)?->teamName ?? "#{$id}", $conflict->matchIds));
        $params = $conflict->params;

        $message = __('conflicts.'.$conflict->code->value, [
            'teams' => $teams,
            'from' => $params['from'] ?? '',
            'to' => $params['to'] ?? '',
            'reason' => $params['reason'] ?? '',
            'rule' => $this->ruleDescription($params),
        ]);

        if (! empty($params['override'])) {
            $message .= ' '.__('conflicts.override', ['reason' => $params['override']]);
        }

        return $message;
    }

    /**
     * «Primer equip: dissabte 17:00», «Escoleta: no abans de 10:00».
     *
     * @param  array<string, scalar|null>  $params
     */
    private function ruleDescription(array $params): string
    {
        $parts = [];
        if (! empty($params['preferred'])) {
            $parts[] = trim((isset($params['weekday']) ? CatalanDate::weekdayName((int) $params['weekday']).' ' : '').$params['preferred']);
        }
        if (! empty($params['not_before'])) {
            $parts[] = __('conflicts.not_before', ['time' => $params['not_before']]);
        }
        if (! empty($params['not_after'])) {
            $parts[] = __('conflicts.not_after', ['time' => $params['not_after']]);
        }

        $label = (string) ($params['rule'] ?? '');

        return trim($label.($label !== '' && $parts !== [] ? ': ' : '').implode(', ', $parts));
    }
}
