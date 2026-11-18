import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useId } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { InlineEditor } from '@/components/verso/editor/inline-editor';
import {
    DatePicker,
    DateTimePicker,
    SearchableSelect,
} from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { initialValues, isRecord, moveItem, visibleFields } from './logic';
import { MediaField } from './media-field';
import { RelationSelect } from './relation-select';
import type { FieldDefinition, FieldValues } from './types';

// Selects with more options than this get a search box.
const SEARCHABLE_OPTION_COUNT = 10;

type Errors = Record<string, string | undefined>;

type FieldListProps = {
    fields: FieldDefinition[];
    values: FieldValues;
    onChange: (values: FieldValues) => void;
    errors: Errors;
    path: string;
};

// The inputs of one level of fields. Fields hidden by a condition are not shown.
export function FieldList({
    fields,
    values,
    onChange,
    errors,
    path,
}: FieldListProps) {
    const visible = visibleFields(fields, values);

    return (
        <div className="grid gap-4">
            {fields
                .filter((field) => visible.has(field.name))
                .map((field) => (
                    <FieldInput
                        key={field.name}
                        field={field}
                        value={values[field.name]}
                        onChange={(value) =>
                            onChange({ ...values, [field.name]: value })
                        }
                        errors={errors}
                        path={`${path}.${field.name}`}
                    />
                ))}
        </div>
    );
}

type FieldInputProps = {
    field: FieldDefinition;
    value: unknown;
    onChange: (value: unknown) => void;
    errors: Errors;
    path: string;
};

