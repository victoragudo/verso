import { t } from '@/lib/i18n';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { TOUCH_TARGET } from './touch';

type Props = {
    open: boolean;
    starterLabel: string;
    onConfirm: () => void;
    onClose: () => void;
};

// Asks before a starter is added to a site that already has pages. A native modal dialog: Esc and the back gesture close it.
export function ConfirmStarterDialog({
    open,
    starterLabel,
    onConfirm,
    onClose,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);

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
            aria-labelledby="confirm-starter-title"
            aria-describedby="confirm-starter-text"
            onClose={onClose}
            className="verso-dialog m-auto w-[min(32rem,calc(100%-2rem))] rounded-lg border bg-background p-5 text-foreground shadow-lg"
        >
            <h2
                id="confirm-starter-title"
                className="text-lg font-semibold text-foreground"
            >
                {t('Add the {site} site?', { site: starterLabel })}
            </h2>
            <p
                id="confirm-starter-text"
                className="mt-2 text-sm text-muted-foreground"
            >
                {t(
                    'This adds new pages and replaces your main menu. Your existing pages stay.',
                )}
            </p>
            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    autoFocus
                    onClick={() => dialog.current?.close()}
                    className={TOUCH_TARGET}
                >
                    {t('Cancel')}
                </Button>
                <Button
                    type="button"
                    onClick={onConfirm}
                    className={TOUCH_TARGET}
                >
                    {t('Add the pages')}
                </Button>
            </div>
        </dialog>
    );
}
