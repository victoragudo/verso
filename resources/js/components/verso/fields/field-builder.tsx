import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronRight,
    Plus,
    Trash,
} from 'lucide-react';
import { useId, useState } from 'react';
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
import { SearchableSelect } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import {
    conditionValueFromText,
    formatOptions,
    hasOptions,
    hasSubFields,
    isRelation,
    moveItem,
    nameFromLabel,
    newField,
    parseOptions,
} from './logic';
import type {
    ChoiceOption,
    ConditionValue,
    FieldDefinition,
    FieldType,
} from './types';

export type BuilderChoices = {
    fieldTypes: ChoiceOption[];
    contentTypes: ChoiceOption[];
    taxonomies: ChoiceOption[];
    roles: ChoiceOption[];
};

type ListProps = {
    fields: FieldDefinition[];
    onChange: (fields: FieldDefinition[]) => void;
    choices: BuilderChoices;
    depth?: number;
    maxDepth: number;
};

const TEXT_TYPES: FieldType[] = [
    'text',
    'textarea',
    'rich_text',
    'email',
    'url',
];
const NONE = '__none';

// The editable list of fields of one level. Fields can be added, moved, removed and nested.
export function FieldBuilderList({
    fields,
    onChange,
    choices,
    depth = 1,
    maxDepth,
}: ListProps) {
    const [newType, setNewType] = useState<FieldType>('text');
    const [openName, setOpenName] = useState<string | null>(null);
    const typeOptions =
        depth >= maxDepth
            ? choices.fieldTypes.filter(
                  (type) => !hasSubFields(type.value as FieldType),
              )
            : choices.fieldTypes;

    const update = (index: number, field: FieldDefinition) =>
        onChange(fields.map((old, other) => (other === index ? field : old)));

    return (
        <div className="grid gap-2">
            {fields.length === 0 && (
                <p className="rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
                    {t('No fields yet.')}
                </p>
            )}
            {fields.map((field, index) => (
                <FieldCard
                    key={index}
                    field={field}
                    siblings={fields}
                    open={openName === `${index}`}
                    onToggle={() =>
                        setOpenName(openName === `${index}` ? null : `${index}`)
                    }
                    onChange={(next) => update(index, next)}
                    onMove={(to) => {
                        onChange(moveItem(fields, index, to));
                        setOpenName(null);
                    }}
                    onRemove={() => {
                        onChange(fields.filter((_, other) => other !== index));
                        setOpenName(null);
                    }}
                    index={index}
                    count={fields.length}
                    choices={{ ...choices, fieldTypes: typeOptions }}
                    depth={depth}
                    maxDepth={maxDepth}
                />
            ))}
            <div className="flex flex-wrap items-center gap-2">
                <SearchableSelect
                    value={newType}
                    onValueChange={(value) => setNewType(value as FieldType)}
                    options={typeOptions}
                    aria-label={t('Type of the new field')}
                    searchPlaceholder={t('Search field types')}
                    className="w-full sm:w-52"
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                        onChange([...fields, newField(newType, fields)]);
                        setOpenName(`${fields.length}`);
                    }}
                >
                    <Plus />
                    {t('Add field')}
                </Button>
            </div>
        </div>
    );
}

type CardProps = {
    field: FieldDefinition;
    siblings: FieldDefinition[];
    open: boolean;
    onToggle: () => void;
    onChange: (field: FieldDefinition) => void;
    onMove: (to: number) => void;
    onRemove: () => void;
    index: number;
    count: number;
    choices: BuilderChoices;
    depth: number;
    maxDepth: number;
};

// The condition value as typed in the builder: booleans become "true" or "false".
function conditionText(value: ConditionValue | undefined): string {
    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    return value === null || value === undefined ? '' : String(value);
}

function numberOrNull(value: string): number | null {
    return value.trim() === '' ? null : Number(value);
}

