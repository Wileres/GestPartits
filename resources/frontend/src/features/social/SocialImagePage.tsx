import { Download, ImagePlus, RotateCcw, RotateCw, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type ChangeEvent, type KeyboardEvent, type PointerEvent } from 'react';
import { toast } from 'sonner';
import { FormField } from '@/components/FormField';
import { MatchdaySelector } from '@/components/MatchdaySelector';
import { NativeSelect } from '@/components/NativeSelect';
import { PageHeader } from '@/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useMatches } from '@/features/matches/useMatches';
import { useMatchdaySelection } from '@/hooks/useMatchdaySelection';
import { usePublicBranding } from '@/hooks/usePublicBranding';
import { t } from '@/i18n/ca';
import { drawPost, FONT_FAMILY } from './drawPost';
import { clampFraming, DEFAULT_FRAMING, MAX_ZOOM, MIN_ZOOM, NO_ADJUSTMENTS, POST_SIZES, type Adjustments, type Framing, type PostFormat, type Rotation } from './postGeometry';

const MAX_FILE_BYTES = 25 * 1024 * 1024;
const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];
/** Blau viu, perquè es llegeixi bé sobre el difuminat negre. */
const DEFAULT_ACCENT = '#2f7bff';
const KEY_STEP = 24;

type Bitmap = HTMLImageElement;

function loadImage(src: string): Promise<Bitmap> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('image'));
        image.src = src;
    });
}

async function loadFile(file: File): Promise<Bitmap> {
    const url = URL.createObjectURL(file);
    try {
        return await loadImage(url);
    } finally {
        URL.revokeObjectURL(url);
    }
}

const isAcceptable = (file: File) => ACCEPTED.includes(file.type) && file.size <= MAX_FILE_BYTES;

/**
 * Editor d'imatges per a xarxes socials: una foto, el resultat del partit i un difuminat negre a la part inferior.
 * Tot passa al navegador (llenç): la foto no es puja al servidor.
 */
