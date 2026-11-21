import { Fragment, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import { snapshot, subscribe } from '@/lib/i18n';

// The outer layout of every page. When the admin language changes, the
// layouts and the page inside render again from the start with the new
// strings. It sits inside the Inertia app, so the app keeps its current page.
export function LocaleBoundary({ children }: { children?: ReactNode }) {
    const { locale } = useSyncExternalStore(subscribe, snapshot, snapshot);

    return <Fragment key={locale}>{children}</Fragment>;
}
