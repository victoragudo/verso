import { Check, Copy } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    // The text to copy, like [form slug="contact"].
    value: string;
    // Optional Blade snippet shown in a small disclosure under the shortcode.
    blade?: string | null;
    // Accessible name of the button, like "Copy the shortcode of Contact".
    label?: string;
    // How long the "Copied" message stays, in milliseconds.
    messageMs?: number;
    compact?: boolean;
    className?: string;
};

type CopyState = 'idle' | 'copied' | 'failed';

// Shows a shortcode with a copy button. It uses the Clipboard API and says the result in a status message.
// When the browser denies the clipboard, the text is selected so the user can copy it by hand.
export function CopyShortcode({
    value,
    blade = null,
    label = t('Copy shortcode'),
    messageMs = 2000,
    compact = false,
    className,
}: Props) {
    const [state, setState] = useState<CopyState>('idle');
    const [message, setMessage] = useState('');
    const fieldRef = useRef<HTMLInputElement>(null);
    const timer = useRef<number | undefined>(undefined);
    const fieldId = useId();

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const report = (next: CopyState, text: string) => {
        window.clearTimeout(timer.current);
        setState(next);
        setMessage(text);
        timer.current = window.setTimeout(() => {
            setState('idle');
            setMessage('');
        }, messageMs);
    };

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            report('copied', t('Copied'));
        } catch {
            // The text is selected, so Ctrl+C or Cmd+C copies it.
            fieldRef.current?.focus();
            fieldRef.current?.select();
            report(
                'failed',
                t('Copy blocked. The text is selected: press Ctrl+C or ⌘C.'),
            );
        }
    };

    return (
        <div className={cn('grid min-w-0 gap-1.5', className)}>
            <div className="flex min-w-0 items-center gap-1.5">
                <label htmlFor={fieldId} className="sr-only">
                    {t('Shortcode')}
                </label>
                <input
                    id={fieldId}
                    ref={fieldRef}
                    readOnly
                    value={value}
                    onFocus={(event) => event.currentTarget.select()}
                    className={cn(
                        'min-w-0 flex-1 rounded-md border border-input bg-muted px-2 font-mono text-xs text-foreground',
                        compact ? 'h-7' : 'h-8',
                    )}
                />
                <button
                    type="button"
                    onClick={copy}
                    aria-label={label}
                    data-state={state}
                    className={cn(
                        'inline-flex min-h-7 min-w-7 flex-none items-center justify-center rounded-md border border-input bg-background text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        compact ? 'size-7' : 'size-8',
                        state === 'copied' && 'text-success-text',
                    )}
                >
                    {state === 'copied' ? (
                        <Check aria-hidden="true" className="size-3.5" />
                    ) : (
                        <Copy aria-hidden="true" className="size-3.5" />
                    )}
                </button>
            </div>
            <span
                role="status"
                className={cn(
                    'min-h-4 text-xs transition-opacity motion-reduce:transition-none',
                    message === '' ? 'opacity-0' : 'opacity-100',
                    state === 'failed'
                        ? 'text-danger-text'
                        : 'text-success-text',
                )}
            >
                {message}
            </span>
            {blade && (
                <details className="text-xs text-muted-foreground">
                    <summary className="cursor-pointer select-none">
                        {t('Use it in a theme view')}
                    </summary>
                    <code className="mt-1 block rounded-md bg-muted px-2 py-1.5 font-mono break-all text-foreground">
                        {blade}
                    </code>
                </details>
            )}
        </div>
    );
}
