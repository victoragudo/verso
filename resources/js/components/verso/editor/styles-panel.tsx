import { ClipboardPaste, Copy, Eraser } from 'lucide-react';
import { useId, useState, useSyncExternalStore } from 'react';
import { builder } from '@/builder';
import type { BuilderStyleProperty, InspectorPanelProps } from '@/builder';
import {
    blockDesign,
    isValidStyleValue,
    parseStyleInput,
    setStyleValue,
    styleClipboard,
    styleValue,
    VALUE_KINDS,
    withDesign,
} from '@/builder/styles';
import type { StyleValue } from '@/builder/styles';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { LengthInput } from '@/components/verso/ui/length-input';
import { labelInSentence } from '@/components/verso/editor/blocks/shared';
import { msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { stylePresets } from '@/builder/presets';
import { FocalControl, GradientControl, ImageControl } from './style-controls';
import type { StyleControlProps } from './style-controls';
import { StylePresetsBar } from './style-presets-bar';

// Plain names of the style groups, in the order they are shown.
export const STYLE_GROUP_LABELS: Record<string, string> = {
    spacing: msg('Spacing'),
    size: msg('Size'),
    layout: msg('Layout'),
    typography: msg('Text'),
    color: msg('Colors'),
    background: msg('Background picture'),
    border: msg('Border'),
    shadow: msg('Shadow'),
    position: msg('Position'),
    visibility: msg('Visibility'),
};

// Suggested sizes when the theme sets no spacing scale.
const DEFAULT_SIZES = ['0', '0.5rem', '1rem', '1.5rem', '2rem', '3rem', '4rem'];

// Short help for values people type.
const HINTS: Record<string, string> = {
    color: msg('Use a color like #1f6f5c.'),
    number: msg('Use a number from 0.1 to 4.'),
    weight: msg('Use 100, 200 ... 900.'),
    columns: msg('Use 1 to 12.'),
    zIndex: msg(
        'Use a whole number from -10 to 10000. Bigger numbers go on top.',
    ),
    token: msg('Pick a value from the list.'),
};

// Groups the style options by their group, keeping the registry order.
export function groupStyleProperties(
    properties: BuilderStyleProperty[],
): [string, BuilderStyleProperty[]][] {
    const groups = new Map<string, BuilderStyleProperty[]>();

    properties.forEach((property) => {
        groups.set(property.group, [
            ...(groups.get(property.group) ?? []),
            property,
        ]);
    });

    return [...groups.entries()];
}

function humanize(word: string): string {
    return word.replace(/-/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
}

type ControlProps = StyleControlProps;

// A text field that only saves values the server accepts, and says why when it does not.
function TextControl({
    property,
    value,
    inherited,
    limits,
    onChange,
}: ControlProps) {
    const id = useId();
    const shown = value === undefined || inherited ? '' : String(value);
    const [draft, setDraft] = useState<string | null>(null);
    const text = draft ?? shown;
    const parsed = parseStyleInput(property.kind, text);
    const invalid = text !== '' && !isValidStyleValue(property.kind, parsed);
    const suggestions =
        property.group === 'typography' && property.kind === 'token'
            ? (limits.fonts ?? []).map((font) => `var:font.${font}`)
            : [];

    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-xs">
                {t(property.label)}
            </Label>
            <Input
                id={id}
                value={text}
                inputMode={
                    ['number', 'weight', 'columns', 'zIndex'].includes(
                        property.kind,
                    )
                        ? 'decimal'
                        : undefined
                }
                list={suggestions.length > 0 ? `${id}-list` : undefined}
                placeholder={
                    inherited && value !== undefined
                        ? t('Same as bigger screens ({value})', {
                              value: String(value),
                          })
                        : t('Default')
                }
                aria-invalid={invalid}
                aria-describedby={invalid ? `${id}-hint` : undefined}
                onChange={(event) => {
                    const next = event.target.value;
                    const nextValue = parseStyleInput(property.kind, next);
                    setDraft(next);

                    if (next.trim() === '') {
                        onChange(undefined);
                    } else if (isValidStyleValue(property.kind, nextValue)) {
                        onChange(nextValue);
                    }
                }}
                onBlur={() => setDraft(null)}
                className="h-8"
            />
            {suggestions.length > 0 && (
                <datalist id={`${id}-list`}>
                    {suggestions.map((suggestion) => (
                        <option key={suggestion} value={suggestion} />
                    ))}
                </datalist>
            )}
            {invalid && (
                <p id={`${id}-hint`} className="text-xs text-destructive">
                    {t(
                        HINTS[property.kind] ??
                            msg('This value is not allowed.'),
                    )}
                </p>
            )}
        </div>
    );
}

// A number with a unit picker. The value rule of the property decides the units and keywords.
function LengthControl({
    property,
    value,
    inherited,
    limits,
    onChange,
}: ControlProps) {
    const id = useId();
    const rule = property.value ?? { type: property.kind as 'length' };

    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-xs">
                {property.label}
            </Label>
            <LengthInput
                id={id}
                label={property.label}
                value={inherited ? undefined : value}
                onChange={onChange}
                rule={rule}
                units={limits.units ?? null}
                tokens
                size="sm"
                suggestions={
                    (rule.type ?? 'length') === 'length'
                        ? (limits.spacing ?? DEFAULT_SIZES)
                        : []
                }
                placeholder={
                    inherited && value !== undefined
                        ? String(value)
                        : t('Default')
                }
            />
        </div>
    );
}

