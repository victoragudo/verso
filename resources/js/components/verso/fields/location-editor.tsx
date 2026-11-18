import { Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SearchableSelect } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import type { ChoiceOption, LocationParam, LocationRule } from './types';

export type LocationChoices = {
    locationParams: ChoiceOption[];
    contentTypes: ChoiceOption[];
    roles: ChoiceOption[];
    terms: ChoiceOption[];
};

type Props = {
    value: LocationRule[][];
    onChange: (value: LocationRule[][]) => void;
    choices: LocationChoices;
};

export const NEW_RULE: LocationRule = {
    param: 'content_type',
    operator: '==',
    value: 'post',
};

// Rule sets joined by "or". The rules inside one set are joined by "and".
export function LocationEditor({ value, onChange, choices }: Props) {
    const updateRule = (set: number, index: number, rule: LocationRule) =>
        onChange(
            value.map((rules, current) =>
                current === set
                    ? rules.map((old, other) => (other === index ? rule : old))
                    : rules,
            ),
        );

    const removeRule = (set: number, index: number) =>
        onChange(
            value
                .map((rules, current) =>
                    current === set
                        ? rules.filter((_, other) => other !== index)
                        : rules,
                )
                .filter((rules) => rules.length > 0),
        );

    return (
        <div className="grid gap-3">
            {value.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('No rules yet, so this group shows nowhere.')}
                </p>
            )}
            {value.map((rules, set) => (
                <div key={set} className="grid gap-2">
                    {set > 0 && (
                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            {t('or')}
                        </span>
                    )}
                    <div
                        className="grid gap-2 rounded-md border bg-muted/20 p-3"
                        role="group"
                        aria-label={t('Rule set {number}', {
                            number: set + 1,
                        })}
                    >
                        {rules.map((rule, index) => (
                            <RuleRow
                                key={index}
                                rule={rule}
                                choices={choices}
                                onChange={(next) =>
                                    updateRule(set, index, next)
                                }
                                onRemove={() => removeRule(set, index)}
                                label={t('Rule {rule} of set {set}', {
                                    rule: index + 1,
                                    set: set + 1,
                                })}
                                removeLabel={t(
                                    'Remove rule {rule} of set {set}',
                                    { rule: index + 1, set: set + 1 },
                                )}
                            />
                        ))}
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="justify-self-start"
                            onClick={() =>
                                onChange(
                                    value.map((current, other) =>
                                        other === set
                                            ? [...current, { ...NEW_RULE }]
                                            : current,
                                    ),
                                )
                            }
                        >
                            <Plus />
                            {t('And')}
                        </Button>
                    </div>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                className="justify-self-start"
                onClick={() => onChange([...value, [{ ...NEW_RULE }]])}
            >
                <Plus />
                {value.length === 0
                    ? t('Add a rule')
                    : t('Add an "or" rule set')}
            </Button>
        </div>
    );
}

type RuleRowProps = {
    rule: LocationRule;
    choices: LocationChoices;
    onChange: (rule: LocationRule) => void;
    onRemove: () => void;
    label: string;
    removeLabel: string;
};

function valueChoices(
    param: LocationParam,
    choices: LocationChoices,
): ChoiceOption[] | null {
    switch (param) {
        case 'content_type':
            return choices.contentTypes;
        case 'user_role':
            return choices.roles;
        case 'taxonomy_term':
            return choices.terms;
        default:
            return null;
    }
}

function RuleRow({
    rule,
    choices,
    onChange,
    onRemove,
    label,
    removeLabel,
}: RuleRowProps) {
    const options = valueChoices(rule.param, choices);

    return (
        <div
            className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_7rem_minmax(0,1fr)_auto]"
            aria-label={label}
            role="group"
        >
            <Select
                value={rule.param}
                onValueChange={(param) =>
                    onChange({
                        param: param as LocationParam,
                        operator: rule.operator,
                        value:
                            valueChoices(param as LocationParam, choices)?.[0]
                                ?.value ?? '',
                    })
                }
            >
                <SelectTrigger aria-label={t('Show when')} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {choices.locationParams.map((param) => (
                        <SelectItem key={param.value} value={param.value}>
                            {param.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select
                value={rule.operator}
                onValueChange={(operator) =>
                    onChange({ ...rule, operator: operator as '==' | '!=' })
                }
            >
                <SelectTrigger aria-label={t('Operator')} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="==">{t('is')}</SelectItem>
                    <SelectItem value="!=">{t('is not')}</SelectItem>
                </SelectContent>
            </Select>
            {options ? (
                <SearchableSelect
                    value={rule.value}
                    onValueChange={(value) => onChange({ ...rule, value })}
                    options={options}
                    aria-label={t('Value')}
                    emptyText={t('Nothing matches.')}
                    className="w-full"
                />
            ) : (
                <Input
                    aria-label={t('Template name')}
                    value={rule.value}
                    placeholder="landing"
                    onChange={(event) =>
                        onChange({ ...rule, value: event.target.value })
                    }
                />
            )}
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={removeLabel}
                onClick={onRemove}
            >
                <X />
            </Button>
        </div>
    );
}
