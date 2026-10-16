<?php

namespace App\Enums;

enum ScheduleVersionKind: string
{
    case PreProposal = 'pre_proposal';
    case Proposal = 'proposal';
    case ManualSave = 'manual_save';
    case Confirmation = 'confirmation';
    case Undo = 'undo';

    public function label(): string
    {
        return match ($this) {
            self::PreProposal => 'Abans de la proposta',
            self::Proposal => 'Proposta automàtica',
            self::ManualSave => 'Desat manual',
            self::Confirmation => 'Confirmació',
            self::Undo => 'Desfet',
        };
    }
}
