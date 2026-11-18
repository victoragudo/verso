import { formatList, t } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { RotateCcw, Undo2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { ChoiceCard } from '@/components/verso/easy/choice-card';
import { ColorField } from '@/components/verso/easy/color-field';
import { ContrastWarnings } from '@/components/verso/easy/contrast-warnings';
import { LogoDrop } from '@/components/verso/easy/logo-drop';
import type { LogoInfo } from '@/components/verso/easy/logo-drop';
import { PaletteSwatch } from '@/components/verso/easy/palette-swatch';
import { StylePreview } from '@/components/verso/easy/style-preview';
import { TOUCH_TARGET } from '@/components/verso/easy/touch';
import { uploadMedia } from '@/components/verso/media/media-api';
import { PageHeader, SectionCard } from '@/components/verso/ui';
import { fixWarning, reviewPalette } from '@/lib/easy/contrast';
import type { ColorRole, ContrastWarning, Palette } from '@/lib/easy/contrast';
import { loadFontFaces } from '@/lib/easy/font-faces';
import type { FontFaceInfo } from '@/lib/easy/font-faces';
import {
    canUndoChoice,
    pickChoice,
    settleChoice,
    startChoices,
    undoChoice,
} from '@/lib/easy/choice-history';
import { readImagePixels } from '@/lib/easy/image-pixels';
import {
    extractColors,
    palettesFromColors,
    pickRoles,
} from '@/lib/easy/palette';
import type { LogoColor, PaletteChoice } from '@/lib/easy/palette';
import {
    firstValidationMessage,
    plainVisitErrors,
} from '@/lib/easy/plain-errors';
import {
    choiceTokens,
    previewSrc,
    suggestedFontPair,
} from '@/lib/easy/style-choice';
import type {
    ColorRoleInfo,
    FontPairInfo,
    StylePresetInfo,
} from '@/lib/easy/style-choice';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { style as stylePage } from '@/routes/admin/easy';
import { update } from '@/routes/admin/easy/style';
import type { SitePreviewWidths } from '@/types';

type Props = {
    theme: { name: string; slug: string };
    roles: ColorRoleInfo[];
    colors: Palette;
    presets: StylePresetInfo[];
    fontPairs: FontPairInfo[];
    fontFaces: FontFaceInfo[];
    preset: string | null;
    fontPair: string | null;
    logo: LogoInfo | null;
    logoSupported: boolean;
    canUpload: boolean;
    previewUrl: string;
    previewWidths: SitePreviewWidths;
    previewDelay: number;
    undoSteps?: number;
    palette: { sampleSize: number; maxColors: number };
};

// Everything the person picks on the page. Each change is one "Undo" step.
type Picks = {
    colors: Palette;
    paletteKey: string | null;
    presetKey: string | null;
    fontPairKey: string | null;
    fontPairChosen: boolean;
    logo: LogoInfo | null;
    logoChanged: boolean;
};

const DEFAULT_UNDO_STEPS = 50;

// Picture types the media library takes and a browser can read colors from.
const LOGO_TYPES = 'image/png,image/jpeg,image/webp,image/gif,image/avif';

// Key of the palette card that keeps the colors the site has now.
const CURRENT = 'current';

// "Make it yours": logo, palette, fonts and style, with the real site changing live next to the controls.
export default function MakeItYours(props: Props) {
    const errors =
        usePage<{ errors?: Record<string, string> }>().props.errors ?? {};
    const roleKeys = useMemo(
        () => props.roles.map((role) => role.role),
        [props.roles],
    );
    const saved: Picks = {
        colors: props.colors,
        paletteKey: CURRENT,
        presetKey: props.preset,
        fontPairKey: props.fontPair,
        fontPairChosen: false,
        logo: props.logo,
        logoChanged: false,
    };
    const [history, setHistory] = useState(() => startChoices(saved));
    const { colors, paletteKey, presetKey, fontPairKey, logo, logoChanged } =
        history.current;
    const [announcement, setAnnouncement] = useState('');
    const [saveError, setSaveError] = useState<string | null>(null);
    const [fromLogo, setFromLogo] = useState(false);
    const [palettes, setPalettes] = useState<PaletteChoice[]>(() =>
        palettesFromColors([
            { hex: props.colors.accent ?? '#2e5e4e', share: 1 },
        ]),
    );
    const [logoStatus, setLogoStatus] = useState('');
    const [busy, setBusy] = useState(false);
    const [saving, setSaving] = useState(false);
    const [frameSrc, setFrameSrc] = useState<string | null>(null);
    const firstLogo = props.logo?.url;
    const limit = props.undoSteps ?? DEFAULT_UNDO_STEPS;

    // Records one pick. Nothing is saved until "Save my look".
    // Changes with the same mergeKey in a row, like typing one color code, are one Undo step.
    const change = (
        update: (current: Picks) => Partial<Picks>,
        mergeKey: string | null = null,
    ) =>
        setHistory((current) =>
            pickChoice(
                current,
                { ...current.current, ...update(current.current) },
                limit,
                mergeKey,
            ),
        );

    const settle = () => setHistory(settleChoice);

    const undoPick = () => {
        setHistory(undoChoice);
        setAnnouncement(t('Last pick undone. The preview shows it.'));
    };

    const backToSaved = () => {
        change(() => saved);
        setAnnouncement(
            t(
                'Back to the colors, fonts and style you had. Press Undo to get your picks back.',
            ),
        );
    };

    const preset = props.presets.find((item) => item.key === presetKey) ?? null;
    const fontPair =
        props.fontPairs.find((item) => item.key === fontPairKey) ?? null;
    const shown = pickRoles(colors, roleKeys);
    const warnings = reviewPalette(shown);
    const src = previewSrc(
        props.previewUrl,
        choiceTokens({ colors: shown, preset, fontPair }, props.roles),
        logoChanged ? (logo?.id ?? null) : undefined,
    );

    useEffect(() => loadFontFaces(props.fontFaces), [props.fontFaces]);

    useEffect(() => {
        const timer = window.setTimeout(
            () => setFrameSrc(src),
            props.previewDelay,
        );

        return () => window.clearTimeout(timer);
    }, [src, props.previewDelay]);

    const showLogoColors = (found: LogoColor[], select: boolean) => {
        if (found.length === 0) {
            return;
        }

        const choices = palettesFromColors(found);
        setPalettes(choices);
        setFromLogo(true);

        if (select) {
            change(() => ({
                colors: choices[0].colors,
                paletteKey: choices[0].key,
            }));
        }
    };

    const readColors = async (source: string) =>
        extractColors(
            await readImagePixels(source, props.palette.sampleSize),
            props.palette.maxColors,
        );
    const readFirstLogo = useRef(() => {
        if (firstLogo) {
            readColors(firstLogo)
                .then((found) => showLogoColors(found, false))
                .catch(() => undefined);
        }
    });

    useEffect(() => readFirstLogo.current(), []);

    const takeLogo = async (file: File) => {
        if (!LOGO_TYPES.split(',').includes(file.type)) {
            setLogoStatus(
                t(
                    'This file is not a picture we can use. Pick a PNG or JPG file.',
                ),
            );

            return;
        }

        setBusy(true);
        const local = URL.createObjectURL(file);

        try {
            const found = await readColors(local);
            showLogoColors(found, true);
            setLogoStatus(
                found.length > 0
                    ? t(
                          '{count, plural, one {We found # color in your logo and made palettes from them.} other {We found # colors in your logo and made palettes from them.}}',
                          { count: found.length },
                      )
                    : t(
                          'We could not find colors in this picture. Pick a palette below.',
                      ),
            );
        } catch {
            setLogoStatus(
                t(
                    'We could not read the colors of this picture. Pick a palette below.',
                ),
            );
        }

        if (props.canUpload) {
            try {
                const uploaded = await uploadMedia(file);
                change(() => ({
                    logo: {
                        id: uploaded.id,
                        url: uploaded.url,
                        filename: uploaded.filename,
                    },
                    logoChanged: true,
                }));
            } catch (error) {
                setLogoStatus(
                    error instanceof Error
                        ? t('The logo was not uploaded: {reason}', {
                              reason: error.message,
                          })
                        : t('The logo was not uploaded.'),
                );
            }
        }

        URL.revokeObjectURL(local);
        setBusy(false);
    };

    const removeLogo = () => {
        change(() => ({ logo: null, logoChanged: true }));
        setLogoStatus(t('The logo is removed. Save to keep this change.'));
    };

    const pickPalette = (key: string) =>
        change((current) => ({
            paletteKey: key,
            colors:
                key === CURRENT
                    ? props.colors
                    : (palettes.find((item) => item.key === key)?.colors ??
                      current.colors),
        }));

    const setColor = (role: ColorRole, value: string) =>
        change(
            (current) => ({
                colors: { ...current.colors, [role]: value },
                paletteKey: null,
            }),
            `color:${role}`,
        );

    const fix = (warning: ContrastWarning) =>
        change((current) => ({
            colors: fixWarning(current.colors, warning),
            paletteKey: null,
        }));

    const pickFontPair = (key: string) =>
        change(() => ({ fontPairKey: key, fontPairChosen: true }));

    // A style brings its own fonts, but never undoes a font pair the person picked.
    const pickPreset = (key: string) =>
        change((current) => {
            const suggested = suggestedFontPair(
                props.presets.find((item) => item.key === key),
                props.fontPairs,
                current.fontPairChosen,
            );

            return {
                presetKey: key,
                ...(suggested === null ? {} : { fontPairKey: suggested }),
            };
        });

    const save = (event: FormEvent) => {
        event.preventDefault();
        setSaveError(null);
        const action = { retry: t('Save my look') };

        router.put(
            update.url(),
            {
                colors: shown,
                preset: presetKey,
                fontPair: fontPairKey,
                ...(logoChanged ? { logo: logo?.id ?? null } : {}),
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onSuccess: () => setHistory(startChoices(history.current)),
                onError: (found) =>
                    setSaveError(
                        firstValidationMessage(found, 'style') ??
                            t(
                                'Some picks need a change. Look at the messages above, then press Save my look.',
                            ),
                    ),
                ...plainVisitErrors(setSaveError, action),
                onFinish: () => setSaving(false),
            },
        );
    };

    const paletteCards = [
        {
            key: CURRENT,
            label: t('Your colors now'),
            description: t('Keep the colors the site has today.'),
            colors: props.colors,
        },
        ...(fromLogo ? palettes : palettes.map(withoutLogo)),
    ];

    return (
        <>
            <Head title={t('Make it yours')} />
            <PageHeader
                icon="palette"
                title={t('Make it yours')}
                description={t(
                    'Add your logo, pick colors, fonts and a style. The preview shows your real site with {theme} as you go. Nothing changes for visitors until you save.',
                    { theme: props.theme.name },
                )}
            />

            <div className="grid items-start gap-6 @5xl/page:grid-cols-[minmax(24rem,32rem)_minmax(0,1fr)] @[120rem]/page:grid-cols-[minmax(28rem,40rem)_minmax(0,1fr)] landscape:@2xl/page:@max-5xl/page:grid-cols-[minmax(20rem,1fr)_minmax(0,1.25fr)]">
                <form
                    onSubmit={save}
                    aria-busy={saving}
                    className="grid min-w-0 gap-6"
                >
                    {props.logoSupported && (
                        <SectionCard title={t('Your logo')} icon="media">
                            <LogoDrop
                                logo={logo}
                                busy={busy}
                                status={logoStatus}
                                error={errors.logo}
                                accept={LOGO_TYPES}
                                onFile={(file) => void takeLogo(file)}
                                onRemove={removeLogo}
                            />
                        </SectionCard>
                    )}

                    {props.roles.length > 0 && (
                        <SectionCard
                            id="colors"
                            className="scroll-mt-20"
                            title={t('Colors')}
                            icon="palette"
                            description={t(
                                'Pick a palette, then change any color if you like.',
                            )}
                        >
                            <fieldset>
                                <legend className="sr-only">
                                    {t('Color palette')}
                                </legend>
                                <div className="auto-fit-grid gap-3 [--grid-min:9.5rem]">
                                    {paletteCards.map((choice) => (
                                        <ChoiceCard
                                            key={choice.key}
                                            name="palette"
                                            value={choice.key}
                                            label={choice.label}
                                            description={choice.description}
                                            checked={paletteKey === choice.key}
                                            onSelect={pickPalette}
                                        >
                                            <PaletteSwatch
                                                colors={pickRoles(
                                                    choice.colors,
                                                    roleKeys,
                                                )}
                                            />
                                        </ChoiceCard>
                                    ))}
                                </div>
                            </fieldset>

                            <fieldset className="mt-6">
                                <legend className="mb-3 text-sm font-semibold">
                                    {t('Each color')}
                                </legend>
                                <div className="auto-grid gap-4 [--grid-min:13rem]">
                                    {props.roles.map((role) => (
                                        <ColorField
                                            key={role.role}
                                            label={role.label}
                                            value={
                                                colors[role.role] ?? '#000000'
                                            }
                                            error={
                                                errors[`colors.${role.role}`]
                                            }
                                            onChange={(value) =>
                                                setColor(role.role, value)
                                            }
                                            onDone={settle}
                                        />
                                    ))}
                                </div>
                            </fieldset>

                            <div className="mt-6">
                                <ContrastWarnings
                                    warnings={warnings}
                                    onFix={fix}
                                />
                            </div>
                        </SectionCard>
                    )}

                    {props.fontPairs.length > 0 && (
                        <SectionCard
                            title={t('Fonts')}
                            icon="posts"
                            description={t(
                                'Each card is written in its own fonts.',
                            )}
                        >
                            <fieldset>
                                <legend className="sr-only">
                                    {t('Font pair')}
                                </legend>
                                <div className="auto-fit-grid gap-3 [--grid-min:14rem]">
                                    {props.fontPairs.map((pair) => (
                                        <ChoiceCard
                                            key={pair.key}
                                            name="font-pair"
                                            value={pair.key}
                                            label={pair.label}
                                            description={formatList([
                                                pair.headingLabel,
                                                pair.bodyLabel,
                                            ])}
                                            checked={fontPairKey === pair.key}
                                            onSelect={pickFontPair}
                                        >
                                            <span
                                                aria-hidden="true"
                                                className="grid gap-1 rounded-md bg-muted px-3 py-2.5"
                                            >
                                                <span
                                                    className="text-xl leading-tight font-semibold"
                                                    style={{
                                                        fontFamily:
                                                            pair.headingStack,
                                                    }}
                                                >
                                                    {pair.label}
                                                </span>
                                                <span
                                                    className="text-sm text-muted-foreground"
                                                    style={{
                                                        fontFamily:
                                                            pair.bodyStack,
                                                    }}
                                                >
                                                    {pair.sample}
                                                </span>
                                            </span>
                                        </ChoiceCard>
                                    ))}
                                </div>
                            </fieldset>
                            <InputError message={errors.fontPair} />
                        </SectionCard>
                    )}

                    {props.presets.length > 0 && (
                        <SectionCard
                            title={t('Style')}
                            icon="customize"
                            description={t(
                                'Corners and spacing for the whole site.',
                            )}
                        >
                            <fieldset>
                                <legend className="sr-only">
                                    {t('Style')}
                                </legend>
                                <div className="auto-fit-grid gap-3 [--grid-min:11rem]">
                                    {props.presets.map((item) => (
                                        <ChoiceCard
                                            key={item.key}
                                            name="style-preset"
                                            value={item.key}
                                            label={item.label}
                                            description={item.description}
                                            checked={presetKey === item.key}
                                            onSelect={pickPreset}
                                        >
                                            <PresetDrawing preset={item} />
                                        </ChoiceCard>
                                    ))}
                                </div>
                            </fieldset>
                            <InputError message={errors.preset} />
                        </SectionCard>
                    )}

                    <InputError message={errors.style} />
                    {saveError && (
                        <p
                            role="alert"
                            className="text-sm font-medium text-danger-text"
                            data-test="style-error"
                        >
                            {saveError}
                        </p>
                    )}
                    <p role="status" className="sr-only">
                        {announcement}
                    </p>

                    <div className="sticky bottom-0 z-10 flex flex-wrap items-center gap-2 border-t bg-background/95 py-3 backdrop-blur-sm">
                        <div className="flex flex-wrap gap-2">
                            <Button
                                asChild
                                variant="ghost"
                                className={TOUCH_TARGET}
                            >
                                <Link href={dashboard().url}>
                                    {t('Skip for now')}
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className={TOUCH_TARGET}
                                aria-disabled={!canUndoChoice(history)}
                                onClick={() =>
                                    canUndoChoice(history) && undoPick()
                                }
                            >
                                <Undo2 aria-hidden="true" />
                                {t('Undo')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className={TOUCH_TARGET}
                                onClick={backToSaved}
                            >
                                <RotateCcw aria-hidden="true" />
                                {t('Back to the colors I had')}
                            </Button>
                        </div>
                        <Button
                            type="submit"
                            disabled={saving || busy}
                            className={cn(TOUCH_TARGET, 'ms-auto')}
                        >
                            {saving ? t('Saving') : t('Save my look')}
                        </Button>
                    </div>
                </form>

                <div className="min-w-0 @5xl/page:sticky @5xl/page:top-16 landscape:@2xl/page:sticky landscape:@2xl/page:top-16">
                    <StylePreview
                        src={frameSrc ?? src}
                        widths={props.previewWidths}
                    />
                </div>
            </div>
        </>
    );
}

// Before a logo is read, palettes come from the accent color, so the main one gets a plain name.
function withoutLogo(choice: PaletteChoice): PaletteChoice {
    return choice.key === 'light'
        ? {
              ...choice,
              label: t('Light'),
              description: t(
                  'A light page with your accent color for links and buttons.',
              ),
          }
        : choice;
}

// A small drawing of a preset: a card and a button with its corners and gaps.
function PresetDrawing({ preset }: { preset: StylePresetInfo }) {
    const radius = preset.tokens['radius.base'] ?? '6px';
    const gap = preset.tokens['spacing.small'] ?? '0.5rem';

    return (
        <span
            aria-hidden="true"
            className="flex items-end rounded-md bg-muted p-3"
            style={{ gap }}
        >
            <span
                className="block h-10 flex-1 border bg-card"
                style={{ borderRadius: radius }}
            />
            <span
                className="block h-6 w-12 bg-brand"
                style={{ borderRadius: radius }}
            />
        </span>
    );
}

MakeItYours.layout = () => ({
    breadcrumbs: [{ title: t('Make it yours'), href: stylePage() }],
});