// Theme colors as swatches when the theme limits colors, otherwise the color picker of the browser.
function ColorControl({
    property,
    value,
    inherited,
    limits,
    onChange,
}: ControlProps) {
    const id = useId();
    const current = inherited ? undefined : (value as string | undefined);

    if (limits.colors) {
        return (
            <fieldset className="grid gap-1">
                <legend className="mb-1 text-xs font-medium">
                    {t(property.label)}
                </legend>
                <div className="flex flex-wrap gap-1.5">
                    {limits.colors.map((color) => (
                        <button
                            key={color}
                            type="button"
                            aria-label={color}
                            aria-pressed={current === color}
                            onClick={() =>
                                onChange(current === color ? undefined : color)
                            }
                            style={{ backgroundColor: color }}
                            className={cn(
                                'size-7 rounded-full border border-black/10 outline-offset-2 transition-[scale] hover:scale-110 motion-reduce:hover:scale-100 dark:border-white/20',
                                current === color &&
                                    'outline-2 outline-primary',
                            )}
                        />
                    ))}
                </div>
            </fieldset>
        );
    }

    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-xs">
                {t(property.label)}
            </Label>
            <div className="flex items-center gap-1.5">
                <input
                    id={id}
                    type="color"
                    value={
                        typeof value === 'string' &&
                        /^#[0-9a-f]{6}$/i.test(value)
                            ? value
                            : '#000000'
                    }
                    onChange={(event) => onChange(event.target.value)}
                    className="h-8 w-10 cursor-pointer rounded border bg-transparent p-0.5"
                />
                <span className="min-w-0 flex-1 truncate font-mono text-xs text-muted-foreground">
                    {current ??
                        (inherited
                            ? t('Same as bigger screens')
                            : t('Default'))}
                </span>
                {current !== undefined && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 px-2"
                        onClick={() => onChange(undefined)}
                        aria-label={t('Clear {property}', {
                            property: labelInSentence(property.label),
                        })}
                    >
                        <Eraser />
                    </Button>
                )}
            </div>
        </div>
    );
}

function SelectControl({ property, value, inherited, onChange }: ControlProps) {
    const id = useId();

    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-xs">
                {t(property.label)}
            </Label>
            <OptionSelect
                id={id}
                size="sm"
                value={inherited || value === undefined ? '' : String(value)}
                onValueChange={(option) =>
                    onChange(option === '' ? undefined : option)
                }
                options={[
                    {
                        value: '',
                        label:
                            inherited && value !== undefined
                                ? t('Same as bigger screens ({value})', {
                                      value: humanize(String(value)),
                                  })
                                : t('Default'),
                    },
                    ...property.options.map((option) => ({
                        value: option,
                        label: humanize(option),
                    })),
                ]}
                searchPlaceholder={t('Search options')}
            />
        </div>
    );
}

