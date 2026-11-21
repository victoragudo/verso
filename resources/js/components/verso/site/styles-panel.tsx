import { router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LengthInput, OptionSelect, SectionCard } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { globalStyles as saveGlobalStyles } from '@/routes/admin/site-editor';
import type { GlobalStylesData, StyleToken } from '@/types';

type Props = {
    data: GlobalStylesData;
    previewUrl: string;
    errors?: Record<string, string>;
};

// Plain names of the token groups. Colors and fonts show first; the rest sit under "Advanced".
const GROUP_TITLES: Record<string, string> = {
    color: msg('Colors'),
    font: msg('Fonts'),
    text: msg('Text sizes'),
    spacing: msg('Spacing'),
    radius: msg('Corners'),
    width: msg('Widths'),
};

const MAIN_GROUPS = ['color', 'font'];

// Text shown in each font card, so people see the font before they pick it.
const FONT_SAMPLE = msg('Fresh bread every morning');

// The Styles panel: colors as swatches, fonts as sample text, the other tokens under "Advanced", with a live preview.
export function StylesPanel({ data, previewUrl, errors = {} }: Props) {
    const [values, setValues] = useState(data.values);
    const [saving, setSaving] = useState(false);
    const changed = Object.keys(values).filter(
        (id) => values[id] !== data.values[id],
    );
    const query = new URLSearchParams(
        changed.map((id) => [`tokens[${id}]`, values[id]]),
    ).toString();

    const set = (id: string, value: string) =>
        setValues((current) => ({ ...current, [id]: value }));

    const save = () => {
        setSaving(true);
        router.put(
            saveGlobalStyles.url(),
            {
                values: Object.fromEntries(
                    changed.map((id) => [id, values[id]]),
                ),
            },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const main = data.groups.filter((group) =>
        MAIN_GROUPS.includes(group.name),
    );
    const advanced = data.groups.filter(
        (group) => !MAIN_GROUPS.includes(group.name),
    );

    const renderGroup = (group: GlobalStylesData['groups'][number]) => (
        <fieldset key={group.name} className="grid gap-3">
            <legend className="mb-2 text-sm font-semibold">
                {GROUP_TITLES[group.name]
                    ? t(GROUP_TITLES[group.name])
                    : group.name}
            </legend>
            <div
                className={cn(
                    'grid gap-3',
                    group.name === 'font'
                        ? 'grid-cols-1'
                        : 'auto-grid [--grid-min:13rem]',
                )}
            >
                {group.tokens.map((token) => (
                    <TokenField
                        key={token.id}
                        token={token}
                        value={values[token.id] ?? token.default}
                        fonts={data.fonts}
                        units={data.units ?? null}
                        error={errors[`values.${token.id}`]}
                        onChange={(value) => set(token.id, value)}
                    />
                ))}
            </div>
        </fieldset>
    );

    return (
        <SectionCard
            title={t('Colors and fonts')}
            description={t(
                'Change the look of the whole site. The preview shows your picks before you save them.',
            )}
            actions={
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={changed.length === 0}
                        onClick={() => setValues(data.values)}
                    >
                        {t('Undo changes')}
                    </Button>
                    <Button
                        type="button"
                        disabled={changed.length === 0 || saving}
                        onClick={save}
                    >
                        {t('Save colors and fonts')}
                    </Button>
                </div>
            }
        >
            <div className="grid gap-6 @4xl/page:grid-cols-[clamp(22rem,24cqi,30rem)_minmax(0,1fr)]">
                <div className="grid content-start gap-6">
                    {main.map(renderGroup)}
                    {advanced.length > 0 && (
                        <Collapsible>
                            <CollapsibleTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="group -ms-3"
                                >
                                    {t('Advanced')}
                                    <ChevronDown
                                        aria-hidden="true"
                                        className="transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                                    />
                                </Button>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="mt-4 grid gap-6">
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Sizes take any CSS unit, like rem (1rem is the normal text size), px, % or dvh, and custom values like clamp(1rem, 2vw, 2rem).',
                                    )}
                                </p>
                                {advanced.map(renderGroup)}
                            </CollapsibleContent>
                        </Collapsible>
                    )}
                </div>
                <iframe
                    title={t(
                        'Preview of your colors and fonts on the home page',
                    )}
                    src={query === '' ? previewUrl : `${previewUrl}?${query}`}
                    className="h-[70vh] min-h-[28rem] w-full rounded-md border bg-white"
                    data-test="styles-preview"
                />
            </div>
        </SectionCard>
    );
}

// One token: a color swatch, a font choice or a size box.
function TokenField({
    token,
    value,
    fonts,
    units,
    error,
    onChange,
}: {
    token: StyleToken;
    value: string;
    fonts: GlobalStylesData['fonts'];
    units: string[] | null;
    error?: string;
    onChange: (value: string) => void;
}) {
    const id = `token-${token.id.replace('.', '-')}`;
    const help = token.help && (
        <p id={`${id}-help`} className="text-xs text-muted-foreground">
            {token.help}
        </p>
    );

    if (token.kind === 'color') {
        return (
            <div className="grid gap-1.5">
                <Label htmlFor={id}>{token.label}</Label>
                <div className="flex items-center gap-2">
                    <input
                        id={id}
                        type="color"
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        aria-describedby={token.help ? `${id}-help` : undefined}
                        className="size-10 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                    />
                    <Input
                        aria-label={t('{name} color code', {
                            name: token.label,
                        })}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        spellCheck={false}
                        className="font-mono"
                    />
                </div>
                {help}
                <InputError message={error} />
            </div>
        );
    }

    if (token.kind === 'font') {
        return (
            <div className="grid gap-1.5">
                <Label htmlFor={id}>{token.label}</Label>
                <OptionSelect
                    id={id}
                    value={value}
                    onValueChange={onChange}
                    aria-describedby={`${id}-sample`}
                    options={fonts.map((font) => ({
                        value: font.value,
                        label: font.label,
                    }))}
                    searchPlaceholder={t('Search fonts')}
                />
                <p
                    id={`${id}-sample`}
                    className="rounded-md bg-muted px-3 py-2 text-lg"
                    style={{
                        fontFamily: fonts.find((font) => font.value === value)
                            ?.stack,
                    }}
                >
                    {t(FONT_SAMPLE)}
                </p>
                {help}
                <InputError message={error} />
            </div>
        );
    }

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{token.label}</Label>
            <LengthInput
                id={id}
                label={token.label}
                value={value}
                onChange={(next) => onChange(next ?? token.default)}
                rule={token.value ?? { negative: false }}
                units={units}
                invalid={Boolean(error)}
                aria-describedby={token.help ? `${id}-help` : undefined}
                placeholder={token.default}
            />
            {help}
            <InputError message={error} />
        </div>
    );
}
