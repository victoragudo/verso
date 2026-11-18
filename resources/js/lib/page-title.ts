import type { Page } from '@inertiajs/core';

const FALLBACK_NAME = 'Verso';

// Browser tab title: the page title followed by the site name from the shared props.
export function pageTitle(title: string, page?: Page): string {
    const siteName = page?.props.siteName;
    const name =
        typeof siteName === 'string' && siteName.trim() !== ''
            ? siteName
            : FALLBACK_NAME;

    return title ? `${title} - ${name}` : name;
}
