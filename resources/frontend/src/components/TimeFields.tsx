import type { ComponentProps } from 'react';
import { NativeSelect } from '@/components/NativeSelect';
import { Input } from '@/components/ui/input';
import { quarterHours } from '@/lib/time';

/**
 * Hores en format 24 h independent de l'idioma del navegador (el camp natiu <input type="time">
 * mostra AM/PM en navegadors en anglès).
 */

interface QuarterTimeSelectProps extends Omit<ComponentProps<'select'>, 'children'> {
    from?: string;
    to?: string;
    step?: number;
    /** Text de l'opció buida (si no s'indica, l'hora és obligatòria). */
    emptyLabel?: string;
}

/** Desplegable d'hores en intervals (per defecte, cada 15 minuts): no permet hores fora de la graella. */
export function QuarterTimeSelect({ from, to, step, emptyLabel, ...props }: QuarterTimeSelectProps) {
    return (
        <NativeSelect {...props}>
            {emptyLabel !== undefined && <option value="">{emptyLabel}</option>}
            {quarterHours(from, to, step).map((time) => (
                <option key={time} value={time}>
                    {time}
                </option>
            ))}
        </NativeSelect>
    );
}

/** Camp de text per a hores lliures (p. ex. un partit a fora a les 10:10). */
export function TimeTextInput(props: Omit<ComponentProps<typeof Input>, 'type'>) {
    return <Input {...props} type="text" inputMode="numeric" placeholder="hh:mm" maxLength={5} autoComplete="off" pattern="[0-2][0-9]:[0-5][0-9]" />;
}
