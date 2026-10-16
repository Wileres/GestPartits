import type { FieldArea, SchedulingRule } from '@/api/types';
import { t } from '@/i18n/ca';
import { weekdayName } from './dates';

/**
 * Resum llegible d'una regla: «dissabte 17:00 · Camp 1», «no abans de 10:00».
 */
export function describeRule(rule: SchedulingRule, areas: FieldArea[] = []): string {
    const parts: string[] = [];

    if (rule.preferred_time !== null) {
        const day = rule.weekday === null ? '' : `${weekdayName(rule.weekday)} `;
        const area = areas.find((a) => a.id === rule.preferred_field_area_id);
        parts.push(`${day}${rule.preferred_time}${area === undefined ? '' : ` · ${area.name}`}`);
    }

    if (rule.not_before !== null) parts.push(`${t('rules.notBefore').toLowerCase()} ${rule.not_before}`);
    if (rule.not_after !== null) parts.push(`${t('rules.notAfter').toLowerCase()} ${rule.not_after}`);

    return parts.join(' · ');
}
