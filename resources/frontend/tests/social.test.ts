import { describe, expect, it } from 'vitest';
import { matchDayLayout, TEXT_AREA } from '@/features/social/drawMatchDay';
import { drawPost, GRADIENT_START, type PostOptions } from '@/features/social/drawPost';
import {
    adjustmentsFilter, clampFraming, coverScale, DEFAULT_FRAMING, imagePlacement, MAX_ZOOM, NO_ADJUSTMENTS, POST_SIZES, rotatedSize,
} from '@/features/social/postGeometry';

describe('mides de publicació', () => {
    it('la vertical és 4:5 i la quadrada 1:1, amb 1080 px d’amplada', () => {
        expect(POST_SIZES.portrait).toEqual({ width: 1080, height: 1350 });
        expect(POST_SIZES.square).toEqual({ width: 1080, height: 1080 });
        expect(POST_SIZES.portrait.width / POST_SIZES.portrait.height).toBeCloseTo(0.8);
    });
});

describe('enquadrament de la foto', () => {
    it('amb zoom 1 la foto omple el llenç sense buits', () => {
        // Foto apaïsada 1600 × 1200 en un llenç 1080 × 1350: mana l’alçada.
        expect(coverScale(1600, 1200, 0, 1080, 1350)).toBeCloseTo(1350 / 1200);
        // Girada 90°, la mida es capgira i mana l’amplada.
        expect(rotatedSize(1600, 1200, 90)).toEqual({ width: 1200, height: 1600 });
        expect(coverScale(1600, 1200, 90, 1080, 1350)).toBeCloseTo(Math.max(1080 / 1200, 1350 / 1600));
    });

    it('el desplaçament mai deixa veure el fons del llenç', () => {
        const framed = clampFraming({ ...DEFAULT_FRAMING, zoom: 1, offsetX: 5000, offsetY: -5000 }, 1600, 1200, 1080, 1350);
        const place = imagePlacement(framed, 1600, 1200, 1080, 1350);

        // Els costats de la foto cobreixen sempre el llenç.
        expect(place.centerX - place.width / 2).toBeLessThanOrEqual(0.0001);
        expect(place.centerX + place.width / 2).toBeGreaterThanOrEqual(1080 - 0.0001);
        expect(place.centerY - place.height / 2).toBeLessThanOrEqual(0.0001);
        expect(place.centerY + place.height / 2).toBeGreaterThanOrEqual(1350 - 0.0001);
        // Amb zoom 1 l’alçada ja és justa: no es pot moure en vertical.
        expect(framed.offsetY).toBeCloseTo(0);
    });

    it('amb més ampliació hi ha més marge per desplaçar-la i el zoom queda limitat', () => {
        const base = clampFraming({ ...DEFAULT_FRAMING, offsetX: 9999 }, 1600, 1200, 1080, 1350);
        const zoomed = clampFraming({ ...DEFAULT_FRAMING, zoom: 2, offsetX: 9999 }, 1600, 1200, 1080, 1350);
        expect(zoomed.offsetX).toBeGreaterThan(base.offsetX);
        expect(clampFraming({ ...DEFAULT_FRAMING, zoom: 99 }, 1600, 1200, 1080, 1350).zoom).toBe(MAX_ZOOM);
        expect(clampFraming({ ...DEFAULT_FRAMING, zoom: 0.1 }, 1600, 1200, 1080, 1350).zoom).toBe(1);
    });

    it('els ajustos bàsics es tradueixen en un filtre del llenç', () => {
        expect(adjustmentsFilter(NO_ADJUSTMENTS)).toBe('brightness(100%) contrast(100%) saturate(100%)');
        expect(adjustmentsFilter({ brightness: 20, contrast: -30, saturation: 100 })).toBe('brightness(120%) contrast(70%) saturate(200%)');
        expect(adjustmentsFilter({ brightness: 500, contrast: -500, saturation: 0 })).toBe('brightness(200%) contrast(0%) saturate(100%)');
    });
});

/** Llenç de mentida que enregistra el que es dibuixa. */
function fakeContext() {
    const log: { op: string; args: unknown[]; fillStyle: unknown }[] = [];
    const state: Record<string, unknown> = { fillStyle: '', font: '', filter: '', globalAlpha: 1 };
    const ctx = new Proxy(
        {},
        {
            get: (_t, name: string) => {
                if (name === 'measureText') return (text: string) => ({ width: text.length * 20 });
                if (name === 'createLinearGradient') {
                    return (...args: unknown[]) => {
                        log.push({ op: 'gradient', args, fillStyle: null });

                        return { addColorStop: (...stop: unknown[]) => log.push({ op: 'stop', args: stop, fillStyle: null }) };
                    };
                }
                if (name in state) return state[name];

                return (...args: unknown[]) => log.push({ op: name, args, fillStyle: state.fillStyle });
            },
            set: (_t, name: string, value: unknown) => {
                state[name] = value;

                return true;
            },
        },
    ) as unknown as CanvasRenderingContext2D;

    return { ctx, log };
}

