import { router } from '@inertiajs/react';
import { DirectionProvider } from '@radix-ui/react-direction';
import { Fragment, useEffect, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import { currentLocale, loadLocale, snapshot, subscribe } from '@/lib/i18n';
import type { I18nProps } from '@/types';

// Keeps the loaded strings in step with the page language. It sits outside
// the Inertia page, so it listens to navigations instead of usePage(). When
// the language changes (after login or a switch), the new strings load and
// the app renders again from the top, so no component keeps old text.
export function I18nRoot({ children }: { children: ReactNode }) {
    const state = useSyncExternalStore(subscribe, snapshot, snapshot);

    useEffect(
        () =>
            router.on('navigate', (event) => {
                const i18n = event.detail.page.props.i18n as
                    | I18nProps
                    | undefined;

                if (i18n && i18n.locale !== currentLocale()) {
                    void loadLocale(i18n.locale, i18n.bundle, i18n.dir);
                }
            }),
        [],
    );

    return (
        <DirectionProvider dir={state.dir}>
            <Fragment key={state.locale}>{children}</Fragment>
        </DirectionProvider>
    );
}
