import type { StatusTone } from '@/components/verso/ui';
import { currentLocale, msg } from '@/lib/i18n';
import type { SitePartStatus } from '@/types';

// Plain words and a color for each state of a header, footer or other part.
export const PART_STATUS: Record<
    SitePartStatus,
    { label: string; tone: StatusTone }
> = {
    original: { label: msg('Theme original'), tone: 'neutral' },
    unpublished: { label: msg('Changes not published'), tone: 'warning' },
    published: { label: msg('Published'), tone: 'success' },
};

const NOUN_CAPITALIZING_LOCALES = ['de'];

export function partNameInSentence(label: string): string {
    const locale = currentLocale();

    return NOUN_CAPITALIZING_LOCALES.includes(locale)
        ? label
        : label.toLocaleLowerCase(locale);
}
