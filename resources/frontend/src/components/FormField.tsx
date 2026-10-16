import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';

interface FormFieldProps {
    id: string;
    label: string;
    error?: string;
    help?: string;
    children: ReactNode;
}

/**
 * Etiqueta, control, ajuda i error d'un camp, enllaçats per a lectors de pantalla.
 */
export function FormField({ id, label, error, help, children }: FormFieldProps) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {help !== undefined && error === undefined && (
                <p id={`${id}-help`} className="text-xs text-muted-foreground">
                    {help}
                </p>
            )}
            {error !== undefined && (
                <p id={`${id}-error`} role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}

