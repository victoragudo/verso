import { t } from '@/lib/i18n';
import type { LayoutConditionDefinition, LayoutRule } from './types';

// One line that says where a layout shows, like "Content type: Posts, except Category or tag: Recipes".
export function conditionSummary(
    rules: LayoutRule[],
    definitions: LayoutConditionDefinition[],
): string {
    const describe = (rule: LayoutRule) => {
        const definition = definitions.find((item) => item.key === rule.type);

        if (!definition) {
            return rule.type;
        }

        const value = definition.options?.find(
            (option) => option.value === rule.value,
        );

        return value
            ? t('{rule}: {value}', {
                  rule: definition.label,
                  value: value.label,
              })
            : definition.label;
    };
    const shown = rules.filter((rule) => !rule.exclude).map(describe);
    const hidden = rules.filter((rule) => rule.exclude).map(describe);

    if (shown.length === 0) {
        return t('Nowhere yet. Add a rule.');
    }

    return hidden.length === 0
        ? shown.join(', ')
        : t('{shown}, except {hidden}', {
              shown: shown.join(', '),
              hidden: hidden.join(', '),
          });
}
