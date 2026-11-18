import { Check, Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';

const MESSAGE_MS = 2000;

// A block of code or a shell command with a copy button. The result is read out in a status message.
export function CodeSnippet({ code, label }: { code: string; label: string }) {
    const [message, setMessage] = useState('');
    const [copied, setCopied] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const copy = async () => {
        window.clearTimeout(timer.current);

        try {
            await navigator.clipboard.writeText(code);
            setMessage(t('Copied'));
            setCopied(true);
        } catch {
            setMessage(t('Copy blocked. Select the text and copy it by hand.'));
            setCopied(false);
        }

        timer.current = window.setTimeout(() => {
            setMessage('');
            setCopied(false);
        }, MESSAGE_MS);
    };

    return (
        <div className="relative min-w-0 rounded-md border bg-muted/40">
            <pre className="overflow-x-auto p-3 pe-12 font-mono text-xs leading-relaxed whitespace-pre text-foreground">
                <code>{code}</code>
            </pre>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="absolute end-1 top-1 size-9"
                aria-label={label}
                onClick={copy}
            >
                {copied ? (
                    <Check className="size-4" />
                ) : (
                    <Copy className="size-4" />
                )}
            </Button>
            <span role="status" className="sr-only">
                {message}
            </span>
        </div>
    );
}
