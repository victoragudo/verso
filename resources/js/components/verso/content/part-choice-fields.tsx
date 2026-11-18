import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { INHERIT_STYLE } from '@/components/verso/content/part-choices';
import { t } from '@/lib/i18n';
import type { PartChoice } from '@/types';

type Props = {
    choices: PartChoice[];
    value: Record<string, string>;
    onChange: (value: Record<string, string>) => void;
    errors?: Record<string, string | undefined>;
    inheritLabel?: string;
    idPrefix?: string;
};

// One select per part with styles (header, footer): keep the style of the template or the site, or pick another.
export function PartChoiceFields({
    choices,
    value,
    onChange,
    errors = {},
    inheritLabel,
    idPrefix = 'part-style',
}: Props) {
    return (
        <>
            {choices.map((choice) => {
                const id = `${idPrefix}-${choice.name}`;
                const current = value[choice.name] ?? INHERIT_STYLE;
                const known =
                    current === INHERIT_STYLE ||
                    choice.options.some((option) => option.value === current);

                return (
                    <div key={choice.name} className="grid gap-2">
                        <Label htmlFor={id}>
                            {t('{part} style', { part: choice.label })}
                        </Label>
                        <Select
                            value={current}
                            onValueChange={(style) =>
                                onChange({ ...value, [choice.name]: style })
                            }
                        >
                            <SelectTrigger id={id} className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={INHERIT_STYLE}>
                                    {inheritLabel ??
                                        t('Same as the template or the site')}
                                </SelectItem>
                                {choice.options.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                                {!known && (
                                    <SelectItem value={current}>
                                        {t(
                                            '{style} (not in the active theme)',
                                            {
                                                style: current,
                                            },
                                        )}
                                    </SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={errors[choice.name]} />
                    </div>
                );
            })}
        </>
    );
}
