import { currentLocale, msg, t } from '@/lib/i18n';
import type { SearchableOption } from '@/lib/option-search';

const GENERAL_GROUP = msg('General');
const ZERO_OFFSET = 'UTC+00:00';
const DISPLAY_LOCALE = 'en';

// Turns "GMT+1:00" style names into "UTC+01:00".
function formatOffset(name: string): string {
    const match = /([+-])(\d{1,2})(?::(\d{2}))?/.exec(name);

    if (!match) {
        return ZERO_OFFSET;
    }

    return `UTC${match[1]}${match[2].padStart(2, '0')}:${match[3] ?? '00'}`;
}

// The current UTC offset of a time zone, or null when the zone is unknown.
function timezoneOffset(timezone: string, now: Date): string | null {
    try {
        const part = new Intl.DateTimeFormat(DISPLAY_LOCALE, {
            timeZone: timezone,
            timeZoneName: 'longOffset',
        })
            .formatToParts(now)
            .find((item) => item.type === 'timeZoneName');

        return part ? formatOffset(part.value) : null;
    } catch {
        return null;
    }
}

// Time zone options grouped by region, with the city as label and the offset.
export function timezoneOptions(
    timezones: string[],
    now = new Date(),
): SearchableOption[] {
    return timezones.map((timezone) => {
        const slash = timezone.indexOf('/');
        const group =
            slash === -1 ? t(GENERAL_GROUP) : timezone.slice(0, slash);
        const city = slash === -1 ? timezone : timezone.slice(slash + 1);
        const offset = timezoneOffset(timezone, now);

        return {
            value: timezone,
            label: city.replaceAll('_', ' '),
            group,
            description: offset ?? undefined,
            keywords: offset ? [offset] : undefined,
        };
    });
}

// The name of a language in a given language, or null when Intl does not know it.
function languageName(code: string, inLocale: string): string | null {
    try {
        const name = new Intl.DisplayNames([inLocale], { type: 'language' }).of(
            code,
        );

        return name && name !== code ? name : null;
    } catch {
        return null;
    }
}

// Upper cases the first letter, as native names are often lower case.
function capitalize(text: string, locale: string): string {
    return text.charAt(0).toLocaleUpperCase(locale) + text.slice(1);
}

// Language options with the native name as label and "English name · code" below.
export function localeOptions(locales: string[]): SearchableOption[] {
    return locales.map((code) => {
        const tag = code.replaceAll('_', '-');
        const nativeName = languageName(tag, tag);
        const englishName = languageName(tag, DISPLAY_LOCALE);
        const adminName = languageName(tag, currentLocale()) ?? englishName;
        const keywords = [
            ...new Set(
                [englishName, adminName].filter(
                    (name): name is string => name !== null,
                ),
            ),
        ];

        return {
            value: code,
            label: nativeName ? capitalize(nativeName, tag) : code,
            description: adminName ? `${adminName} · ${code}` : code,
            keywords: keywords.length > 0 ? keywords : undefined,
        };
    });
}