export function SocialImagePage() {
    const selection = useMatchdaySelection();
    const matches = useMatches(selection.matchday?.id ?? null);
    const branding = usePublicBranding();
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drag = useRef<{ x: number; y: number; pointer: number } | null>(null);

    const [photo, setPhoto] = useState<Bitmap | null>(null);
    const [photoName, setPhotoName] = useState('');
    const [loadedCrest, setLoadedCrest] = useState<{ url: string; image: Bitmap } | null>(null);
    const [opponentCrest, setOpponentCrest] = useState<Bitmap | null>(null);
    const [format, setFormat] = useState<PostFormat>('portrait');
    const [framing, setFraming] = useState<Framing>(DEFAULT_FRAMING);
    const [adjustments, setAdjustments] = useState<Adjustments>(NO_ADJUSTMENTS);
    const [team, setTeam] = useState('');
    const [scoreTeam, setScoreTeam] = useState('');
    const [scoreOpponent, setScoreOpponent] = useState('');
    const [opponent, setOpponent] = useState('');
    const [competition, setCompetition] = useState('');
    const [accent, setAccent] = useState(DEFAULT_ACCENT);
    const [fontsReady, setFontsReady] = useState(() => document.fonts === undefined);

    const size = POST_SIZES[format];
    const withResult = useMemo(() => (matches.data ?? []).filter((match) => match.has_result), [matches.data]);
    const logoUrl = branding.data?.logo_url ?? null;

    // L'escut del club (el mateix per a tots els equips), si n'hi ha.
    useEffect(() => {
        let active = true;
        if (logoUrl !== null) {
            loadImage(logoUrl).then(
                (image) => active && setLoadedCrest({ url: logoUrl, image }),
                () => undefined,
            );
        }

        return () => {
            active = false;
        };
    }, [logoUrl]);
    const teamCrest = loadedCrest !== null && loadedCrest.url === logoUrl ? loadedCrest.image : null;

    // La tipografia s'ha de carregar abans de dibuixar el text.
    useEffect(() => {
        let active = true;
        void document.fonts
            ?.load(`900 100px ${FONT_FAMILY}`)
            .then(() => document.fonts.load(`700 40px ${FONT_FAMILY}`))
            .finally(() => active && setFontsReady(true));

        return () => {
            active = false;
        };
    }, []);

    // Dibuixa la publicació cada cop que canvia alguna cosa.
    useEffect(() => {
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext('2d') ?? null;
        if (canvas === null || ctx === null || photo === null) return;

        drawPost(ctx, {
            width: size.width,
            height: size.height,
            photo,
            framing,
            adjustments,
            text: { team, scoreTeam, scoreOpponent, opponent, competition },
            accent,
            teamCrest,
            opponentCrest,
        });
    }, [photo, framing, adjustments, size, team, scoreTeam, scoreOpponent, opponent, competition, accent, teamCrest, opponentCrest, fontsReady]);

    const frame = useCallback(
        (next: Framing, image: Bitmap | null = photo, canvasSize = size) =>
            image === null ? next : clampFraming(next, image.width, image.height, canvasSize.width, canvasSize.height),
        [photo, size],
    );

    const choosePhoto = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (file === undefined) return;
        if (!isAcceptable(file)) {
            toast.error(t('social.invalidFile'));

            return;
        }
        try {
            setPhoto(await loadFile(file));
            setPhotoName(file.name);
            setFraming(DEFAULT_FRAMING);
        } catch {
            toast.error(t('social.loadError'));
        }
    };

    const chooseOpponentCrest = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (file === undefined) return;
        if (!isAcceptable(file)) {
            toast.error(t('social.invalidFile'));

            return;
        }
        try {
            setOpponentCrest(await loadFile(file));
        } catch {
            toast.error(t('social.loadError'));
        }
    };

    const changeFormat = (next: PostFormat) => {
        setFormat(next);
        setFraming((current) => frame(current, photo, POST_SIZES[next]));
    };

    const rotate = (delta: 90 | -90) => setFraming((current) => frame({ ...current, rotation: ((current.rotation + delta + 360) % 360) as Rotation, offsetX: 0, offsetY: 0 }));

    const onPointerDown = (event: PointerEvent<HTMLCanvasElement>) => {
        if (photo === null) return;
        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = { x: event.clientX, y: event.clientY, pointer: event.pointerId };
    };

    const onPointerMove = (event: PointerEvent<HTMLCanvasElement>) => {
        const start = drag.current;
        if (start === null || start.pointer !== event.pointerId) return;
        // Els píxels de la pantalla es converteixen en píxels del llenç.
        const ratio = size.width / event.currentTarget.getBoundingClientRect().width;
        const dx = (event.clientX - start.x) * ratio;
        const dy = (event.clientY - start.y) * ratio;
        drag.current = { ...start, x: event.clientX, y: event.clientY };
        setFraming((current) => frame({ ...current, offsetX: current.offsetX + dx, offsetY: current.offsetY + dy }));
    };

    const onKeyDown = (event: KeyboardEvent<HTMLCanvasElement>) => {
        const moves: Record<string, [number, number]> = { ArrowLeft: [-KEY_STEP, 0], ArrowRight: [KEY_STEP, 0], ArrowUp: [0, -KEY_STEP], ArrowDown: [0, KEY_STEP] };
        const move = moves[event.key];
        if (move === undefined || photo === null) return;
        event.preventDefault();
        setFraming((current) => frame({ ...current, offsetX: current.offsetX + move[0], offsetY: current.offsetY + move[1] }));
    };

    const loadMatch = (value: string) => {
        const match = withResult.find((m) => String(m.id) === value);
        if (match === undefined) return;
        setTeam(match.team.name);
        setScoreTeam(String(match.score_team ?? ''));
        setScoreOpponent(String(match.score_opponent ?? ''));
        setOpponent(match.opponent ?? '');
    };

    const download = () => {
        const canvas = canvasRef.current;
        if (canvas === null || photo === null) return;
        canvas.toBlob((blob) => {
            if (blob === null) return;
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `resultat-${(team || 'partit').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}.png`;
            link.click();
            URL.revokeObjectURL(url);
            toast.success(t('social.downloaded'));
        }, 'image/png');
    };

    const adjust = (key: keyof Adjustments) => (event: ChangeEvent<HTMLInputElement>) => setAdjustments((current) => ({ ...current, [key]: Number(event.target.value) }));
    const score = `${scoreTeam.trim()} - ${scoreOpponent.trim()}`;

    return (
        <>
            <PageHeader title={t('social.title')} description={t('social.subtitle')} />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
                <div className="lg:sticky lg:top-6 lg:self-start">
                    <Card className="gap-3 p-3">
                        <p className="text-sm font-medium">{t('social.preview')}</p>
                        <div className="overflow-hidden rounded-lg border bg-muted" style={{ aspectRatio: `${size.width} / ${size.height}` }}>
                            {photo === null ? (
                                <p className="flex h-full items-center justify-center p-6 text-center text-sm text-muted-foreground">{t('social.noPhoto')}</p>
                            ) : (
                                <canvas
                                    ref={canvasRef}
                                    width={size.width}
                                    height={size.height}
                                    tabIndex={0}
                                    aria-label={t('social.canvasLabel', { team: team.trim(), score })}
                                    className="h-full w-full cursor-grab touch-none active:cursor-grabbing"
                                    onPointerDown={onPointerDown}
                                    onPointerMove={onPointerMove}
                                    onPointerUp={() => (drag.current = null)}
                                    onPointerCancel={() => (drag.current = null)}
                                    onKeyDown={onKeyDown}
                                />
                            )}
                        </div>
                        {photo !== null && <p className="text-xs text-muted-foreground">{t('social.framingHelp')}</p>}
                        <Button onClick={download} disabled={photo === null}>
                            <Download aria-hidden />
                            {t('social.download')}
                        </Button>
                    </Card>
                </div>

                <div className="grid gap-6">
                    <Card className="gap-4 p-4">
                        <h2 className="font-semibold">{t('social.photoSection')}</h2>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button asChild variant="outline">
                                <label className="cursor-pointer">
                                    <ImagePlus aria-hidden />
                                    {photo === null ? t('social.choosePhoto') : t('social.changePhoto')}
                                    <input type="file" accept={ACCEPTED.join(',')} className="sr-only" onChange={(e) => void choosePhoto(e)} />
                                </label>
                            </Button>
                            {photoName !== '' && <span className="min-w-0 truncate text-sm text-muted-foreground">{photoName}</span>}
                        </div>
                        <p className="text-xs text-muted-foreground">{t('social.photoHelp')}</p>

                        <fieldset className="grid gap-1.5" disabled={photo === null}>
                            <legend className="mb-1.5 text-sm font-medium">{t('social.format')}</legend>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {(['portrait', 'square'] as const).map((value) => (
                                    <label key={value} className="flex cursor-pointer items-center justify-center rounded-md border px-3 py-2 text-sm has-checked:border-primary has-checked:bg-secondary has-checked:text-primary">
                                        <input type="radio" name="post-format" className="sr-only" checked={format === value} onChange={() => changeFormat(value)} />
                                        {value === 'portrait' ? t('social.formatPortrait') : t('social.formatSquare')}
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        <FormField id="social-zoom" label={t('social.zoom')}>
                            <input
                                id="social-zoom"
                                type="range"
                                min={MIN_ZOOM}
                                max={MAX_ZOOM}
                                step={0.05}
                                value={framing.zoom}
                                disabled={photo === null}
                                className="w-full accent-primary"
                                onChange={(e) => setFraming((current) => frame({ ...current, zoom: Number(e.target.value) }))}
                            />
                        </FormField>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" disabled={photo === null} onClick={() => rotate(-90)}>
                                <RotateCcw aria-hidden />
                                {t('social.rotateLeft')}
                            </Button>
                            <Button variant="outline" size="sm" disabled={photo === null} onClick={() => rotate(90)}>
                                <RotateCw aria-hidden />
                                {t('social.rotateRight')}
                            </Button>
                            <Button variant="ghost" size="sm" disabled={photo === null} onClick={() => setFraming(DEFAULT_FRAMING)}>
                                {t('social.resetFraming')}
                            </Button>
                        </div>
                    </Card>

                    <Card className="gap-4 p-4">
                        <h2 className="font-semibold">{t('social.adjustments')}</h2>
                        {(['brightness', 'contrast', 'saturation'] as const).map((key) => (
                            <FormField key={key} id={`social-${key}`} label={t(`social.${key}`)}>
                                <input id={`social-${key}`} type="range" min={-100} max={100} step={1} value={adjustments[key]} disabled={photo === null} className="w-full accent-primary" onChange={adjust(key)} />
                            </FormField>
                        ))}
                        <div>
                            <Button variant="ghost" size="sm" disabled={photo === null} onClick={() => setAdjustments(NO_ADJUSTMENTS)}>
                                {t('social.resetAdjustments')}
                            </Button>
                        </div>
                    </Card>

                    <Card className="gap-4 p-4">
                        <h2 className="font-semibold">{t('social.resultSection')}</h2>

                        <div className="grid gap-3">
                            <MatchdaySelector selection={selection} />
                            <FormField id="social-match" label={t('social.fromMatch')} help={t('social.fromMatchHelp')}>
                                <NativeSelect id="social-match" value="" onChange={(e) => loadMatch(e.target.value)} disabled={withResult.length === 0}>
                                    <option value="">{t('social.fromMatchNone')}</option>
                                    {withResult.map((match) => (
                                        <option key={match.id} value={match.id}>
                                            {match.team.name}: {match.score_team} - {match.score_opponent} {match.opponent ?? ''}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                        </div>

                        <FormField id="social-team" label={t('social.team')}>
                            <Input id="social-team" value={team} placeholder={t('social.teamPlaceholder')} onChange={(e) => setTeam(e.target.value)} />
                        </FormField>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField id="social-score-team" label={t('social.scoreTeam')}>
                                <Input id="social-score-team" inputMode="numeric" maxLength={2} value={scoreTeam} onChange={(e) => setScoreTeam(e.target.value.replace(/\D/g, ''))} />
                            </FormField>
                            <FormField id="social-score-opponent" label={t('social.scoreOpponent')}>
                                <Input id="social-score-opponent" inputMode="numeric" maxLength={2} value={scoreOpponent} onChange={(e) => setScoreOpponent(e.target.value.replace(/\D/g, ''))} />
                            </FormField>
                        </div>
                        <FormField id="social-opponent" label={t('social.opponent')} help={t('social.opponentHelp')}>
                            <Input id="social-opponent" value={opponent} onChange={(e) => setOpponent(e.target.value)} />
                        </FormField>
                        <FormField id="social-competition" label={t('social.competition')}>
                            <Input id="social-competition" value={competition} placeholder={t('social.competitionPlaceholder')} onChange={(e) => setCompetition(e.target.value)} />
                        </FormField>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField id="social-color" label={t('social.color')}>
                                <input id="social-color" type="color" value={accent} onChange={(e) => setAccent(e.target.value)} className="h-9 w-full cursor-pointer rounded-md border bg-transparent p-1" />
                            </FormField>
                            <FormField id="social-opponent-crest" label={t('social.opponentCrest')} help={t('social.opponentCrestHelp')}>
                                <div className="flex items-center gap-2">
                                    <Button asChild variant="outline" className="min-w-0 flex-1 justify-start">
                                        <label className="cursor-pointer">
                                            <ImagePlus aria-hidden />
                                            <span className="truncate">{opponentCrest === null ? t('social.chooseCrest') : t('social.changeCrest')}</span>
                                            <input id="social-opponent-crest" type="file" accept={ACCEPTED.join(',')} className="sr-only" onChange={(e) => void chooseOpponentCrest(e)} />
                                        </label>
                                    </Button>
                                    {opponentCrest !== null && (
                                        <Button variant="outline" size="icon" onClick={() => setOpponentCrest(null)} aria-label={t('social.removeCrest')}>
                                            <X aria-hidden />
                                        </Button>
                                    )}
                                </div>
                            </FormField>
                        </div>
                    </Card>
                </div>
            </div>
        </>
    );
}
