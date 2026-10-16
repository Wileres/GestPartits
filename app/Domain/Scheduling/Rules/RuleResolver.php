<?php

namespace App\Domain\Scheduling\Rules;

use App\Domain\Scheduling\Model\MatchItem;
use App\Domain\Scheduling\Model\RuleSpec;

/**
 * Regles aplicables a un partit.
 * - Preferència: només una, la més específica (equip > categoria > modalitat), després la de més prioritat
 *   i, en cas d'empat, la d'id més baix.
 * - Finestres (no abans de / no després de): totes les vigents.
 */
final class RuleResolver
{
    /** @var list<RuleSpec> */
    private array $rules;

    /**
     * @param  list<RuleSpec>  $rules
     */
    public function __construct(array $rules, string $referenceDate)
    {
        $this->rules = array_values(array_filter($rules, fn (RuleSpec $r) => $r->isValidOn($referenceDate)));
    }

    public function preference(MatchItem $match): ?RuleSpec
    {
        $candidates = array_values(array_filter($this->rules, fn (RuleSpec $r) => $r->hasPreference() && $r->appliesTo($match)));

        usort($candidates, fn (RuleSpec $a, RuleSpec $b) => [$b->specificity(), $b->priority, $a->id] <=> [$a->specificity(), $a->priority, $b->id]);

        return $candidates[0] ?? null;
    }

    /**
     * @return list<RuleSpec>
     */
    public function windows(MatchItem $match): array
    {
        $windows = array_values(array_filter($this->rules, fn (RuleSpec $r) => $r->hasWindow() && $r->appliesTo($match)));
        usort($windows, fn (RuleSpec $a, RuleSpec $b) => $a->id <=> $b->id);

        return $windows;
    }
}