// One labelled input for a field, picked by its type.
export function FieldInput({
    field,
    value,
    onChange,
    errors,
    path,
}: FieldInputProps) {
    const id = useId();
    const error = errors[path];
    const helpId = `${id}-help`;
    const isToggle = field.type === 'toggle';

    return (
        <div className="grid min-w-0 gap-2" data-field={field.name}>
            {!isToggle && (
                <Label htmlFor={id}>
                    {field.label}
                    {field.required && (
                        <span
                            className="text-destructive-foreground"
                            aria-hidden="true"
                        >
                            {' '}
                            *
                        </span>
                    )}
                </Label>
            )}
            <FieldControl
                id={id}
                field={field}
                value={value}
                onChange={onChange}
                errors={errors}
                path={path}
                invalid={Boolean(error)}
                describedBy={field.instructions ? helpId : undefined}
            />
            {field.instructions && (
                <p id={helpId} className="text-xs text-muted-foreground">
                    {field.instructions}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}

type ControlProps = FieldInputProps & {
    id: string;
    invalid: boolean;
    describedBy?: string;
};

function text(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}

function list(value: unknown): string[] {
    return Array.isArray(value) ? value.map(String) : [];
}

function FieldControl({
    id,
    field,
    value,
    onChange,
    errors,
    path,
    invalid,
    describedBy,
}: ControlProps) {
    const common = {
        id,
        'aria-invalid': invalid,
        'aria-describedby': describedBy,
    };
    const options = field.options ?? [];

    switch (field.type) {
        case 'textarea':
            return (
                <Textarea
                    {...common}
                    value={text(value)}
                    placeholder={field.placeholder}
                    maxLength={field.max_length ?? undefined}
                    rows={4}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'rich_text':
            return (
                <div
                    id={id}
                    className="rounded-md border bg-background px-3 py-2 text-sm focus-within:ring-2 focus-within:ring-ring/40"
                >
                    <InlineEditor
                        html={text(value)}
                        onChange={(html) => onChange(html)}
                        variant="paragraphs"
                        placeholder={field.placeholder || t('Write here')}
                        label={field.label}
                    />
                </div>
            );
        case 'number':
            return (
                <Input
                    {...common}
                    type="number"
                    inputMode="decimal"
                    value={text(value)}
                    min={field.min ?? undefined}
                    max={field.max ?? undefined}
                    step={field.step ?? 'any'}
                    placeholder={field.placeholder}
                    className="tabular-nums"
                    onChange={(event) =>
                        onChange(
                            event.target.value === ''
                                ? null
                                : Number(event.target.value),
                        )
                    }
                />
            );
        case 'email':
        case 'url':
        case 'text':
            return (
                <Input
                    {...common}
                    type={field.type === 'text' ? 'text' : field.type}
                    value={text(value)}
                    placeholder={field.placeholder}
                    maxLength={field.max_length ?? undefined}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'select':
            return options.length > SEARCHABLE_OPTION_COUNT ? (
                <SearchableSelect
                    id={id}
                    value={text(value)}
                    onValueChange={onChange}
                    options={options}
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                    className="w-full"
                />
            ) : (
                <Select value={text(value)} onValueChange={onChange}>
                    <SelectTrigger {...common} className="w-full">
                        <SelectValue placeholder={t('Pick an option')} />
                    </SelectTrigger>
                    <SelectContent>
                        {options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        case 'multi_select':
        case 'checkbox': {
            const picked = list(value);
            const toggle = (option: string, checked: boolean) =>
                onChange(
                    checked
                        ? options
                              .map((item) => item.value)
                              .filter(
                                  (item) =>
                                      item === option || picked.includes(item),
                              )
                        : picked.filter((item) => item !== option),
                );

            return (
                <div
                    id={id}
                    role="group"
                    aria-describedby={describedBy}
                    className={
                        field.type === 'multi_select'
                            ? 'grid max-h-56 gap-0.5 overflow-y-auto rounded-md border p-1.5'
                            : 'grid gap-0.5'
                    }
                >
                    {options.map((option) => (
                        <label
                            key={option.value}
                            className="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-md px-2 text-sm hover:bg-muted/60 md:min-h-8"
                        >
                            <Checkbox
                                checked={picked.includes(option.value)}
                                onCheckedChange={(checked) =>
                                    toggle(option.value, checked === true)
                                }
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            );
        }
        case 'radio':
            return (
                <div
                    id={id}
                    role="radiogroup"
                    aria-describedby={describedBy}
                    className="grid gap-0.5"
                >
                    {options.map((option) => (
                        <label
                            key={option.value}
                            className="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-md px-2 text-sm hover:bg-muted/60 md:min-h-8"
                        >
                            <input
                                type="radio"
                                name={id}
                                value={option.value}
                                checked={text(value) === option.value}
                                onChange={() => onChange(option.value)}
                                className="size-4 accent-[var(--color-brand,#2563eb)]"
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            );
        case 'toggle':
            return (
                <label className="flex min-h-10 items-center gap-2.5 text-sm font-medium md:min-h-8">
                    <Checkbox
                        id={id}
                        checked={value === true}
                        aria-describedby={describedBy}
                        onCheckedChange={(checked) =>
                            onChange(checked === true)
                        }
                    />
                    {field.label}
                </label>
            );
        case 'date':
            return (
                <DatePicker
                    {...common}
                    value={text(value)}
                    onChange={(next) => onChange(next || null)}
                    placeholder={t('Pick a date')}
                />
            );
        case 'date_time':
            return (
                <DateTimePicker
                    {...common}
                    value={text(value)}
                    onChange={(next) => onChange(next || null)}
                    placeholder={t('Pick a date and time')}
                />
            );
        case 'color':
            return (
                <div className="flex items-center gap-2">
                    <input
                        type="color"
                        aria-label={t('{label} picker', { label: field.label })}
                        value={text(value) || '#000000'}
                        onChange={(event) => onChange(event.target.value)}
                        className="h-10 w-12 cursor-pointer rounded-md border bg-background p-1 md:h-9"
                    />
                    <Input
                        {...common}
                        value={text(value)}
                        placeholder="#1d4ed8"
                        className="font-mono"
                        onChange={(event) => onChange(event.target.value)}
                    />
                </div>
            );
        case 'image':
        case 'file':
        case 'gallery':
            return (
                <MediaField
                    kind={field.type}
                    value={value as number | number[] | null}
                    onChange={onChange}
                    label={field.label}
                    maxItems={field.max_rows}
                />
            );
        case 'entry_relation':
        case 'term_relation':
        case 'user_relation':
            return (
                <RelationSelect
                    id={id}
                    kind={
                        field.type === 'entry_relation'
                            ? 'entry'
                            : field.type === 'term_relation'
                              ? 'term'
                              : 'user'
                    }
                    filters={{
                        types: field.content_types,
                        taxonomy: field.taxonomy,
                        roles: field.roles,
                    }}
                    multiple={field.multiple === true}
                    value={value as number | number[] | null}
                    onChange={onChange}
                    maxItems={field.max_rows}
                    invalid={invalid}
                    label={field.label}
                />
            );
        case 'repeater':
            return (
                <RepeaterInput
                    field={field}
                    value={value}
                    onChange={onChange}
                    errors={errors}
                    path={path}
                />
            );
        case 'group':
            return (
                <fieldset
                    id={id}
                    className="grid gap-4 rounded-md border bg-muted/20 p-3 sm:p-4"
                >
                    <legend className="sr-only">{field.label}</legend>
                    <FieldList
                        fields={field.fields ?? []}
                        values={
                            isRecord(value)
                                ? value
                                : initialValues(field.fields ?? [], {})
                        }
                        onChange={onChange}
                        errors={errors}
                        path={path}
                    />
                </fieldset>
            );
    }
}

type RepeaterProps = {
    field: FieldDefinition;
    value: unknown;
    onChange: (value: unknown) => void;
    errors: Errors;
    path: string;
};

// Rows of nested fields that can be added, removed and moved.
export function RepeaterInput({
    field,
    value,
    onChange,
    errors,
    path,
}: RepeaterProps) {
    const rows = Array.isArray(value)
        ? value.map((row) => (isRecord(row) ? row : {}))
        : [];
    const fields = field.fields ?? [];
    const full = field.max_rows != null && rows.length >= field.max_rows;

    return (
        <div className="grid gap-3">
            {rows.length === 0 && (
                <p className="rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
                    {t('No rows yet.')}
                </p>
            )}
            {rows.map((row, index) => (
                <div
                    key={index}
                    className="grid gap-3 rounded-md border bg-muted/20 p-3 sm:p-4"
                    aria-label={t('{label} row {number}', {
                        label: field.label,
                        number: index + 1,
                    })}
                    role="group"
                >
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-xs font-medium text-muted-foreground tabular-nums">
                            {t('Row {number}', { number: index + 1 })}
                        </span>
                        <div className="flex gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                disabled={index === 0}
                                aria-label={t('Move row {number} up', {
                                    number: index + 1,
                                })}
                                onClick={() =>
                                    onChange(moveItem(rows, index, index - 1))
                                }
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                disabled={index === rows.length - 1}
                                aria-label={t('Move row {number} down', {
                                    number: index + 1,
                                })}
                                onClick={() =>
                                    onChange(moveItem(rows, index, index + 1))
                                }
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label={t('Remove row {number}', {
                                    number: index + 1,
                                })}
                                onClick={() =>
                                    onChange(
                                        rows.filter(
                                            (_, other) => other !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>
                    <FieldList
                        fields={fields}
                        values={row}
                        onChange={(next) =>
                            onChange(
                                rows.map((current, other) =>
                                    other === index ? next : current,
                                ),
                            )
                        }
                        errors={errors}
                        path={`${path}.${index}`}
                    />
                </div>
            ))}
            {!full && (
                <Button
                    type="button"
                    variant="outline"
                    className="justify-self-start"
                    onClick={() =>
                        onChange([...rows, initialValues(fields, {})])
                    }
                >
                    <Plus />
                    {t('Add row')}
                </Button>
            )}
        </div>
    );
}
