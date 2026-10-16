import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { maskDisplayDate } from '@/lib/dates';

/**
 * Data en format DD/MM/AAAA independent de l'idioma del navegador (el camp natiu <input type="date">
 * mostra MM/DD/AAAA en navegadors en anglès). Les barres s'afegeixen mentre s'escriu.
 */
export function DateTextInput({ onChange, ...props }: Omit<ComponentProps<typeof Input>, 'type'>) {
    return (
        <Input
            {...props}
            type="text"
            inputMode="numeric"
            placeholder="dd/mm/aaaa"
            maxLength={10}
            autoComplete="off"
            onChange={(event) => {
                event.target.value = maskDisplayDate(event.target.value);
                onChange?.(event);
            }}
        />
    );
}
