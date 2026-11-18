import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/verso/ui';
import { t } from '@/lib/i18n';

// The sticky row with the save button at the end of each settings form.
export function SaveBar({
    dirty,
    processing,
    label,
}: {
    dirty: boolean;
    processing: boolean;
    label: string;
}) {
    return (
        <div className="sticky bottom-4 z-10 flex flex-wrap items-center justify-end gap-3 rounded-lg border bg-card px-4 py-3 shadow-xs">
            {dirty && (
                <StatusBadge tone="warning" className="me-auto">
                    {t('Unsaved changes')}
                </StatusBadge>
            )}
            <Button
                type="submit"
                disabled={processing}
                className="min-h-10 md:min-h-9"
            >
                {label}
            </Button>
        </div>
    );
}

// Moves focus to the first field the server marked as invalid, so its error is read out.
export function focusFirstError(form: HTMLFormElement | null): void {
    requestAnimationFrame(() => {
        form?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
    });
}
