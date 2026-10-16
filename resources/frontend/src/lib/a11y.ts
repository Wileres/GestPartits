/**
 * Valor d'aria-describedby per a un camp de FormField (l'error té prioritat sobre l'ajuda).
 */
export function describedBy(id: string, error?: string, help?: string): string | undefined {
    if (error !== undefined) return `${id}-error`;
    if (help !== undefined) return `${id}-help`;

    return undefined;
}
