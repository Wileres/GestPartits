/**
 * Geometria de l'editor d'imatges per a xarxes socials: mides de publicació d'Instagram i enquadrament
 * (zoom, desplaçament i gir) de la fotografia dins del llenç.
 */

export type PostFormat = 'portrait' | 'square';

/** Mides de publicació d'Instagram: vertical 4:5 (la d'una publicació habitual) i quadrada 1:1. */
export const POST_SIZES: Record<PostFormat, { width: number; height: number }> = {
    portrait: { width: 1080, height: 1350 },
    square: { width: 1080, height: 1080 },
};

export const MIN_ZOOM = 1;
export const MAX_ZOOM = 4;

export type Rotation = 0 | 90 | 180 | 270;

export interface Framing {
    /** 1 = la foto omple el llenç just; més gran = ampliada. */
    zoom: number;
    /** Desplaçament del centre de la foto respecte del centre del llenç, en píxels del llenç. */
    offsetX: number;
    offsetY: number;
    rotation: Rotation;
}

export const DEFAULT_FRAMING: Framing = { zoom: 1, offsetX: 0, offsetY: 0, rotation: 0 };

/** Mida de la foto un cop girada (a 90° i 270° s'intercanvien amplada i alçada). */
export function rotatedSize(imageWidth: number, imageHeight: number, rotation: Rotation): { width: number; height: number } {
    return rotation === 90 || rotation === 270 ? { width: imageHeight, height: imageWidth } : { width: imageWidth, height: imageHeight };
}

/** Escala amb què la foto (ja girada) omple el llenç sense deixar cap buit, abans d'aplicar el zoom. */
export function coverScale(imageWidth: number, imageHeight: number, rotation: Rotation, canvasWidth: number, canvasHeight: number): number {
    const size = rotatedSize(imageWidth, imageHeight, rotation);

    return Math.max(canvasWidth / size.width, canvasHeight / size.height);
}

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));

/**
 * Manté la foto sempre cobrint el llenç: el desplaçament màxim és la meitat del que sobresurt de la foto.
 */
export function clampFraming(framing: Framing, imageWidth: number, imageHeight: number, canvasWidth: number, canvasHeight: number): Framing {
    const zoom = clamp(framing.zoom, MIN_ZOOM, MAX_ZOOM);
    const size = rotatedSize(imageWidth, imageHeight, framing.rotation);
    const scale = coverScale(imageWidth, imageHeight, framing.rotation, canvasWidth, canvasHeight) * zoom;
    const maxX = Math.max(0, (size.width * scale - canvasWidth) / 2);
    const maxY = Math.max(0, (size.height * scale - canvasHeight) / 2);

    return { ...framing, zoom, offsetX: clamp(framing.offsetX, -maxX, maxX), offsetY: clamp(framing.offsetY, -maxY, maxY) };
}

/** Rectangle on es dibuixa la foto (sense girar) i escala final, per a `drawImage`. */
export function imagePlacement(framing: Framing, imageWidth: number, imageHeight: number, canvasWidth: number, canvasHeight: number) {
    const scale = coverScale(imageWidth, imageHeight, framing.rotation, canvasWidth, canvasHeight) * framing.zoom;

    return {
        scale,
        centerX: canvasWidth / 2 + framing.offsetX,
        centerY: canvasHeight / 2 + framing.offsetY,
        width: imageWidth * scale,
        height: imageHeight * scale,
    };
}

/** Ajustos bàsics de la imatge: de -100 a 100 (0 = sense canvis). */
export interface Adjustments {
    brightness: number;
    contrast: number;
    saturation: number;
}

export const NO_ADJUSTMENTS: Adjustments = { brightness: 0, contrast: 0, saturation: 0 };

/** Valor del filtre de llenç (`ctx.filter`) per als ajustos indicats. */
export function adjustmentsFilter(adjustments: Adjustments): string {
    const percent = (value: number) => Math.round(100 + clamp(value, -100, 100));

    return `brightness(${percent(adjustments.brightness)}%) contrast(${percent(adjustments.contrast)}%) saturate(${percent(adjustments.saturation)}%)`;
}
