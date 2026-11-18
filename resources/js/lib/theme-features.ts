import { t } from '@/lib/i18n';
import type { ThemeInfo } from '@/types';

const FEATURE_SEPARATOR = ' · ';

export function themeFeatures(theme: ThemeInfo): string {
    const features = [
        theme.templates > 0 &&
            t('{count, plural, one {# template} other {# templates}}', {
                count: theme.templates,
            }),
        theme.patterns > 0 &&
            t('{count, plural, one {# pattern} other {# patterns}}', {
                count: theme.patterns,
            }),
        theme.parts > 0 &&
            t('{count, plural, one {# part} other {# parts}}', {
                count: theme.parts,
            }),
    ];

    return features
        .filter((item): item is string => typeof item === 'string')
        .join(FEATURE_SEPARATOR);
}
