import type { TextDirection } from '@/lib/i18n';

export type AdminLocale = {
    code: string;
    name: string;
    englishName: string;
    dir: TextDirection;
};

// The language data every page gets from the server.
export type I18nProps = {
    locale: string;
    dir: TextDirection;
    bundle: string | null;
    fallback: string;
    siteLocale: string;
    // Language and direction of the site content, used by the editor canvas.
    contentLang: string;
    contentDir: TextDirection;
    userLocale: string | null;
    locales: AdminLocale[];
};
