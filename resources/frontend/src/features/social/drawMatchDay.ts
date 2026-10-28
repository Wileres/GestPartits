import { drawPhoto, fitFont, FONT_FAMILY, type PostOptions } from './drawPost';

/** Un partit del cartell de «Dia de partit». */
export interface MatchDayLine {
    /** Nom de l'equip del club. */
    team: string;
    opponent: string;
    /** Hora d'inici («11:15»). */
    time: string;
    /** Camp o instal·lació («Camp 1»); buit si no se sap. */
    place: string;
    /** Dia («DISSABTE»): només quan els partits triats són de dies diferents. */
    day: string | null;
}

export interface MatchDayOptions extends Pick<PostOptions, 'width' | 'height' | 'photo' | 'framing' | 'adjustments' | 'accent' | 'teamCrest'> {
    /** Text de la capçalera («Dia de partit»); es pot editar. */
    header: string;
    lines: MatchDayLine[];
}

export interface MatchDayLayout {
    margin: number;
    headerSize: number;
    headerBaseline: number;
    ruleY: number;
    /** Alçada de cada partit i primera línia de base. */
    blockHeight: number;
    firstBaseline: number;
    /** On acaba el text: el difuminat arriba una mica més avall. */
    textBottom: number;
    fadeBottom: number;
}

/** El text ocupa com a màxim aquesta part de l'alçada del llenç (de dalt cap avall). */
export const TEXT_AREA = 0.66;

/** Mides del cartell: la capçalera i, a sota, els partits amb una alçada proporcional al seu nombre. */
export function matchDayLayout(width: number, height: number, count: number): MatchDayLayout {
    const margin = width * 0.07;
    const headerSize = Math.round(width * 0.09);
    const headerBaseline = height * 0.07 + headerSize;
    const ruleY = headerBaseline + width * 0.03;
    const start = ruleY + width * 0.05;
    const room = height * TEXT_AREA - start;
    const blockHeight = count === 0 ? 0 : Math.min(width * 0.17, room / count);
    const textBottom = start + blockHeight * count;

    return {
        margin,
        headerSize,
        headerBaseline,
        ruleY,
        blockHeight,
        firstBaseline: start,
        textBottom,
        fadeBottom: Math.min(height, textBottom + height * 0.12),
    };
}

/**
 * Cartell de «Dia de partit»: la foto, un difuminat negre a la part superior i, a sobre, la capçalera i, per
 * a cada partit triat, els equips, l'hora i el camp.
 */
export function drawMatchDay(ctx: CanvasRenderingContext2D, options: MatchDayOptions): void {
    const { width, height, accent, lines, teamCrest } = options;
    const layout = matchDayLayout(width, height, lines.length);
    const maxWidth = width - layout.margin * 2;

    drawPhoto(ctx, options);

    // Difuminat negre de dalt: més fosc a dalt, es dilueix cap avall.
    const gradient = ctx.createLinearGradient(0, 0, 0, layout.fadeBottom);
    gradient.addColorStop(0, 'rgba(0, 0, 0, 0.96)');
    gradient.addColorStop(0.55, 'rgba(0, 0, 0, 0.78)');
    gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, width, layout.fadeBottom);

    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'center';

    // Escut del club a la cantonada, si n'hi ha.
    if (teamCrest !== null) {
        const size = Math.round(width * 0.11);
        const scale = Math.min(size / teamCrest.width, size / teamCrest.height);
        ctx.drawImage(teamCrest, layout.margin, height * 0.04, teamCrest.width * scale, teamCrest.height * scale);
    }

    const header = options.header.trim().toUpperCase();
    if (header !== '') {
        fitFont(ctx, header, 900, layout.headerSize, maxWidth - (teamCrest === null ? 0 : width * 0.24));
        ctx.fillStyle = '#ffffff';
        ctx.fillText(header, width / 2, layout.headerBaseline);
    }

    ctx.fillStyle = accent;
    ctx.globalAlpha = 0.9;
    ctx.fillRect(layout.margin, layout.ruleY, maxWidth, Math.max(3, width * 0.003));
    ctx.globalAlpha = 1;

    const teamSize = Math.max(18, Math.round(layout.blockHeight * 0.4));
    const detailSize = Math.max(16, Math.round(layout.blockHeight * 0.3));
    const texts = lines.map((line) => ({
        names: `${line.team.trim()}  vs  ${line.opponent.trim()}`.toUpperCase(),
        detail: [line.day, line.time, line.place.trim() === '' ? null : line.place.trim()].filter((part) => part !== null && part !== '').join(' · ').toUpperCase(),
    }));
    // Una sola mida per a tots els partits (la que necessita el text més llarg), perquè quedin alineats.
    const namesFont = Math.min(teamSize, ...texts.map((text) => fitFont(ctx, text.names, 800, teamSize, maxWidth)));
    const detailFont = Math.min(detailSize, ...texts.map((text) => fitFont(ctx, text.detail, 700, detailSize, maxWidth)));

    texts.forEach((text, index) => {
        const top = layout.firstBaseline + index * layout.blockHeight;

        ctx.font = `800 ${namesFont}px ${FONT_FAMILY}`;
        ctx.fillStyle = '#ffffff';
        ctx.fillText(text.names, width / 2, top + layout.blockHeight * 0.48);

        ctx.font = `700 ${detailFont}px ${FONT_FAMILY}`;
        ctx.fillStyle = accent;
        ctx.fillText(text.detail, width / 2, top + layout.blockHeight * 0.48 + detailFont * 1.25);
    });
}
