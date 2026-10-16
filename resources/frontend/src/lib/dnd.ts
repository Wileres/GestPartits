import type { Announcements, ScreenReaderInstructions, UniqueIdentifier } from '@dnd-kit/core';
import { t } from '@/i18n/ca';

/**
 * Anuncis en català per als lectors de pantalla durant l'arrossegament (dnd-kit els fa en anglès per defecte).
 */
export function catalanAnnouncements(label: (id: UniqueIdentifier) => string): { announcements: Announcements; screenReaderInstructions: ScreenReaderInstructions } {
    return {
        screenReaderInstructions: { draggable: t('dnd.instructions') },
        announcements: {
            onDragStart: ({ active }) => t('dnd.picked', { item: label(active.id) }),
            onDragOver: ({ active, over }) =>
                over === null ? t('dnd.overNothing', { item: label(active.id) }) : t('dnd.over', { item: label(active.id), target: label(over.id) }),
            onDragEnd: ({ active, over }) =>
                over === null ? t('dnd.droppedNothing', { item: label(active.id) }) : t('dnd.dropped', { item: label(active.id), target: label(over.id) }),
            onDragCancel: ({ active }) => t('dnd.cancelled', { item: label(active.id) }),
        },
    };
}
