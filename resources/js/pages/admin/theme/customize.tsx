import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MediaPicker } from '@/components/verso/media/media-picker';
import {
    LengthInput,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { customize, index, preview } from '@/routes/admin/themes';
import { update } from '@/routes/admin/themes/customize';
import type {
    MediaItem,
    ThemeInfo,
    ThemeSettingField,
    ThemeSettingValue,
} from '@/types';

type MediaPreview = Pick<MediaItem, 'id' | 'url' | 'filename'>;

type Props = {
    theme: Omit<ThemeInfo, 'removable' | 'customizable'>;
    fields: ThemeSettingField[];
    schemaErrors: string[];
    values: Record<string, ThemeSettingValue>;
    media: Record<number, MediaPreview>;
    fonts: Record<string, string>;
};

type CustomizeForm = {
    values: Record<string, ThemeSettingValue>;
};

// Wait this long after the last change before the preview reloads.
const PREVIEW_DELAY_MS = 400;

// Builds the preview address with the draft values in the query string.
function previewUrl(slug: string, values: Record<string, ThemeSettingValue>) {
    const params = new URLSearchParams();

    Object.entries(values).forEach(([key, value]) => {
        if (value !== null && value !== '') {
            params.set(
                `values[${key}]`,
                typeof value === 'boolean'
                    ? value
                        ? '1'
                        : '0'
                    : String(value),
            );
        }
    });

    return `${preview.url(slug)}?${params.toString()}`;
}

// Theme customizer: a form built from the theme settings with a live preview.
export default function ThemeCustomize({
    theme,
    fields,
    schemaErrors,
    values,
    media,
    fonts,
}: Props) {
    const form = useForm<CustomizeForm>({ values });
    const { data, setData, errors, processing, isDirty } = form;
    const [frameUrl, setFrameUrl] = useState(() =>
        previewUrl(theme.slug, values),
    );

    useEffect(() => {
        const timer = window.setTimeout(
            () => setFrameUrl(previewUrl(theme.slug, data.values)),
            PREVIEW_DELAY_MS,
        );

        return () => window.clearTimeout(timer);
    }, [data.values, theme.slug]);

    const setValue = (key: string, value: ThemeSettingValue) =>
        setData('values', { ...data.values, [key]: value });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(theme.slug), { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Customize {theme}', { theme: theme.name })} />
            <PageHeader
                icon="customize"
                title={t('Customize {theme}', { theme: theme.name })}
                description={t(
                    'Change colors, fonts, the logo and the layout. The preview updates as you type.',
                )}
                actions={
                    theme.active ? (
                        <StatusBadge tone="success">{t('Active')}</StatusBadge>
                    ) : (
                        <StatusBadge tone="neutral">
                            {t('Not active')}
                        </StatusBadge>
                    )
                }
            />

            <div className="grid gap-4 @4xl/page:grid-cols-[clamp(22rem,24cqi,30rem)_minmax(0,1fr)]">
                <form onSubmit={submit} className="min-w-0">
                    <SectionCard
                        title={t('Settings')}
                        description={t('Empty fields use the theme default.')}
                        contentClassName="grid gap-5"
                        footer={
                            <Button
                                type="submit"
                                disabled={processing || !isDirty}
                                className="min-h-10 md:min-h-9"
                            >
                                {t('Save changes')}
                            </Button>
                        }
                    >
                        {schemaErrors.length > 0 && (
                            <ul className="list-disc rounded-md bg-warning-soft py-2 ps-7 pe-3 text-sm text-warning-text">
                                {schemaErrors.map((problem) => (
                                    <li key={problem}>{problem}</li>
                                ))}
                            </ul>
                        )}
                        {fields.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('This theme has no settings.')}
                            </p>
                        )}
                        {fields.map((field) => (
                            <SettingField
                                key={field.key}
                                field={field}
                                value={data.values[field.key] ?? null}
                                fonts={fonts}
                                media={media}
                                error={
                                    (errors as Record<string, string>)[
                                        `values.${field.key}`
                                    ]
                                }
                                onChange={(value) => setValue(field.key, value)}
                            />
                        ))}
                    </SectionCard>
                </form>

                <SectionCard
                    title={t('Preview')}
                    description={t('Your home page with the unsaved changes.')}
                    contentClassName="p-0 sm:p-0"
                >
                    <iframe
                        title={t('Preview of {theme}', { theme: theme.name })}
                        src={frameUrl}
                        className="h-[70vh] min-h-[480px] w-full rounded-b-lg bg-white"
                    />
                </SectionCard>
            </div>
        </>
    );
}