const picture = { width: 1600, height: 1200 };
const crest = { width: 200, height: 200 };

function options(overrides: Partial<PostOptions> = {}): PostOptions {
    return {
        width: 1080, height: 1350, photo: picture as never, framing: DEFAULT_FRAMING, adjustments: NO_ADJUSTMENTS,
        text: { team: 'Benjamí S10 B masc', scoreTeam: '7', scoreOpponent: '0', opponent: 'UE Llagostera', competition: 'Primera divisió' },
        accent: '#2f7bff', teamCrest: crest as never, opponentCrest: crest as never, ...overrides,
    };
}

describe('publicació amb el resultat', () => {
    it('dibuixa la foto, el difuminat negre a la part inferior i el resultat en blau', () => {
        const { ctx, log } = fakeContext();

        drawPost(ctx, options());

        const ops = log.map((entry) => entry.op);
        // Primer la foto i després el difuminat; el text, al final.
        expect(ops.indexOf('drawImage')).toBeLessThan(ops.indexOf('gradient'));
        expect(ops.indexOf('gradient')).toBeLessThan(ops.indexOf('fillText'));

        // El difuminat comença a la meitat del llenç i arriba al negre gairebé opac.
        const gradient = log.find((entry) => entry.op === 'gradient');
        expect(gradient?.args).toEqual([0, Math.round(1350 * GRADIENT_START), 0, 1350]);
        expect(log.filter((entry) => entry.op === 'stop').map((entry) => entry.args[1])).toEqual(['rgba(0, 0, 0, 0)', 'rgba(0, 0, 0, 0.78)', 'rgba(0, 0, 0, 0.96)']);

        const texts = log.filter((entry) => entry.op === 'fillText');
        const score = texts.find((entry) => entry.args[0] === '7 - 0');
        expect(score?.fillStyle).toBe('#2f7bff');
        // El nom de l’equip i la competició, en majúscules i en blanc.
        expect(texts.find((entry) => entry.args[0] === 'BENJAMÍ S10 B MASC')?.fillStyle).toBe('#ffffff');
        expect(texts.some((entry) => entry.args[0] === 'PRIMERA DIVISIÓ')).toBe(true);
        // Els dos escuts.
        expect(ops.filter((op) => op === 'drawImage')).toHaveLength(3);
    });

    it('si no hi ha escut del rival, hi escriu el nom del rival', () => {
        const { ctx, log } = fakeContext();

        drawPost(ctx, options({ opponentCrest: null }));

        expect(log.some((entry) => entry.op === 'fillText' && entry.args[0] === 'UE LLAGOSTERA')).toBe(true);
        expect(log.filter((entry) => entry.op === 'drawImage')).toHaveLength(2);
    });

    it('sense resultat no dibuixa cap marcador', () => {
        const { ctx, log } = fakeContext();

        drawPost(ctx, options({ text: { team: 'Aleví A', scoreTeam: '', scoreOpponent: '', opponent: '', competition: '' } }));

        const texts = log.filter((entry) => entry.op === 'fillText').map((entry) => entry.args[0]);
        expect(texts).toEqual(['ALEVÍ A']);
    });

    it('aplica els ajustos només a la foto i gira el llenç segons la rotació', () => {
        const { ctx, log } = fakeContext();

        drawPost(ctx, options({ adjustments: { brightness: 10, contrast: 0, saturation: 0 }, framing: { ...DEFAULT_FRAMING, rotation: 90 } }));

        // El filtre es restableix després de la foto: el difuminat i el text no s'ajusten.
        expect((ctx as unknown as { filter: string }).filter).toBe('none');
        const rotate = log.find((entry) => entry.op === 'rotate');
        expect(rotate?.args[0]).toBeCloseTo(Math.PI / 2);
    });
});

describe('cartell de «Dia de partit»', () => {
    it('l’alçada de cada partit és proporcional al nombre de partits i mai surt del text', () => {
        const few = matchDayLayout(1080, 1350, 2);
        const many = matchDayLayout(1080, 1350, 8);

        expect(many.blockHeight).toBeLessThan(few.blockHeight);
        expect(many.textBottom).toBeLessThanOrEqual(1350 * TEXT_AREA + 0.001);
        expect(many.fadeBottom).toBeGreaterThan(many.textBottom);
        expect(matchDayLayout(1080, 1350, 0).blockHeight).toBe(0);
    });
});
