import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { StatusBadge } from '@/components/verso/ui';
import { formatList, t, Trans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { SettingValue, SystemSetting } from './types';
import {
    bestUnit,
    formatBytes,
    formatValue,
    fromDisplay,
    rangeProblem,
    serverUploadLimit,
    toDisplay,
    unitChoices,
    unitLabel,
} from './units';

type Props = {
    setting: SystemSetting;
    value: SettingValue;
    error?: string;
    onChange: (value: SettingValue) => void;
    onReset: () => void;
    groupLabel?: string;
};

// The value of a setting as short text, like "10 MB" or "On".
export function describeValue(
    setting: SystemSetting,
    value: SettingValue,
): string {
    if (typeof value === 'boolean') {
        return value ? t('On') : t('Off');
    }

    if (Array.isArray(value)) {
        return formatList(value);
    }

    if (typeof value === 'number') {
        return formatValue(value, setting.unit);
    }

    if (setting.type === 'choice') {
        return (
            setting.options.find((option) => option.value === value)?.label ??
            String(value ?? '')
        );
    }

    return value === null || value === '' ? t('Empty') : String(value);
}

// One row of System settings: label and help on the start side, the control on the end side.
export function SettingField({
    setting,
    value,
    error,
    onChange,
    onReset,
    groupLabel,
}: Props) {
    const id = `setting-${setting.key.replaceAll('.', '-')}`;
    const helpId = `${id}-help`;
    const locked = setting.envLocked;
    const dirty = JSON.stringify(value) !== JSON.stringify(setting.value);

    return (
        <div
            className="grid gap-x-8 gap-y-3 py-4 first:pt-0 last:pb-0 @3xl/settings:grid-cols-[minmax(0,1fr)_minmax(18rem,26rem)]"
            data-setting={setting.key}
        >
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    {setting.type === 'boolean' || setting.type === 'choice' ? (
                        <span
                            id={`${id}-label`}
                            className="text-sm font-medium text-foreground"
                        >
                            {setting.label}
                        </span>
                    ) : (
                        <label
                            htmlFor={id}
                            className="text-sm font-medium text-foreground"
                        >
                            {setting.label}
                        </label>
                    )}
                    {groupLabel && (
                        <StatusBadge tone="neutral">{groupLabel}</StatusBadge>
                    )}
                    {setting.changed && !dirty && (
                        <StatusBadge tone="info">{t('Changed')}</StatusBadge>
                    )}
                    {dirty && (
                        <StatusBadge tone="warning">
                            {t('Not saved')}
                        </StatusBadge>
                    )}
                    {locked && (
                        <StatusBadge tone="neutral">
                            {t('Set by the server environment')}
                        </StatusBadge>
                    )}
                </div>
                <p
                    id={helpId}
                    className="mt-1 max-w-prose text-sm [overflow-wrap:anywhere] text-muted-foreground"
                >
                    {setting.help}{' '}
                    <span>
                        {t('Default: {value}.', {
                            value: describeValue(setting, setting.default),
                        })}
                    </span>
                    {locked && setting.env && (
                        <>
                            {' '}
                            <Trans
                                text={t(
                                    'Change <code>{env}</code> in the server environment to change it.',
                                    { env: setting.env },
                                )}
                                components={{
                                    code: (text) => (
                                        <code className="font-mono text-xs">
                                            {text}
                                        </code>
                                    ),
                                }}
                            />
                        </>
                    )}
                    {setting.apply.length > 0 && (
                        <>
                            {' '}
                            {t('When saved: {actions}.', {
                                actions: formatList(
                                    setting.apply.map((action) => action.label),
                                ),
                            })}
                        </>
                    )}
                </p>
                {setting.problem && (
                    <p className="mt-2 text-sm text-warning-text">
                        {t(
                            'The saved value was ignored: {problem} The default is used.',
                            { problem: setting.problem },
                        )}
                    </p>
                )}
            </div>

            <div className="grid min-w-0 content-start gap-2">
                <Control
                    setting={setting}
                    id={id}
                    helpId={helpId}
                    value={value}
                    onChange={onChange}
                    disabled={locked}
                />
                <InputError message={error} />
                {setting.upload && typeof value === 'number' && (
                    <UploadNote setting={setting} value={value} />
                )}
                {setting.changed && !locked && (
                    <div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="-ms-2 text-muted-foreground"
                            onClick={onReset}
                        >
                            {t('Reset to default')}
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}

type ControlProps = {
    setting: SystemSetting;
    id: string;
    helpId: string;
    value: SettingValue;
    onChange: (value: SettingValue) => void;
    disabled: boolean;
};

function Control({
    setting,
    id,
    helpId,
    value,
    onChange,
    disabled,
}: ControlProps) {
    if (setting.type === 'boolean') {
        return (
            <label
                className={cn(
                    'flex min-h-10 cursor-pointer items-center gap-3 rounded-md border bg-card px-3 text-sm transition-colors hover:bg-accent/50',
                    disabled && 'cursor-not-allowed opacity-60',
                )}
            >
                <Checkbox
                    id={id}
                    checked={value === true}
                    disabled={disabled}
                    aria-labelledby={`${id}-label`}
                    aria-describedby={helpId}
                    onCheckedChange={(checked) => onChange(checked === true)}
                />
                <span>{value === true ? t('On') : t('Off')}</span>
            </label>
        );
    }

    if (setting.type === 'choice') {
        return (
            <fieldset
                aria-labelledby={`${id}-label`}
                aria-describedby={helpId}
                disabled={disabled}
                className="flex flex-wrap gap-2"
            >
                {setting.options.map((option) => (
                    <label
                        key={option.value}
                        className="flex min-h-10 cursor-pointer items-center gap-2 rounded-md border bg-card px-3 text-sm has-checked:border-ring has-checked:bg-accent has-disabled:cursor-not-allowed has-disabled:opacity-60 md:min-h-9"
                    >
                        <input
                            type="radio"
                            name={id}
                            value={option.value}
                            checked={value === option.value}
                            onChange={() => onChange(option.value)}
                            className="accent-primary"
                        />
                        {option.label}
                    </label>
                ))}
            </fieldset>
        );
    }

    if (setting.type === 'integer' || setting.type === 'decimal') {
        return (
            <UnitInput
                setting={setting}
                id={id}
                helpId={helpId}
                value={value}
                onChange={onChange}
                disabled={disabled}
            />
        );
    }

    const text = Array.isArray(value) ? value.join(', ') : String(value ?? '');
    const email = setting.format === 'email';

    return (
        <Input
            id={id}
            type={email ? 'email' : 'text'}
            value={text}
            disabled={disabled}
            aria-describedby={helpId}
            autoComplete="off"
            autoCapitalize="none"
            autoCorrect="off"
            spellCheck={false}
            minLength={
                setting.type === 'text' && setting.min !== null
                    ? setting.min
                    : undefined
            }
            maxLength={
                setting.type === 'text' && setting.max !== null
                    ? setting.max
                    : undefined
            }
            required={setting.type === 'text' && (setting.min ?? 0) > 0}
            onChange={(event) =>
                onChange(
                    setting.type === 'list'
                        ? event.target.value
                              .split(',')
                              .map((item) => item.trim())
                              .filter((item) => item !== '')
                        : event.target.value,
                )
            }
            className="user-invalid:border-destructive"
        />
    );
}

// A number field with a unit picker. The value is stored in the setting unit, the field shows the picked unit.
function UnitInput({
    setting,
    id,
    helpId,
    value,
    onChange,
    disabled,
}: ControlProps) {
    const stored = typeof value === 'number' ? value : NaN;
    const choices = unitChoices(setting.unit);
    const [unit, setUnit] = useState(() =>
        bestUnit(Number.isNaN(stored) ? 0 : stored, setting.unit),
    );
    const [text, setText] = useState(() =>
        Number.isNaN(stored)
            ? ''
            : String(toDisplay(stored, setting.unit, unit)),
    );
    const [touched, setTouched] = useState(false);
    const unitId = useId();
    const problem = touched
        ? rangeProblem(stored, setting.min, setting.max, setting.unit)
        : null;
    const min =
        setting.min === null
            ? undefined
            : toDisplay(setting.min, setting.unit, unit);
    const max =
        setting.max === null
            ? undefined
            : toDisplay(setting.max, setting.unit, unit);

    const changeText = (next: string) => {
        setText(next);
        onChange(
            next.trim() === ''
                ? NaN
                : fromDisplay(Number(next), setting.unit, unit),
        );
    };

    const changeUnit = (next: string) => {
        setUnit(next);

        if (!Number.isNaN(stored)) {
            setText(String(toDisplay(stored, setting.unit, next)));
        }
    };

    return (
        <div className="grid gap-1.5">
            <div className="flex min-w-0 gap-2">
                <Input
                    id={id}
                    type="number"
                    inputMode={
                        setting.type === 'decimal' || unit !== setting.unit
                            ? 'decimal'
                            : 'numeric'
                    }
                    step={
                        setting.type === 'integer' && unit === setting.unit
                            ? 1
                            : 'any'
                    }
                    min={min}
                    max={max}
                    value={text}
                    disabled={disabled}
                    required
                    aria-describedby={
                        problem ? `${helpId} ${id}-range` : helpId
                    }
                    aria-invalid={problem ? true : undefined}
                    onChange={(event) => changeText(event.target.value)}
                    onBlur={() => setTouched(true)}
                    className="min-w-0 flex-1 user-invalid:border-destructive"
                />
                {choices.length > 1 ? (
                    <OptionSelect
                        id={unitId}
                        aria-label={t('Unit of {setting}', {
                            setting: setting.label,
                        })}
                        value={unit}
                        disabled={disabled}
                        onValueChange={changeUnit}
                        options={choices.map((choice) => ({
                            value: choice,
                            label: unitLabel(choice),
                        }))}
                        className="w-auto shrink-0"
                    />
                ) : (
                    unitLabel(setting.unit) !== '' && (
                        <span className="flex h-10 shrink-0 items-center px-1 text-sm text-muted-foreground md:h-9">
                            {unitLabel(setting.unit)}
                        </span>
                    )
                )}
            </div>
            {problem && (
                <p
                    id={`${id}-range`}
                    className="text-sm text-red-600 dark:text-red-400"
                >
                    {problem}
                </p>
            )}
        </div>
    );
}

// The real upload limit, and a warning when the typed value is above what PHP or the web server allows.
function UploadNote({
    setting,
    value,
}: {
    setting: SystemSetting;
    value: number;
}) {
    const upload = setting.upload;

    if (!upload) {
        return null;
    }

    const bytes = fromDisplay(value, 'bytes', setting.unit);
    const server = serverUploadLimit(upload.parts);
    const blocked = server !== null && bytes > server.bytes;
    const label =
        upload.parts.find((part) => part.key === server?.key)?.label ?? '';

    if (blocked && server) {
        return (
            <div
                role="status"
                className="rounded-md border border-warning-text/20 bg-warning-soft px-3 py-2 text-sm text-warning-text"
            >
                <p className="font-medium">{t('Above the server limit')}</p>
                <p className="mt-0.5">
                    <Trans
                        text={t(
                            'Uploads will stop at {size} because of {limit}. Raise it under <link>Server limits</link>.',
                            { size: formatBytes(server.bytes), limit: label },
                        )}
                        components={{
                            link: (text) => (
                                <a
                                    href="#server-limits"
                                    className="underline underline-offset-4"
                                >
                                    {text}
                                </a>
                            ),
                        }}
                    />
                </p>
            </div>
        );
    }

    return (
        <p className="text-xs text-muted-foreground">
            {t('Uploads up to {size} work on this server.', {
                size: formatBytes(Math.min(bytes, server?.bytes ?? bytes)),
            })}
        </p>
    );
}
