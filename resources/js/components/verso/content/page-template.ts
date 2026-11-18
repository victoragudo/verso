import { t } from '@/lib/i18n';
import type { SearchableOption } from '@/lib/option-search';
import type { PageTemplateOption } from '@/types';

// Select value for "no page template": the theme picks the view.
export const DEFAULT_TEMPLATE = 'default';

// Options for the template select. A stored template the theme no longer offers stays visible.
export function templateOptions(
    templates: PageTemplateOption[],
    current: string,
): SearchableOption[] {
    const options: SearchableOption[] = [
        { value: DEFAULT_TEMPLATE, label: t('Default template') },
        ...templates.map((template) => ({
            value: template.value,
            label: template.label,
            keywords: template.description ? [template.description] : [],
        })),
    ];

    if (!options.some((option) => option.value === current)) {
        options.push({
            value: current,
            label: current,
            description: t('Not in the active theme'),
        });
    }

    return options;
}

// The template name saved in meta, or the default value.
export function templateFromMeta(meta: Record<string, unknown> | undefined) {
    const template = meta?.template;

    return typeof template === 'string' && template !== ''
        ? template
        : DEFAULT_TEMPLATE;
}
