import { t } from '@/lib/i18n';
import { useEffect, useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { normalizeHex } from '@/lib/easy/color';
import { cn } from '@/lib/utils';
import { TOUCH_TARGET } from './touch';

// A full six digit code. Short codes like #fff are taken when the box loses focus, so typing is not cut short.
const FULL_HEX = /^#[0-9a-f]{6}$/i;

type Props = {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    // Called when a box loses focus, so the next change is a new Undo step.
    onDone?: () => void;
};

// One palette color: the native color picker plus a box for the color code, like #2e5e4e.
export function ColorField({ label, value, error, onChange, onDone }: Props) {
    const id = useId();
    const [draft, setDraft] = useState(value);
    const invalid = normalizeHex(draft) === null;

    useEffect(() => setDraft(value), [value]);

    const type = (text: string) => {
        setDraft(text);

        if (FULL_HEX.test(text.trim())) {
            onChange(text.trim().toLowerCase());
        }
    };

    const leave = () => {
        const color = normalizeHex(draft);

        if (color && color !== value) {
            onChange(color);
        }

        setDraft(color ?? value);
        onDone?.();
    };

    return (
        <div className="grid gap-1.5">
            <label htmlFor={`${id}-code`} className="text-sm font-medium">
                {label}
            </label>
            <div className="flex items-center gap-2">
                <input
                    type="color"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    onBlur={onDone}
                    aria-label={t('{color}: pick a color', { color: label })}
                    className="size-11 shrink-0 cursor-pointer rounded-md border bg-transparent p-1 pointer-fine:md:size-9"
                />
                <Input
                    id={`${id}-code`}
                    value={draft}
                    onChange={(event) => type(event.target.value)}
                    onBlur={leave}
                    spellCheck={false}
                    autoComplete="off"
                    aria-invalid={invalid || undefined}
                    aria-describedby={invalid ? `${id}-hint` : undefined}
                    className={cn(TOUCH_TARGET, 'font-mono')}
                />
            </div>
            {invalid && (
                <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                    {t('Use a color code like {example}.', {
                        example: '#2e5e4e',
                    })}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
