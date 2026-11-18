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
import { Textarea } from '@/components/ui/textarea';
import { LengthInput, SearchableSelect } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import type { PluginBlockField } from '@/types';
import { DateTimePicker } from '@/components/verso/ui/date-time-picker';
import { ColorField, IconField, MediaField } from './field-inputs';
import type { BlockFieldProps } from './shared';
import { boolAttr, stringAttr, withAttrs } from './shared';

const SEARCHABLE_OPTION_COUNT = 10;

type PluginBlockFieldsProps = BlockFieldProps & {
    fields: PluginBlockField[];
    className?: string;
};

// Builds one input per field that the plugin block describes.
export function PluginBlockFields({
    block,
    onChange,
    fields,
    className = 'auto-fit-grid gap-3 [--grid-min:13rem]',
}: PluginBlockFieldsProps) {
    if (fields.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('This block has no settings. It is filled in on the site.')}
            </p>
        );
    }

    const set = (name: string, value: unknown) => {
        if (value === null) {
            const attrs = { ...block.attrs };
            delete attrs[name];
            onChange({ ...block, attrs });

            return;
        }

        onChange(withAttrs(block, { [name]: value }));
    };

    return (
        <div className={className}>
            {fields.map((field) => (
                <PluginField
                    key={field.name}
                    id={`${block.id}-${field.name}`}
                    field={field}
                    text={stringAttr(block, field.name)}
                    raw={block.attrs?.[field.name]}
                    checked={boolAttr(
                        block,
                        field.name,
                        field.default === true,
                    )}
                    onChange={(value) => set(field.name, value)}
                />
            ))}
        </div>
    );
}

// One labelled input. Empty values remove the attr so the server sees null.
function PluginField({
    id,
    field,
    text,
    raw,
    checked,
    onChange,
}: {
    id: string;
    raw?: unknown;
    field: PluginBlockField;
    text: string;
    checked: boolean;
    onChange: (value: unknown) => void;
}) {
    const help = field.help ? (
        <p className="text-xs text-muted-foreground">{field.help}</p>
    ) : null;

    if (field.type === 'toggle') {
        return (
            <div className="grid content-end gap-1">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={id}
                        checked={checked}
                        onCheckedChange={(value) => onChange(value === true)}
                    />
                    <Label htmlFor={id}>{field.label}</Label>
                </div>
                {help}
            </div>
        );
    }

    let input: React.ReactNode;
    // A select without a saved value shows the default the site uses, like the primary menu.
    const fallback =
        typeof field.default === 'string' || typeof field.default === 'number'
            ? String(field.default)
            : '';
    const choice =
        text ||
        (field.options.some((option) => option.value === fallback)
            ? fallback
            : '');

    if (field.type === 'media') {
        input = (
            <MediaField
                id={id}
                label={field.label}
                value={raw}
                onChange={(value) => onChange(value)}
            />
        );
    } else if (field.type === 'icon') {
        input = (
            <IconField
                id={id}
                label={field.label}
                value={text}
                onChange={(value) => onChange(value)}
            />
        );
    } else if (field.type === 'color') {
        input = <ColorField id={id} value={text} onChange={onChange} />;
    } else if (field.type === 'datetime') {
        input = (
            <DateTimePicker
                id={id}
                value={text}
                onChange={(value) => onChange(value === '' ? null : value)}
            />
        );
    } else if (field.type === 'length') {
        input = (
            <LengthInput
                id={id}
                label={field.label}
                value={text === '' ? undefined : text}
                rule={field.value ?? undefined}
                placeholder={
                    field.default === null || field.default === undefined
                        ? undefined
                        : String(field.default)
                }
                onChange={(value) => onChange(value ?? null)}
            />
        );
    } else if (
        field.type === 'select' &&
        (field.searchable === true ||
            field.options.length > SEARCHABLE_OPTION_COUNT)
    ) {
        input = (
            <SearchableSelect
                id={id}
                value={choice}
                onValueChange={(value) => onChange(value)}
                options={field.options}
                placeholder={t('Choose an option')}
                searchPlaceholder={t('Search options')}
                className="w-full"
            />
        );
    } else if (field.type === 'select') {
        input = (
            <Select
                value={choice || undefined}
                onValueChange={(value) => onChange(value)}
            >
                <SelectTrigger id={id}>
                    <SelectValue placeholder={t('Choose an option')} />
                </SelectTrigger>
                <SelectContent>
                    {field.options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        );
    } else if (field.type === 'textarea') {
        input = (
            <Textarea
                id={id}
                rows={3}
                value={text}
                required={field.required}
                maxLength={field.max ?? undefined}
                onChange={(event) =>
                    onChange(
                        event.target.value === '' ? null : event.target.value,
                    )
                }
            />
        );
    } else {
        input = (
            <Input
                id={id}
                type={
                    field.type === 'number'
                        ? 'number'
                        : field.type === 'url'
                          ? 'url'
                          : 'text'
                }
                inputMode={field.type === 'number' ? 'decimal' : undefined}
                className={field.type === 'number' ? 'tabular-nums' : undefined}
                value={text}
                required={field.required}
                min={
                    field.type === 'number'
                        ? (field.min ?? undefined)
                        : undefined
                }
                max={
                    field.type === 'number'
                        ? (field.max ?? undefined)
                        : undefined
                }
                maxLength={
                    field.type === 'text' ? (field.max ?? undefined) : undefined
                }
                onChange={(event) => {
                    const value = event.target.value;

                    if (value === '') {
                        onChange(null);
                    } else if (field.type === 'number') {
                        const number = Number(value);
                        onChange(Number.isNaN(number) ? null : number);
                    } else {
                        onChange(value);
                    }
                }}
            />
        );
    }

    return (
        <div
            className={
                ['textarea', 'icon', 'media'].includes(field.type)
                    ? 'col-span-full grid gap-1'
                    : 'grid gap-1'
            }
        >
            <Label htmlFor={id}>
                {field.label}
                {field.required && <span className="text-red-600"> *</span>}
            </Label>
            {input}
            {help}
        </div>
    );
}
