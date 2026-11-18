import { Eraser, ImageIcon, Plus, Trash2 } from 'lucide-react';
import { useId, useState } from 'react';
import type { BuilderStyleProperty, InspectorPanelProps } from '@/builder';
import type { StyleValue } from '@/builder/styles';
import {
    focalCss,
    gradientParts,
    gradientText,
    imageCss,
    MAX_GRADIENT_STOPS,
} from '@/builder/style-values';
import type { GradientParts } from '@/builder/style-values';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { MediaPicker } from '@/components/verso/media/media-picker';
import { labelInSentence } from '@/components/verso/editor/blocks/shared';
import { t } from '@/lib/i18n';

// Props every Styles panel control gets. "design" is the whole block design, for controls that look at other values.
export type StyleControlProps = {
    property: BuilderStyleProperty;
    value: StyleValue | undefined;
    inherited: boolean;
    limits: InspectorPanelProps['limits'];
    onChange: (value: StyleValue | undefined) => void;
    picture?: string;
};

// Where a gradient starts when people add one.
const STARTING_GRADIENT: GradientParts = {
    kind: 'linear',
    angle: 180,
    stops: [
        { color: '#00000000', at: 0 },
        { color: '#000000b3', at: 100 },
    ],
};

function colorInputValue(color: string): string {
    return /^#[0-9a-f]{6}/i.test(color) ? color.slice(0, 7) : '#000000';
}

// Builds a linear or radial gradient from two to five colors, with the angle and the place of each color.
export function GradientControl({
    property,
    value,
    inherited,
    onChange,
}: StyleControlProps) {
    const id = useId();
    const parts = inherited ? null : gradientParts(value);

    if (parts === null) {
        return (
            <div className="grid gap-1">
                <span className="text-xs font-medium">{t(property.label)}</span>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            onChange(gradientText(STARTING_GRADIENT))
                        }
                    >
                        <Plus />
                        {t('Add a gradient')}
                    </Button>
                    {inherited && value !== undefined && (
                        <span className="text-xs text-muted-foreground">
                            {t('Same as bigger screens')}
                        </span>
                    )}
                </div>
            </div>
        );
    }

    const change = (next: Partial<GradientParts>) =>
        onChange(gradientText({ ...parts, ...next }));
    const setStop = (index: number, stop: GradientParts['stops'][number]) =>
        change({
            stops: parts.stops.map((item, at) => (at === index ? stop : item)),
        });

    return (
        <fieldset className="grid gap-2" data-test="gradient-control">
            <legend className="mb-1 text-xs font-medium">
                {t(property.label)}
            </legend>
            <div
                aria-hidden="true"
                className="h-8 rounded border"
                style={{ backgroundImage: previewGradient(parts) }}
            />
            <div className="flex flex-wrap items-end gap-2">
                <div className="grid gap-1">
                    <Label htmlFor={`${id}-kind`} className="text-xs">
                        {t('Kind')}
                    </Label>
                    <OptionSelect
                        id={`${id}-kind`}
                        size="sm"
                        value={parts.kind}
                        onValueChange={(kind) =>
                            change({ kind: kind as GradientParts['kind'] })
                        }
                        options={[
                            { value: 'linear', label: t('Straight') },
                            { value: 'radial', label: t('Round') },
                        ]}
                    />
                </div>
                {parts.kind === 'linear' && (
                    <div className="grid gap-1">
                        <Label htmlFor={`${id}-angle`} className="text-xs">
                            {t('Angle')}
                        </Label>
                        <Input
                            id={`${id}-angle`}
                            type="number"
                            min={0}
                            max={360}
                            value={parts.angle}
                            onChange={(event) =>
                                change({
                                    angle: Math.min(
                                        360,
                                        Math.max(
                                            0,
                                            Number(event.target.value) || 0,
                                        ),
                                    ),
                                })
                            }
                            className="h-8 w-20"
                        />
                    </div>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="ms-auto h-8"
                    onClick={() => onChange(undefined)}
                >
                    <Eraser />
                    {t('Remove gradient')}
                </Button>
            </div>
            <ol className="grid gap-1.5">
                {parts.stops.map((stop, index) => (
                    <li key={index} className="flex items-center gap-1.5">
                        <input
                            type="color"
                            aria-label={t('Color {number}', {
                                number: index + 1,
                            })}
                            value={colorInputValue(stop.color)}
                            onChange={(event) =>
                                setStop(index, {
                                    ...stop,
                                    color:
                                        event.target.value +
                                        (/^#[0-9a-f]{8}$/i.test(stop.color)
                                            ? stop.color.slice(7)
                                            : ''),
                                })
                            }
                            className="h-8 w-10 cursor-pointer rounded border bg-transparent p-0.5"
                        />
                        <Input
                            aria-label={t('Color {number}', {
                                number: index + 1,
                            })}
                            value={stop.color}
                            onChange={(event) =>
                                setStop(index, {
                                    ...stop,
                                    color: event.target.value.trim(),
                                })
                            }
                            className="h-8 min-w-0 flex-1 font-mono text-xs"
                        />
                        <Input
                            type="number"
                            min={0}
                            max={100}
                            aria-label={t(
                                'Place of color {number} in percent',
                                {
                                    number: index + 1,
                                },
                            )}
                            value={stop.at}
                            onChange={(event) =>
                                setStop(index, {
                                    ...stop,
                                    at: Math.min(
                                        100,
                                        Math.max(
                                            0,
                                            Number(event.target.value) || 0,
                                        ),
                                    ),
                                })
                            }
                            className="h-8 w-16"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 px-2"
                            disabled={parts.stops.length <= 2}
                            aria-label={t('Remove color {number}', {
                                number: index + 1,
                            })}
                            onClick={() =>
                                change({
                                    stops: parts.stops.filter(
                                        (_, at) => at !== index,
                                    ),
                                })
                            }
                        >
                            <Trash2 />
                        </Button>
                    </li>
                ))}
            </ol>
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={parts.stops.length >= MAX_GRADIENT_STOPS}
                    onClick={() =>
                        change({
                            stops: [
                                ...parts.stops,
                                { color: '#ffffff', at: 100 },
                            ],
                        })
                    }
                >
                    <Plus />
                    {t('Add a color')}
                </Button>
            </div>
        </fieldset>
    );
}

// The gradient as CSS for the small preview in the panel. Tokens show as grey.
function previewGradient(parts: GradientParts): string {
    const stops = parts.stops.map(
        (stop) =>
            `${stop.color.startsWith('var:') ? '#9ca3af' : stop.color} ${stop.at}%`,
    );

    return parts.kind === 'linear'
        ? `linear-gradient(${parts.angle}deg, ${stops.join(', ')})`
        : `radial-gradient(circle, ${stops.join(', ')})`;
}

// A background picture from the media library.
export function ImageControl({
    property,
    value,
    inherited,
    onChange,
}: StyleControlProps) {
    const [picking, setPicking] = useState(false);
    const current =
        !inherited && typeof value === 'string' && imageCss(value) !== null
            ? value
            : undefined;

    return (
        <div className="grid gap-1.5" data-test="image-control">
            <span className="text-xs font-medium">{t(property.label)}</span>
            {current && (
                <img
                    src={current}
                    alt=""
                    className="aspect-video w-full rounded border object-cover"
                />
            )}
            <div className="flex flex-wrap gap-1.5">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setPicking(true)}
                >
                    <ImageIcon />
                    {current ? t('Replace picture') : t('Choose a picture')}
                </Button>
                {current && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onChange(undefined)}
                        aria-label={t('Clear {property}', {
                            property: labelInSentence(property.label),
                        })}
                    >
                        <Eraser />
                        {t('Remove')}
                    </Button>
                )}
            </div>
            {inherited && value !== undefined && (
                <span className="text-xs text-muted-foreground">
                    {t('Same as bigger screens')}
                </span>
            )}
            <MediaPicker
                open={picking}
                onOpenChange={setPicking}
                accept="image"
                onSelect={(media) =>
                    onChange(
                        imageCss(media.url) === null ? undefined : media.url,
                    )
                }
            />
        </div>
    );
}

