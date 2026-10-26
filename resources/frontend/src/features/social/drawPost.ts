import { adjustmentsFilter, imagePlacement, type Adjustments, type Framing } from './postGeometry';

export interface PostText {
    /** Nom de l'equip («BENJAMÍ S10 B MASC»). */
    team: string;
    scoreTeam: string;
    scoreOpponent: string;
    /** Nom del rival: només es dibuixa si no hi ha escut del rival. */
    opponent: string;
    /** Competició («PRIMERA DIVISIÓ»). */
    competition: string;
}

export interface PostOptions {
    width: number;
    height: number;
    photo: CanvasImageSource & { width: number; height: number };
    framing: Framing;
    adjustments: Adjustments;
    text: PostText;
    /** Color del resultat (blau). */
    accent: string;
    teamCrest: (CanvasImageSource & { width: number; height: number }) | null;
    opponentCrest: (CanvasImageSource & { width: number; height: number }) | null;
}

export const FONT_FAMILY = "'Inter Variable', Inter, system-ui, sans-serif";

/** Alçada del degradat negre, en proporció de l'alçada del llenç. */
export const GRADIENT_START = 0.5;

/**
 * Dibuixa la publicació: la foto enquadrada i ajustada, un difuminat negre a la part inferior i, a sobre,
 * el nom de l'equip, el resultat (en el color indicat) amb els escuts i la competició.
 */
export function drawPost(ctx: CanvasRenderingContext2D, options: PostOptions): void {
    const { width, height, photo, framing, adjustments } = options;

    ctx.clearRect(0, 0, width, height);
    ctx.fillStyle = '#000000';
    ctx.fillRect(0, 0, width, height);

    // Foto: enquadrament i ajustos bàsics.
    const place = imagePlacement(framing, photo.width, photo.height, width, height);
    ctx.save();
    ctx.filter = adjustmentsFilter(adjustments);
    ctx.translate(place.centerX, place.centerY);
    ctx.rotate((framing.rotation * Math.PI) / 180);
    ctx.drawImage(photo, -place.width / 2, -place.height / 2, place.width, place.height);
    ctx.restore();
    ctx.filter = 'none';

    // Difuminat negre de baix.
    const top = Math.round(height * GRADIENT_START);
    const gradient = ctx.createLinearGradient(0, top, 0, height);
    gradient.addColorStop(0, 'rgba(0, 0, 0, 0)');
    gradient.addColorStop(0.55, 'rgba(0, 0, 0, 0.78)');
    gradient.addColorStop(1, 'rgba(0, 0, 0, 0.96)');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, top, width, height - top);

    drawResult(ctx, options, width, height);
}

function fitFont(ctx: CanvasRenderingContext2D, value: string, weight: number, start: number, maxWidth: number): number {
    let size = start;
    ctx.font = `${weight} ${size}px ${FONT_FAMILY}`;
    while (size > 18 && ctx.measureText(value).width > maxWidth) {
        size -= 2;
        ctx.font = `${weight} ${size}px ${FONT_FAMILY}`;
    }

    return size;
}

function drawResult(ctx: CanvasRenderingContext2D, options: PostOptions, width: number, height: number): void {
    const { text, accent, teamCrest, opponentCrest } = options;
    const margin = width * 0.07;
    const maxWidth = width - margin * 2;
    const score = `${text.scoreTeam.trim()} - ${text.scoreOpponent.trim()}`;
    const hasScore = text.scoreTeam.trim() !== '' && text.scoreOpponent.trim() !== '';

    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'center';

    // De baix cap amunt: competició, resultat amb escuts, línia, nom de l'equip.
    let y = height - height * 0.06;

    if (text.competition.trim() !== '') {
        const value = text.competition.trim().toUpperCase();
        fitFont(ctx, value, 500, Math.round(width * 0.036), maxWidth);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.92)';
        ctx.fillText(value, width / 2, y);
        y -= width * 0.065;
    }

    if (hasScore) {
        const scoreSize = fitFont(ctx, score, 900, Math.round(width * 0.14), maxWidth * 0.6);
        const crest = Math.round(width * 0.14);
        const gap = width * 0.035;
        const scoreWidth = ctx.measureText(score).width;
        const baseline = y - Math.max(0, (crest - scoreSize * 0.72) / 2);

        ctx.fillStyle = accent;
        ctx.fillText(score, width / 2, baseline);

        const crestY = baseline - scoreSize * 0.72 / 2 - crest / 2;
        if (teamCrest !== null) {
            drawCrest(ctx, teamCrest, width / 2 - scoreWidth / 2 - gap - crest, crestY, crest);
        }
        if (opponentCrest !== null) {
            drawCrest(ctx, opponentCrest, width / 2 + scoreWidth / 2 + gap, crestY, crest);
        } else if (text.opponent.trim() !== '') {
            const value = text.opponent.trim().toUpperCase();
            const available = width / 2 - scoreWidth / 2 - gap - margin;
            fitFont(ctx, value, 700, Math.round(width * 0.04), available);
            ctx.textAlign = 'left';
            ctx.fillStyle = 'rgba(255, 255, 255, 0.92)';
            ctx.fillText(value, width / 2 + scoreWidth / 2 + gap, baseline - scoreSize * 0.2);
            ctx.textAlign = 'center';
        }

        y = baseline - Math.max(scoreSize * 0.72, crest) - width * 0.025;
    }

    // Línia de color sota el nom, com a separació.
    ctx.fillStyle = accent;
    ctx.globalAlpha = 0.9;
    ctx.fillRect(margin, y, maxWidth, Math.max(3, width * 0.003));
    ctx.globalAlpha = 1;
    y -= width * 0.03;

    if (text.team.trim() !== '') {
        const value = text.team.trim().toUpperCase();
        fitFont(ctx, value, 800, Math.round(width * 0.062), maxWidth);
        ctx.fillStyle = '#ffffff';
        ctx.fillText(value, width / 2, y);
    }
}

function drawCrest(ctx: CanvasRenderingContext2D, crest: CanvasImageSource & { width: number; height: number }, x: number, y: number, size: number): void {
    const scale = Math.min(size / crest.width, size / crest.height);
    const w = crest.width * scale;
    const h = crest.height * scale;

    ctx.drawImage(crest, x + (size - w) / 2, y + (size - h) / 2, w, h);
}
