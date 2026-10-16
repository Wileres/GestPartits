<?php

namespace App\Domain\Scheduling\Conflicts;

enum ConflictCode: string
{
    case F7CapacityExceeded = 'F7_CAPACITY_EXCEEDED';
    case F11OverlapsF7 = 'F11_OVERLAPS_F7';
    case F11OverlapsF11 = 'F11_OVERLAPS_F11';
    case AreaOverlap = 'AREA_OVERLAP';
    case F11RequiresFullField = 'F11_REQUIRES_FULL_FIELD';
    case F7OnFullField = 'F7_ON_FULL_FIELD';
    case OutsideOperatingHours = 'OUTSIDE_OPERATING_HOURS';
    case OffGrid = 'OFF_GRID';
    case OutsideSlot = 'OUTSIDE_SLOT';
    case OutsideMatchday = 'OUTSIDE_MATCHDAY';
    case FieldBlocked = 'FIELD_BLOCKED';
    case DuplicateTeam = 'DUPLICATE_TEAM';
    case CallAfterKickoff = 'CALL_AFTER_KICKOFF';
    case MandatoryRuleBroken = 'MANDATORY_RULE_BROKEN';
    case RecommendedRuleNotMet = 'RECOMMENDED_RULE_NOT_MET';
    case HomeMatchUnscheduled = 'HOME_MATCH_UNSCHEDULED';
    case AwayMissingData = 'AWAY_MISSING_DATA';
}