function FieldCard({
    field,
    siblings,
    open,
    onToggle,
    onChange,
    onMove,
    onRemove,
    index,
    count,
    choices,
    depth,
    maxDepth,
}: CardProps) {
    const id = useId();
    const typeLabel =
        choices.fieldTypes.find((type) => type.value === field.type)?.label ??
        field.type;
    const set = (patch: Partial<FieldDefinition>) =>
        onChange({ ...field, ...patch });
    const watchable = siblings.filter((other) => other.name !== field.name);

    return (
        <div className="rounded-md border bg-background">
            <div className="flex items-center gap-1 px-2 py-1.5">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="min-w-0 flex-1 justify-start gap-2"
                    aria-expanded={open}
                    onClick={onToggle}
                >
                    {open ? <ChevronDown /> : <ChevronRight />}
                    <span className="truncate font-medium text-foreground">
                        {field.label || t('Untitled field')}
                    </span>
                    <span className="hidden truncate font-mono text-xs text-muted-foreground sm:inline">
                        {field.name}
                    </span>
                    <span className="ms-auto shrink-0 text-xs text-muted-foreground">
                        {typeLabel}
                    </span>
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    disabled={index === 0}
                    aria-label={t('Move {label} up', { label: field.label })}
                    onClick={() => onMove(index - 1)}
                >
                    <ArrowUp />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    disabled={index === count - 1}
                    aria-label={t('Move {label} down', { label: field.label })}
                    onClick={() => onMove(index + 1)}
                >
                    <ArrowDown />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={t('Remove {label}', { label: field.label })}
                    onClick={onRemove}
                >
                    <Trash />
                </Button>
            </div>

            {open && (
                <div className="grid gap-4 border-t p-3 sm:grid-cols-2 sm:p-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`${id}-label`}>{t('Label')}</Label>
                        <Input
                            id={`${id}-label`}
                            value={field.label}
                            onChange={(event) => {
                                const label = event.target.value;
                                const auto =
                                    field.name === nameFromLabel(field.label) ||
                                    /^field_\d+$/.test(field.name);

                                set(
                                    auto && nameFromLabel(label) !== ''
                                        ? { label, name: nameFromLabel(label) }
                                        : { label },
                                );
                            }}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${id}-name`}>{t('Name')}</Label>
                        <Input
                            id={`${id}-name`}
                            value={field.name}
                            className="font-mono"
                            onChange={(event) =>
                                set({ name: event.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${id}-type`}>{t('Type')}</Label>
                        <SearchableSelect
                            id={`${id}-type`}
                            value={field.type}
                            onValueChange={(type) => {
                                const next = newField(type as FieldType, []);

                                set({
                                    type: type as FieldType,
                                    options: hasOptions(type as FieldType)
                                        ? (field.options ?? next.options)
                                        : undefined,
                                    fields: hasSubFields(type as FieldType)
                                        ? (field.fields ?? next.fields)
                                        : undefined,
                                    taxonomy:
                                        type === 'term_relation'
                                            ? (field.taxonomy ??
                                              choices.taxonomies[0]?.value ??
                                              null)
                                            : undefined,
                                });
                            }}
                            options={choices.fieldTypes}
                            searchPlaceholder={t('Search field types')}
                            className="w-full"
                        />
                    </div>
                    <label className="flex min-h-10 items-center gap-2.5 self-end text-sm md:min-h-9">
                        <Checkbox
                            checked={field.required}
                            onCheckedChange={(checked) =>
                                set({ required: checked === true })
                            }
                        />
                        {t('Required')}
                    </label>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor={`${id}-help`}>
                            {t('Instructions')}
                        </Label>
                        <Input
                            id={`${id}-help`}
                            value={field.instructions ?? ''}
                            placeholder={t('Shown under the input')}
                            onChange={(event) =>
                                set({ instructions: event.target.value })
                            }
                        />
                    </div>

                    {TEXT_TYPES.includes(field.type) && (
                        <>
                            <NumberSetting
                                label={t('Max length')}
                                value={field.max_length}
                                onChange={(max_length) => set({ max_length })}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-placeholder`}>
                                    {t('Placeholder')}
                                </Label>
                                <Input
                                    id={`${id}-placeholder`}
                                    value={field.placeholder ?? ''}
                                    onChange={(event) =>
                                        set({ placeholder: event.target.value })
                                    }
                                />
                            </div>
                        </>
                    )}

                    {field.type === 'number' && (
                        <>
                            <NumberSetting
                                label={t('Min')}
                                value={field.min}
                                onChange={(min) => set({ min })}
                            />
                            <NumberSetting
                                label={t('Max')}
                                value={field.max}
                                onChange={(max) => set({ max })}
                            />
                        </>
                    )}

                    {hasOptions(field.type) && (
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor={`${id}-options`}>
                                {t('Options')}
                            </Label>
                            <Textarea
                                id={`${id}-options`}
                                rows={4}
                                defaultValue={formatOptions(field.options)}
                                className="font-mono"
                                onChange={(event) =>
                                    set({
                                        options: parseOptions(
                                            event.target.value,
                                        ),
                                    })
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'One per line. Write "value : Label" to show a different label.',
                                )}
                            </p>
                        </div>
                    )}

                    {isRelation(field.type) && (
                        <label className="flex min-h-10 items-center gap-2.5 text-sm md:min-h-9">
                            <Checkbox
                                checked={field.multiple === true}
                                onCheckedChange={(checked) =>
                                    set({ multiple: checked === true })
                                }
                            />
                            {t('Allow many items')}
                        </label>
                    )}

                    {field.type === 'entry_relation' && (
                        <ChoiceList
                            label={t('Content types (none means all)')}
                            options={choices.contentTypes}
                            value={field.content_types ?? []}
                            onChange={(content_types) => set({ content_types })}
                        />
                    )}

                    {field.type === 'term_relation' && (
                        <div className="grid gap-2">
                            <Label htmlFor={`${id}-taxonomy`}>
                                {t('Taxonomy')}
                            </Label>
                            <Select
                                value={field.taxonomy ?? ''}
                                onValueChange={(taxonomy) => set({ taxonomy })}
                            >
                                <SelectTrigger
                                    id={`${id}-taxonomy`}
                                    className="w-full"
                                >
                                    <SelectValue
                                        placeholder={t('Pick a taxonomy')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {choices.taxonomies.map((taxonomy) => (
                                        <SelectItem
                                            key={taxonomy.value}
                                            value={taxonomy.value}
                                        >
                                            {taxonomy.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {field.type === 'user_relation' && (
                        <ChoiceList
                            label={t('Roles (none means all)')}
                            options={choices.roles}
                            value={field.roles ?? []}
                            onChange={(roles) => set({ roles })}
                        />
                    )}

                    {(field.type === 'repeater' ||
                        field.type === 'gallery' ||
                        (isRelation(field.type) && field.multiple)) && (
                        <>
                            <NumberSetting
                                label={
                                    field.type === 'repeater'
                                        ? t('Min rows')
                                        : t('Min items')
                                }
                                value={field.min_rows}
                                onChange={(min_rows) => set({ min_rows })}
                            />
                            <NumberSetting
                                label={
                                    field.type === 'repeater'
                                        ? t('Max rows')
                                        : t('Max items')
                                }
                                value={field.max_rows}
                                onChange={(max_rows) => set({ max_rows })}
                            />
                        </>
                    )}

                    {watchable.length > 0 && (
                        <ConditionSetting
                            field={field}
                            watchable={watchable}
                            onChange={(condition) => set({ condition })}
                        />
                    )}

                    {hasSubFields(field.type) && (
                        <div className="grid gap-2 sm:col-span-2">
                            <span className="text-sm font-medium">
                                {field.type === 'repeater'
                                    ? t('Fields of each row')
                                    : t('Fields of the group')}
                            </span>
                            <div className="border-s-2 ps-3">
                                <FieldBuilderList
                                    fields={field.fields ?? []}
                                    onChange={(fields) => set({ fields })}
                                    choices={choices}
                                    depth={depth + 1}
                                    maxDepth={maxDepth}
                                />
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function NumberSetting({
    label,
    value,
    onChange,
}: {
    label: string;
    value: number | null | undefined;
    onChange: (value: number | null) => void;
}) {
    const id = useId();

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                className="tabular-nums"
                value={value ?? ''}
                onChange={(event) => onChange(numberOrNull(event.target.value))}
            />
        </div>
    );
}

function ChoiceList({
    label,
    options,
    value,
    onChange,
}: {
    label: string;
    options: ChoiceOption[];
    value: string[];
    onChange: (value: string[]) => void;
}) {
    return (
        <fieldset className="grid gap-1 sm:col-span-2">
            <legend className="mb-1 text-sm font-medium">{label}</legend>
            <div className="flex flex-wrap gap-x-4">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="flex min-h-10 items-center gap-2 text-sm md:min-h-8"
                    >
                        <Checkbox
                            checked={value.includes(option.value)}
                            onCheckedChange={(checked) =>
                                onChange(
                                    checked === true
                                        ? [...value, option.value]
                                        : value.filter(
                                              (item) => item !== option.value,
                                          ),
                                )
                            }
                        />
                        {option.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

function ConditionSetting({
    field,
    watchable,
    onChange,
}: {
    field: FieldDefinition;
    watchable: FieldDefinition[];
    onChange: (condition: FieldDefinition['condition']) => void;
}) {
    const id = useId();
    const condition = field.condition ?? null;

    return (
        <fieldset className="grid gap-2 sm:col-span-2">
            <legend className="mb-1 text-sm font-medium">
                {t('Show this field')}
            </legend>
            <div className="grid gap-2 sm:grid-cols-3">
                <Select
                    value={condition?.field ?? NONE}
                    onValueChange={(name) =>
                        onChange(
                            name === NONE
                                ? null
                                : {
                                      field: name,
                                      operator: condition?.operator ?? '==',
                                      value: condition?.value ?? '',
                                  },
                        )
                    }
                >
                    <SelectTrigger
                        aria-label={t('Watched field')}
                        className="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>{t('Always')}</SelectItem>
                        {watchable.map((other) => (
                            <SelectItem key={other.name} value={other.name}>
                                {t('When {label}', { label: other.label })}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {condition && (
                    <>
                        <Select
                            value={condition.operator}
                            onValueChange={(operator) =>
                                onChange({
                                    ...condition,
                                    operator: operator as '==' | '!=',
                                })
                            }
                        >
                            <SelectTrigger
                                aria-label={t('Condition operator')}
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="==">
                                    {t('equals')}
                                </SelectItem>
                                <SelectItem value="!=">
                                    {t('does not equal')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            id={id}
                            aria-label={t('Condition value')}
                            placeholder={t('true, false or a value')}
                            value={conditionText(condition.value)}
                            onChange={(event) =>
                                onChange({
                                    ...condition,
                                    value: conditionValueFromText(
                                        event.target.value,
                                    ),
                                })
                            }
                        />
                    </>
                )}
            </div>
        </fieldset>
    );
}
