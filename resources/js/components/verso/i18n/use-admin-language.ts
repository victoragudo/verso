import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { languageName } from '@/lib/i18n';
import { update } from '@/routes/verso/locale';
import type { AdminLocale } from '@/types';

// Value of the "follow the site language" choice.
export const SITE_LANGUAGE = '';

// The admin languages and a way to pick one. Picking saves it on the user
// (or in the session for guests) and loads the page again in that language.
export function useAdminLanguage() {
    const i18n = usePage().props.i18n;
    const [saving, setSaving] = useState(false);
    const locales: AdminLocale[] = i18n?.locales ?? [];
    const current = i18n?.locale ?? 'en';
    const chosen = i18n?.userLocale ?? SITE_LANGUAGE;
    const site = locales.find((locale) => locale.code === i18n?.siteLocale);

    const choose = (locale: string) => {
        if (
            locale === chosen &&
            (locale !== SITE_LANGUAGE || !i18n?.userLocale)
        ) {
            return;
        }

        setSaving(true);
        router.put(
            update.url(),
            { locale },
            {
                preserveScroll: true,
                preserveState: false,
                onFinish: () => setSaving(false),
            },
        );
    };

    return {
        locales,
        current,
        chosen,
        siteName: site ? site.name : languageName(i18n?.siteLocale ?? 'en'),
        saving,
        choose,
    };
}
