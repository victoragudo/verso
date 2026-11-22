import { Plus, Trash } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { OptionSelect, SearchableSelect } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import type { LayoutConditionDefinition, LayoutRule } from './types';

type Props = {
    rules: LayoutRule[];
    definitions: LayoutConditionDefinition[];
    onChange: (rules: LayoutRule[]) => void;
};

// The rules that say where a layout shows. A layout shows when one rule matches and no "except" rule matches.
export function ConditionsEditor({ rules, definitions, onChange }: Props) {
    const id = useId();
    const set = (index: number, rule: LayoutRule) =>
        onChange(rules.map((item, at) => (at === index ? rule : item)));

    return (
        <div className="grid gap-3" data-test="layout-conditions">
            {rules.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('No rules yet, so it shows nowhere.')}
                </p>
            )}
            <ol className="grid gap-3">
                {rules.map((rule, index) => {
                    const definition = definitions.find(
                        (item) => item.key === rule.type,
                    );
                    const ruleId = `${id}-${index}`;

                    return (
                        <li
                            key={index}
                            className="grid gap-2 rounded-md border p-3"
                        >
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`${ruleId}-type`}
                                    className="text-xs"
                                >
                                    {t('Show on')}
                                </Label>
                                <OptionSelect
                                    id={`${ruleId}-type`}
                                    value={rule.type}
                                    onValueChange={(type) => {
                                        const next = definitions.find(
                                            (item) => item.key === type,
                                        );
                                        set(index, {
                                            ...rule,
                                            type,
                                            value:
                                                next?.options?.[0]?.value ?? '',
                                        });
                                    }}
                                    options={definitions.map((item) => ({
                                        value: item.key,
                                        label: item.label,
                                    }))}
                                    searchPlaceholder={t('Search options')}
                                />
                            </div>
                            {definition?.options && (
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`${ruleId}-value`}
                                        className="text-xs"
                                    >
                                        {t('Which one')}
                                    </Label>
                                    <SearchableSelect
                                        id={`${ruleId}-value`}
                                        value={rule.value}
                                        options={definition.options}
                                        onValueChange={(value) =>
                                            set(index, { ...rule, value })
                                        }
                                        placeholder={t('Choose an option')}
                                        searchPlaceholder={t('Search options')}
                                        className="w-full"
                                    />
                                </div>
                            )}
                            <div className="flex items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={`${ruleId}-exclude`}
                                        checked={rule.exclude}
                                        onCheckedChange={(checked) =>
                                            set(index, {
                                                ...rule,
                                                exclude: checked === true,
                                            })
                                        }
                                    />
                                    <Label
                                        htmlFor={`${ruleId}-exclude`}
                                        className="text-sm font-normal"
                                    >
                                        {t('Except here')}
                                    </Label>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    aria-label={t('Remove rule {number}', {
                                        number: index + 1,
                                    })}
                                    onClick={() =>
                                        onChange(
                                            rules.filter(
                                                (_, at) => at !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash />
                                </Button>
                            </div>
                        </li>
                    );
                })}
            </ol>
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={definitions.length === 0}
                    onClick={() =>
                        onChange([
                            ...rules,
                            {
                                type: definitions[0].key,
                                value: definitions[0].options?.[0]?.value ?? '',
                                exclude: false,
                            },
                        ])
                    }
                >
                    <Plus />
                    {t('Add a rule')}
                </Button>
            </div>
        </div>
    );
}