function ToggleControl({ property, value, onChange }: ControlProps) {
    const id = useId();
    const label =
        property.path === 'visibility.hidden'
            ? t('Hide on this screen size')
            : property.path === 'position.sticky'
              ? t('Stick to the top when scrolling')
              : t(property.label);

    return (
        <div className="flex items-center gap-2">
            <Checkbox
                id={id}
                checked={value === true}
                onCheckedChange={(checked) =>
                    onChange(checked === true ? true : undefined)
                }
            />
            <Label htmlFor={id} className="text-sm font-normal">
                {label}
            </Label>
        </div>
    );
}

const CONTROLS: Record<string, (props: ControlProps) => React.ReactNode> = {
    length: LengthControl,
    color: ColorControl,
    select: SelectControl,
    toggle: ToggleControl,
    gradient: GradientControl,
    image: ImageControl,
    focal: FocalControl,
};

// Controls that need a whole row of the panel.
const WIDE_CONTROLS = ['toggle', 'color', 'gradient', 'image', 'focal'];

// The "Styles" panel of the Block tab: every style option of the registry, per screen size.
export function StylesPanel({
    block,
    onChange,
    device,
    limits,
}: InspectorPanelProps) {
    const design = blockDesign(block);
    const devices = builder.breakpoints
        .all()
        .map((breakpoint) => breakpoint.name);
    const copied = useSyncExternalStore(
        styleClipboard.subscribe,
        styleClipboard.read,
        styleClipboard.read,
    );
    const update = (path: string, value: StyleValue | undefined) =>
        onChange(withDesign(block, setStyleValue(design, path, device, value)));
    const pictureValue = styleValue(
        design,
        'background.image',
        device,
        devices,
    ).value;
    const picture = typeof pictureValue === 'string' ? pictureValue : undefined;

    return (
        <div className="grid gap-3" data-test="styles-panel">
            <StylePresetsBar
                block={block}
                onChange={onChange}
                canEdit={stylePresets.editable()}
            />
            <div className="flex flex-wrap gap-1.5">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={Object.keys(design).length === 0}
                    onClick={() => styleClipboard.copy(design)}
                >
                    <Copy />
                    {t('Copy styles')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={copied === null}
                    onClick={() =>
                        copied && onChange(withDesign(block, copied))
                    }
                >
                    <ClipboardPaste />
                    {t('Paste styles')}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={Object.keys(design).length === 0}
                    onClick={() => onChange(withDesign(block, {}))}
                >
                    <Eraser />
                    {t('Reset')}
                </Button>
            </div>
            <div className="divide-y rounded-md border">
                {groupStyleProperties(builder.styleProperties.all()).map(
                    ([group, properties]) => (
                        <details key={group} className="group/styles px-3">
                            <summary className="cursor-pointer py-2 text-sm font-medium select-none">
                                {STYLE_GROUP_LABELS[group]
                                    ? t(STYLE_GROUP_LABELS[group])
                                    : humanize(group)}
                            </summary>
                            <div className="auto-grid gap-3 pb-3 [--grid-min:9.5rem]">
                                {properties.map((property) => {
                                    const Control = VALUE_KINDS.includes(
                                        property.kind,
                                    )
                                        ? LengthControl
                                        : (CONTROLS[property.control] ??
                                          TextControl);
                                    const { value, inherited } = styleValue(
                                        design,
                                        property.path,
                                        device,
                                        devices,
                                    );

                                    return (
                                        <div
                                            key={`${property.path}:${device}`}
                                            className={cn(
                                                WIDE_CONTROLS.includes(
                                                    property.control,
                                                ) && 'col-span-full',
                                            )}
                                        >
                                            <Control
                                                property={property}
                                                value={value}
                                                inherited={inherited}
                                                limits={limits}
                                                onChange={(next) =>
                                                    update(property.path, next)
                                                }
                                                picture={
                                                    property.control === 'focal'
                                                        ? picture
                                                        : undefined
                                                }
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        </details>
                    ),
                )}
            </div>
        </div>
    );
}
