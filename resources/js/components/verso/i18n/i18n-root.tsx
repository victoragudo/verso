import { router } from '@inertiajs/react';
import { DirectionProvider } from '@radix-ui/react-direction';
import { useEffect, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import { currentLocale, loadLocale, snapshot, subscribe } from '@/lib/i18n';
import type { I18nProps } from '@/types';

// Keeps the loaded strings in step with the page language. It sits outside
// the Inertia page, so it listens to visits instead of usePage(). When the
// language changes (after login or a switch), the new strings load and
// LocaleBoundary renders the page again. The app itself is never mounted
// again: Inertia would start over from the first page it got.
export function I18nRoot({ children }: { children: ReactNode }) {
    const state = useSyncExternalStore(subscribe, snapshot, snapshot);

    useEffect(() => {
        const follow = (page: { props: Record<string, unknown> }) => {
            const i18n = page.props.i18n as I18nProps | undefined;

            if (i18n && i18n.locale !== currentLocale()) {
                void loadLocale(i18n.locale, i18n.bundle, i18n.dir);
            }
        };
        // A visit back to the same URL (like the language switch) replaces the
        // history entry and fires no "navigate", so "success" is needed too.
        const stopNavigate = router.on('navigate', (event) =>
            follow(event.detail.page),
        );
        const stopSuccess = router.on('success', (event) =>
            follow(event.detail.page),
        );

        return () => {
            stopNavigate();
            stopSuccess();
        };
    }, []);

    return <DirectionProvider dir={state.dir}>{children}</DirectionProvider>;
}
