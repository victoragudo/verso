import { router } from '@inertiajs/react';
import { type RefObject, useEffect } from 'react';
import { msg, t } from '@/lib/i18n';

export const LEAVE_MESSAGE = msg(
    'You have unsaved changes. Leave this page and lose them?',
);

// Asks before leaving while there are unsaved changes: on Inertia visits and on tab close or reload.
// Visits that start while `allowVisit` is true (like saving) pass without a question.
export function useUnsavedChanges(
    dirty: boolean,
    allowVisit: RefObject<boolean>,
): void {
    useEffect(() => {
        if (!dirty) {
            return;
        }

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };

        window.addEventListener('beforeunload', onBeforeUnload);

        const stopListening = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (allowVisit.current || visit.prefetch || visit.only.length > 0) {
                return;
            }

            if (!window.confirm(t(LEAVE_MESSAGE))) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            stopListening();
        };
    }, [dirty, allowVisit]);
}
