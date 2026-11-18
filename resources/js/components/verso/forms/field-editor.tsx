import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { SearchableSelect } from '@/components/verso/ui';
import { formatNumber, msg, t } from '@/lib/i18n';
import {
    LENGTH_TYPES,
    OPTION_TYPES,
    nameFromLabel,
    parseOptions,
} from './builder';
import type { BuilderLimits, FormField, FormFieldType } from './types';

type Props = {
    field: FormField;
    index: number;
    count: number;
    fieldTypes: { value: string; label: string }[];
    limits: BuilderLimits;
    errors: Record<string, string | undefined>;
    onChange: (field: FormField) => void;
    onMove: (step: number) => void;
    onRemove: () => void;
};

const WIDTH_OPTIONS = [
    { value: 'full', label: msg('forms::Full width') },
    { value: 'half', label: msg('forms::Half width') },
];

// Settings of one form field in the builder: type, label, name, rules and choices.
export function FieldEditor({
    field,
    index,
    count,
    fieldTypes,
    limits,
    errors,
    onChange,
    onMove,
    onRemove,
}: Props) {
    const id = `field-${field.id}`;
    const error = (key: string) => errors[`fields.${index}.${key}`];
    const set = <K extends keyof FormField>(key: K, value: FormField[K]) =>
        onChange({ ...field, [key]: value });
    const typeLabel =
        fieldTypes.find((type) => type.value === field.type)?.label ??
        field.type;
    const acceptError = Object.entries(errors).find(([key]) =>
        key.startsWith(`fields.${index}.accept`),
    )?.[1];

    // Keeps the name in step with the label while the name still follows the old label.
    const changeLabel = (label: string) => {
        const followsLabel = field.name === nameFromLabel(field.label);
        onChange({
            ...field,
            label,
            name: followsLabel ? nameFromLabel(label) : field.name,
        });
    };

    const numberOrNull = (value: string) =>
        value === '' ? null : Math.max(0, Number(value));

    return (
        <li className="rounded-lg border bg-card shadow-xs">
            <div className="flex flex-wrap items-center gap-2 border-b px-4 py-2.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase tabular-nums">
                    {t('forms::{position, number}. {type}', {
                        position: index + 1,
                        type: typeLabel,
                    })}
                </span>
                <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                    {field.label}
                </span>
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-10"
                        disabled={index === 0}
                        onClick={() => onMove(-1)}
                        aria-label={t('forms::Move {label} up', {
                            label: field.label,
                        })}
                    >
                        <ArrowUp className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-10"
                        disabled={index === count - 1}
                        onClick={() => onMove(1)}
                        aria-label={t('forms::Move {label} down', {
                            label: field.label,
                        })}
                    >
                        <ArrowDown className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-10 text-danger-text"
                        onClick={onRemove}
                        aria-label={t('forms::Remove {label}', {
                            label: field.label,
                        })}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </div>

            <div className="grid gap-4 p-4 sm:grid-cols-2">
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor={`${id}-label`}>
                        {field.type === 'consent'
                            ? t('forms::Consent text')
                            : t('forms::Label')}
                    </Label>
                    {field.type === 'consent' ? (
                        <Textarea
                            id={`${id}-label`}
                            rows={2}
                            value={field.label}
                            onChange={(event) =>
                                set('label', event.target.value)
                            }
                            aria-invalid={Boolean(error('label'))}
                        />
                    ) : (
                        <Input
                            id={`${id}-label`}
                            value={field.label}
                            onChange={(event) =>
                                changeLabel(event.target.value)
                            }
                            aria-invalid={Boolean(error('label'))}
                        />
                    )}
                    <InputError message={error('label')} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${id}-type`}>{t('forms::Type')}</Label>
                    <SearchableSelect
                        id={`${id}-type`}
                        value={field.type}
                        onValueChange={(value) =>
                            set('type', value as FormFieldType)
                        }
                        options={fieldTypes}
                        searchPlaceholder={t('forms::Search field types')}
                        className="w-full"
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${id}-name`}>{t('Name')}</Label>
                    <Input
                        id={`${id}-name`}
                        value={field.name}
                        className="font-mono text-sm"
                        autoCapitalize="none"
                        autoCorrect="off"
                        spellCheck={false}
                        onChange={(event) => set('name', event.target.value)}
                        aria-invalid={Boolean(error('name'))}
                        aria-describedby={`${id}-name-help`}
                    />
                    <p
                        id={`${id}-name-help`}
                        className="text-xs text-muted-foreground"
                    >
                        {t(
                            'forms::Used in the CSV and as {placeholder} in emails.',
                            {
                                placeholder: `{${field.name}}`,
                            },
                        )}
                    </p>
                    <InputError message={error('name')} />
                </div>

                {field.type === 'hidden' ? (
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor={`${id}-value`}>
                            {t('forms::Value')}
                        </Label>
                        <Input
                            id={`${id}-value`}
                            value={field.value}
                            onChange={(event) =>
                                set('value', event.target.value)
                            }
                            aria-describedby={`${id}-value-help`}
                        />
                        <p
                            id={`${id}-value-help`}
                            className="text-xs text-muted-foreground"
                        >
                            {t(
                                'forms::Saved with every message, for example the campaign that brought the visitor.',
                            )}
                        </p>
                        <InputError message={error('value')} />
                    </div>
                ) : (
                    <>
                        {field.type !== 'consent' && (
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-placeholder`}>
                                    {t('forms::Placeholder')}
                                </Label>
                                <Input
                                    id={`${id}-placeholder`}
                                    value={field.placeholder}
                                    onChange={(event) =>
                                        set('placeholder', event.target.value)
                                    }
                                />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor={`${id}-width`}>
                                {t('forms::Width')}
                            </Label>
                            <SearchableSelect
                                id={`${id}-width`}
                                value={field.width}
                                onValueChange={(value) =>
                                    set(
                                        'width',
                                        value === 'half' ? 'half' : 'full',
                                    )
                                }
                                options={WIDTH_OPTIONS.map((option) => ({
                                    ...option,
                                    label: t(option.label),
                                }))}
                                className="w-full"
                            />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor={`${id}-help`}>
                                {t('forms::Help text')}
                            </Label>
                            <Input
                                id={`${id}-help`}
                                value={field.help}
                                onChange={(event) =>
                                    set('help', event.target.value)
                                }
                            />
                        </div>
                        <label className="flex min-h-10 items-center gap-2.5 text-sm sm:col-span-2">
                            <Checkbox
                                checked={field.required}
                                onCheckedChange={(value) =>
                                    set('required', value === true)
                                }
                            />
                            {t('forms::Required')}
                        </label>
                    </>
                )}

                {OPTION_TYPES.includes(field.type) && (
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor={`${id}-options`}>
                            {t('forms::Options')}
                        </Label>
                        <Textarea
                            id={`${id}-options`}
                            rows={4}
                            defaultValue={field.options.join('\n')}
                            onBlur={(event) =>
                                set('options', parseOptions(event.target.value))
                            }
                            aria-invalid={Boolean(error('options'))}
                            aria-describedby={`${id}-options-help`}
                        />
                        <p
                            id={`${id}-options-help`}
                            className="text-xs text-muted-foreground"
                        >
                            {t(
                                'forms::One option per line, up to {count, number}.',
                                {
                                    count: limits.max_options,
                                },
                            )}
                        </p>
                        <InputError message={error('options')} />
                    </div>
                )}

                {LENGTH_TYPES.includes(field.type) && (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor={`${id}-min`}>
                                {t('forms::Fewest characters')}
                            </Label>
                            <Input
                                id={`${id}-min`}
                                type="number"
                                inputMode="numeric"
                                min={0}
                                className="tabular-nums"
                                value={field.min_length ?? ''}
                                onChange={(event) =>
                                    set(
                                        'min_length',
                                        numberOrNull(event.target.value),
                                    )
                                }
                            />
                            <InputError message={error('min_length')} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`${id}-max`}>
                                {t('forms::Most characters')}
                            </Label>
                            <Input
                                id={`${id}-max`}
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={limits.textarea_max}
                                className="tabular-nums"
                                value={field.max_length ?? ''}
                                onChange={(event) =>
                                    set(
                                        'max_length',
                                        numberOrNull(event.target.value),
                                    )
                                }
                            />
                            <InputError message={error('max_length')} />
                        </div>
                    </>
                )}

                {field.type === 'file' && (
                    <>
                        <fieldset className="grid gap-2 sm:col-span-2">
                            <legend className="mb-1 text-sm font-medium">
                                {t('forms::Allowed file types')}
                            </legend>
                            <div className="flex flex-wrap gap-x-4 gap-y-1">
                                {limits.upload_types.map((type) => (
                                    <label
                                        key={type}
                                        className="flex min-h-10 items-center gap-2 text-sm uppercase"
                                    >
                                        <Checkbox
                                            checked={field.accept.includes(
                                                type,
                                            )}
                                            onCheckedChange={(value) =>
                                                set(
                                                    'accept',
                                                    value === true
                                                        ? [
                                                              ...field.accept,
                                                              type,
                                                          ]
                                                        : field.accept.filter(
                                                              (item) =>
                                                                  item !== type,
                                                          ),
                                                )
                                            }
                                        />
                                        {type}
                                    </label>
                                ))}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'forms::None ticked allows every type in the list.',
                                )}
                            </p>
                            <InputError message={acceptError} />
                        </fieldset>
                        <div className="grid gap-2">
                            <Label htmlFor={`${id}-kb`}>
                                {t('forms::Largest file (KB)')}
                            </Label>
                            <Input
                                id={`${id}-kb`}
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={limits.upload_max_kb}
                                placeholder={formatNumber(
                                    limits.upload_default_kb,
                                )}
                                className="tabular-nums"
                                value={field.max_kb ?? ''}
                                onChange={(event) =>
                                    set(
                                        'max_kb',
                                        numberOrNull(event.target.value),
                                    )
                                }
                            />
                            <InputError message={error('max_kb')} />
                        </div>
                    </>
                )}
            </div>
        </li>
    );
}
