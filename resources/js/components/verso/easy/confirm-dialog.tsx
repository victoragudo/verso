import { useEffect, useId, useRef } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { TOUCH_TARGET } from './touch';

type Props = {
    open: boolean;
    title: string;
    children: ReactNode;
    confirmLabel: string;
    cancelLabel?: string;
    // Red confirm button for actions that throw work away.
    danger?: boolean;
    onConfirm: () => void;
    onClose: () => void;
};

// A plain yes or no question in a native modal dialog. Esc and the back gesture close it, Cancel has the focus.
export function ConfirmDialog({
    open,
    title,
    children,
    confirmLabel,
    cancelLabel,
    danger = false,
    onConfirm,
    onClose,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const id = useId();

    useEffect(() => {
        const element = dialog.current;

        if (!element) {
            return;
        }

        if (open && !element.open) {
            element.showModal();
        } else if (!open && element.open) {
            element.close();
        }
    }, [open]);

    return (
        <dialog
            ref={dialog}
            aria-labelledby={`${id}-title`}
            aria-describedby={`${id}-text`}
            onClose={onClose}
            className="verso-dialog m-auto w-[min(32rem,calc(100%-2rem))] rounded-lg border bg-background p-5 text-foreground shadow-lg"
        >
            <h2
                id={`${id}-title`}
                className="text-lg font-semibold text-foreground"
            >
                {title}
            </h2>
            <div
                id={`${id}-text`}
                className="mt-2 text-sm text-muted-foreground"
            >
                {children}
            </div>
            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    autoFocus
                    onClick={() => dialog.current?.close()}
                    className={TOUCH_TARGET}
                >
                    {cancelLabel ?? t('Cancel')}
                </Button>
                <Button
                    type="button"
                    variant={danger ? 'destructive' : 'default'}
                    onClick={() => {
                        dialog.current?.close();
                        onConfirm();
                    }}
                    className={TOUCH_TARGET}
                    data-test="confirm-dialog-yes"
                >
                    {confirmLabel}
                </Button>
            </div>
        </dialog>
    );
}
