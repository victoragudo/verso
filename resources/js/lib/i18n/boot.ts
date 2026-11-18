// Loads the first language before the app renders. The server prints the
// bundle as a preload link and the language on the html element.
import { loadLocale } from './store';
import type { TextDirection } from './store';

export const BUNDLE_LINK_SELECTOR = 'link[data-verso-i18n]';

export async function bootI18n(): Promise<void> {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    const link = document.querySelector<HTMLLinkElement>(BUNDLE_LINK_SELECTOR);
    const dir: TextDirection = root.dir === 'rtl' ? 'rtl' : 'ltr';

    await loadLocale(
        root.lang || 'en',
        link?.getAttribute('href') ?? null,
        dir,
    );
}
