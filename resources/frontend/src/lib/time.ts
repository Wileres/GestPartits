/**
 * Hores locals "HH:MM" i minuts des de mitjanit (mateixa lògica que App\Support\Time).
 */
export function toMinutes(time: string): number {
    const match = /^(\d{2}):(\d{2})/.exec(time);
    if (match === null) {
        throw new Error(`Hora no vàlida: ${time}`);
    }

    return Number(match[1]) * 60 + Number(match[2]);
}

export function fromMinutes(minutes: number): string {
    return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

export function isOnGrid(time: string, stepMinutes: number): boolean {
    return toMinutes(time) % stepMinutes === 0;
}

export function isTime(value: string): boolean {
    return /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
}

export function isWithin(time: string, from: string, to: string): boolean {
    const minutes = toMinutes(time);

    return minutes >= toMinutes(from) && minutes <= toMinutes(to);
}

/** Hores de «from» a «to» (incloses) cada «step» minuts. */
export function quarterHours(from = '00:00', to = '23:45', step = 15): string[] {
    const options: string[] = [];
    for (let m = toMinutes(from); m <= toMinutes(to); m += step) {
        options.push(fromMinutes(m));
    }

    return options;
}
