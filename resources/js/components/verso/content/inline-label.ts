import { currentLocale } from '@/lib/i18n';

const LOCALES_WITH_CAPITAL_NOUNS = new Set(['de']);

export function inlineLabel(label: string): string {
    const locale = currentLocale();

    if (LOCALES_WITH_CAPITAL_NOUNS.has(locale.split('-')[0])) {
        return label;
    }

    return label.toLocaleLowerCase(locale);
}