// The point of the picture that stays in view when the box crops it: click the picture or type percents.
export function FocalControl({
    property,
    value,
    inherited,
    onChange,
    picture,
}: StyleControlProps) {
    const id = useId();
    const current = typeof value === 'string' ? focalCss(value) : null;
    const [left, top] = (current ?? '50% 50%')
        .split(' ')
        .map((part) => parseInt(part, 10));
    const set = (x: number, y: number) =>
        onChange(
            `${Math.round(Math.min(100, Math.max(0, x)))}% ${Math.round(Math.min(100, Math.max(0, y)))}%`,
        );

    return (
        <fieldset className="grid gap-1.5" data-test="focal-control">
            <legend className="mb-1 text-xs font-medium">
                {t(property.label)}
            </legend>
            {picture && (
                <button
                    type="button"
                    aria-label={t('Pick the focal point on the picture')}
                    className="relative block w-full overflow-hidden rounded border transition-colors hover:border-ring/60"
                    onClick={(event) => {
                        const box = event.currentTarget.getBoundingClientRect();
                        set(
                            ((event.clientX - box.left) / box.width) * 100,
                            ((event.clientY - box.top) / box.height) * 100,
                        );
                    }}
                >
                    <img src={picture} alt="" className="block w-full" />
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-black/40 shadow"
                        style={{ left: `${left}%`, top: `${top}%` }}
                    />
                </button>
            )}
            <div className="flex items-end gap-2">
                <div className="grid gap-1">
                    <Label htmlFor={`${id}-x`} className="text-xs">
                        {t('From the left (%)')}
                    </Label>
                    <Input
                        id={`${id}-x`}
                        type="number"
                        min={0}
                        max={100}
                        value={left}
                        onChange={(event) =>
                            set(Number(event.target.value) || 0, top)
                        }
                        className="h-8 w-20"
                    />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`${id}-y`} className="text-xs">
                        {t('From the top (%)')}
                    </Label>
                    <Input
                        id={`${id}-y`}
                        type="number"
                        min={0}
                        max={100}
                        value={top}
                        onChange={(event) =>
                            set(left, Number(event.target.value) || 0)
                        }
                        className="h-8 w-20"
                    />
                </div>
                {current !== null && !inherited && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8"
                        onClick={() => onChange(undefined)}
                    >
                        <Eraser />
                        {t('Reset')}
                    </Button>
                )}
            </div>
        </fieldset>
    );
}
