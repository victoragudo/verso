import { useCallback, useEffect, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { askAssistant } from '../ai-api';
import { friendlyError } from './page-text';
import type { TextChange } from './page-text';
import { pageHelper } from './store';
import type { BusinessResult, PageHelperProps } from './types';

// Shared parts of the helper dialogs: the dialog frame, busy bar, errors, the before/after list and the business field.

type DialogProps = {
    title: string;
    description: ReactNode;
    busy?: boolean;
    // Read to screen reader users inside the dialog, like "4 new texts are ready".
    status?: string;
    children: ReactNode;
    footer: ReactNode;
    onClose: () => void;
};

// A modal dialog of the helper. Esc, the close button and a click outside all close it.
export function HelperDialog({
    title,
    description,
    busy = false,
    status = '',
    children,
    footer,
    onClose,
}: DialogProps) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent
                className="flex max-h-[90dvh] w-[calc(100vw-2rem)] flex-col gap-4 overscroll-contain sm:max-w-2xl"
                data-test="ai-helper-dialog"
                onCloseAutoFocus={(event) => {
                    if (pageHelper.returnFocus()) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <div
                    className="-mx-1 flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto overscroll-contain px-1 py-1"
                    aria-busy={busy}
                >
                    {children}
                </div>
                <p role="status" className="sr-only">
                    {status}
                </p>
                <DialogFooter className="flex-wrap gap-2">
                    {footer}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// A progress bar while the helper works. With a value it shows how far it got.
export function BusyBar({
    label,
    value,
    max,
}: {
    label: string;
    value?: number;
    max?: number;
}) {
    const id = useId();

    return (
        <div className="flex flex-col gap-2" data-test="ai-helper-busy">
            <span id={id} className="text-sm text-muted-foreground">
                {label}
            </span>
            <progress
                aria-labelledby={id}
                value={value}
                max={max}
                className="h-1.5 w-full overflow-hidden rounded-full accent-blue-600 [&::-moz-progress-bar]:bg-blue-600 [&::-webkit-progress-bar]:bg-muted [&::-webkit-progress-value]:bg-blue-600"
            />
        </div>
    );
}

export function HelperError({ message }: { message: string | null }) {
    if (!message) {
        return null;
    }

    return (
        <p
            role="alert"
            className="rounded-lg border border-danger-text/20 bg-danger-soft px-3 py-2 text-sm text-danger-text"
        >
            {message}
        </p>
    );
}

// Each changed text as "Now" and "New", side by side when there is room.
export function ChangesPreview({ changes }: { changes: TextChange[] }) {
    return (
        <ol
            className="flex flex-col gap-2"
            aria-label={t('ai-assistant::Changes')}
            data-test="ai-helper-changes"
        >
            {changes.map((change) => (
                <li
                    key={change.key}
                    className="@container rounded-lg border bg-card p-3"
                >
                    <div className="grid gap-3 @lg:grid-cols-2">
                        <div className="flex flex-col gap-1">
                            <span className="text-xs font-medium text-muted-foreground">
                                {t('ai-assistant::Now')}
                            </span>
                            <del className="text-sm break-words text-muted-foreground no-underline">
                                {change.before || t('ai-assistant::(empty)')}
                            </del>
                        </div>
                        <div className="flex flex-col gap-1">
                            <span className="text-xs font-medium text-blue-700 dark:text-blue-400">
                                {t('ai-assistant::New')}
                            </span>
                            <ins className="text-sm break-words no-underline">
                                {change.after || t('ai-assistant::(empty)')}
                            </ins>
                        </div>
                    </div>
                </li>
            ))}
        </ol>
    );
}

// The text box for "What does your business do?". The value is kept for every page.
export function BusinessField({
    value,
    max,
    disabled,
    onChange,
}: {
    value: string;
    max: number;
    disabled?: boolean;
    onChange: (value: string) => void;
}) {
    const id = useId();

    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>
                {t('ai-assistant::What does your business do?')}
            </Label>
            <Textarea
                id={id}
                value={value}
                maxLength={max}
                rows={3}
                disabled={disabled}
                aria-describedby={`${id}-hint`}
                placeholder={t(
                    'ai-assistant::Family bakery by the harbor. Sourdough, cinnamon buns and coffee from 7am.',
                )}
                onChange={(event) => onChange(event.target.value)}
            />
            <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                {t(
                    'ai-assistant::One or two sentences are enough. The helper remembers it for your other pages.',
                )}
            </p>
        </div>
    );
}

// Runs one helper request at a time, with a plain error message. Leaving the dialog cancels it.
export function useHelperTask() {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const abort = useRef<AbortController | null>(null);

    useEffect(() => () => abort.current?.abort(), []);

    const run = useCallback(
        async <T,>(task: (signal: AbortSignal) => Promise<T>) => {
            abort.current?.abort();
            const controller = new AbortController();
            abort.current = controller;
            setBusy(true);
            setError(null);

            try {
                return await task(controller.signal);
            } catch (caught) {
                if (!controller.signal.aborted) {
                    setError(friendlyError(caught));
                }

                return null;
            } finally {
                if (abort.current === controller) {
                    setBusy(false);
                }
            }
        },
        [],
    );

    return { busy, error, run };
}

// The business description of the dialog. save() keeps a changed one on the server first.
export function useBusiness(config: PageHelperProps) {
    const [business, setBusiness] = useState(config.business);

    const save = useCallback(
        async (signal: AbortSignal): Promise<string> => {
            const text = business.trim();

            if (text === config.business.trim()) {
                return text;
            }

            const saved = await askAssistant<BusinessResult>(
                config.endpoints.business,
                { business: text },
                signal,
                'PUT',
            );
            pageHelper.setBusiness(saved.business);

            return saved.business;
        },
        [business, config.business, config.endpoints.business],
    );

    return { business, setBusiness, save };
}