// One form control, picked by the field type.
function SettingField({
    field,
    value,
    fonts,
    media,
    error,
    onChange,
}: {
    field: ThemeSettingField;
    value: ThemeSettingValue;
    fonts: Record<string, string>;
    media: Record<number, MediaPreview>;
    error?: string;
    onChange: (value: ThemeSettingValue) => void;
}) {
    const id = `setting-${field.key}`;

    return (
        <div className="@container/field grid gap-2">
            <Label htmlFor={id}>{field.label}</Label>
            <FieldControl
                id={id}
                field={field}
                value={value}
                fonts={fonts}
                media={media}
                onChange={onChange}
            />
            {field.help !== '' && (
                <p className="text-xs text-muted-foreground">{field.help}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

// A stored size as CSS: old whole numbers are in the unit of the field, like 62 => "62rem".
export function sizeValue(
    value: ThemeSettingValue,
    field: ThemeSettingField,
): string | undefined {
    if (typeof value === 'number') {
        return `${value}${field.unit}`;
    }

    return typeof value === 'string' && value !== '' ? value : undefined;
}

// A size or length field: a slider for the range of the theme, and a length input for any CSS unit.
function SizeControl({
    id,
    field,
    value,
    onChange,
}: {
    id: string;
    field: ThemeSettingField;
    value: ThemeSettingValue;
    onChange: (value: ThemeSettingValue) => void;
}) {
    const current = value ?? field.default;
    const css = sizeValue(current, field);
    const hasRange = field.max > field.min;
    const inUnit =
        typeof current === 'number'
            ? current
            : css !== undefined &&
                css.endsWith(field.unit) &&
                /^-?\d+(\.\d+)?$/.test(css.slice(0, -field.unit.length))
              ? parseFloat(css)
              : null;

    return (
        <div className="grid gap-2 @md/field:grid-cols-[minmax(0,1fr)_minmax(10rem,14rem)] @md/field:items-center">
            {hasRange && (
                <input
                    type="range"
                    min={field.min}
                    max={field.max}
                    value={inUnit ?? field.min}
                    aria-label={t('{field} in {unit}', {
                        field: field.label,
                        unit: field.unit,
                    })}
                    onChange={(event) => onChange(Number(event.target.value))}
                    className={
                        inUnit === null
                            ? 'h-10 min-w-0 accent-brand opacity-50'
                            : 'h-10 min-w-0 accent-brand'
                    }
                />
            )}
            <LengthInput
                id={id}
                label={field.label}
                value={css}
                rule={field.value ?? undefined}
                onChange={(next) => onChange(next ?? null)}
                placeholder={sizeValue(field.default, field)}
                className={hasRange ? undefined : 'col-span-full'}
            />
        </div>
    );
}

function FieldControl({
    id,
    field,
    value,
    fonts,
    media,
    onChange,
}: {
    id: string;
    field: ThemeSettingField;
    value: ThemeSettingValue;
    fonts: Record<string, string>;
    media: Record<number, MediaPreview>;
    onChange: (value: ThemeSettingValue) => void;
}) {
    switch (field.type) {
        case 'color':
            return (
                <div className="flex items-center gap-2">
                    <input
                        id={id}
                        type="color"
                        value={String(value ?? field.default ?? '#000000')}
                        onChange={(event) => onChange(event.target.value)}
                        className="h-10 w-12 shrink-0 cursor-pointer rounded-md border bg-card p-1"
                    />
                    <Input
                        aria-label={t('{name} hex value', {
                            name: field.label,
                        })}
                        value={String(value ?? '')}
                        placeholder={String(field.default ?? '')}
                        onChange={(event) => onChange(event.target.value)}
                        className="font-mono tabular-nums"
                    />
                </div>
            );
        case 'font':
            return (
                <Select
                    value={String(value ?? field.default ?? '')}
                    onValueChange={(next) => onChange(next)}
                >
                    <SelectTrigger id={id} className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {field.options.map((option) => (
                            <SelectItem key={option} value={option}>
                                {fonts[option] ?? option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        case 'size':
        case 'length':
            return (
                <SizeControl
                    id={id}
                    field={field}
                    value={value}
                    onChange={onChange}
                />
            );
        case 'toggle':
            return (
                <Checkbox
                    id={id}
                    checked={Boolean(value ?? field.default)}
                    onCheckedChange={(checked) => onChange(checked === true)}
                />
            );
        case 'media':
            return (
                <MediaField
                    id={id}
                    value={typeof value === 'number' ? value : null}
                    media={media}
                    onChange={onChange}
                />
            );
        default:
            return (
                <Input
                    id={id}
                    value={String(value ?? '')}
                    maxLength={field.maxLength}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
    }
}

// Pick an image from the media library, with a small preview.
function MediaField({
    id,
    value,
    media,
    onChange,
}: {
    id: string;
    value: number | null;
    media: Record<number, MediaPreview>;
    onChange: (value: ThemeSettingValue) => void;
}) {
    const [open, setOpen] = useState(false);
    const [picked, setPicked] = useState<Record<number, MediaPreview>>({});
    const known = useMemo(() => ({ ...media, ...picked }), [media, picked]);
    const current = value !== null ? known[value] : undefined;

    return (
        <div className="flex flex-wrap items-center gap-3">
            {current ? (
                <img
                    src={current.url}
                    alt=""
                    className="h-10 max-w-32 rounded border bg-muted object-contain"
                />
            ) : (
                <span className="text-sm text-muted-foreground">
                    {t('No image chosen.')}
                </span>
            )}
            <Button
                id={id}
                type="button"
                variant="outline"
                size="sm"
                className="min-h-10 md:min-h-8"
                onClick={() => setOpen(true)}
            >
                {current ? t('Change') : t('Choose image')}
            </Button>
            {value !== null && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="min-h-10 md:min-h-8"
                    onClick={() => onChange(null)}
                >
                    {t('Remove')}
                </Button>
            )}
            <MediaPicker
                open={open}
                onOpenChange={setOpen}
                accept="image"
                onSelect={(item) => {
                    setPicked((previous) => ({ ...previous, [item.id]: item }));
                    onChange(item.id);
                    setOpen(false);
                }}
            />
        </div>
    );
}

ThemeCustomize.layout = (props: Props) => ({
    breadcrumbs: [
        { title: msg('Themes'), href: index() },
        { title: msg('Customize'), href: customize(props.theme.slug) },
    ],
});
