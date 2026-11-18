import { memo, useId } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { SectionCard, StatusBadge } from '@/components/verso/ui';
import { checkState } from '@/lib/access';
import type { CapabilityGroup, CheckState } from '@/lib/access';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Capability } from '@/types';
import { RiskBadge } from './risk-badge';

type Props = {
    groups: CapabilityGroup[];
    selected: Set<string>;
    grantable: Set<string>;
    readOnly?: boolean;
    defaults?: Set<string> | null;
    onToggle: (keys: string[], on: boolean) => void;
};

// Capability cards per group, with "select all" boxes for each group and section.
export function CapabilityChecklist({
    groups,
    selected,
    grantable,
    readOnly = false,
    defaults = null,
    onToggle,
}: Props) {
    return (
        <div className="masonry-grid [--grid-min:26rem]">
            {groups.map((group) => {
                const keys = group.capabilities.map((item) => item.key);

                return (
                    <SectionCard
                        key={group.group}
                        title={group.group}
                        contentClassName="p-0"
                        actions={
                            !readOnly && (
                                <SelectAll
                                    label={t('All {group} capabilities', {
                                        group: group.group,
                                    })}
                                    keys={keys}
                                    selected={selected}
                                    grantable={grantable}
                                    onToggle={onToggle}
                                />
                            )
                        }
                    >
                        {group.sections.map((section) => (
                            <div
                                key={section.key}
                                className="border-b last:border-b-0"
                            >
                                {section.title && (
                                    <div className="flex items-center justify-between gap-2 bg-muted/50 px-4 py-1 sm:px-5">
                                        <h3 className="text-xs font-semibold text-muted-foreground">
                                            {section.title}
                                        </h3>
                                        {!readOnly &&
                                            group.sections.length > 1 && (
                                                <SelectAll
                                                    label={t(
                                                        'All {group} capabilities',
                                                        {
                                                            group: section.title,
                                                        },
                                                    )}
                                                    keys={section.capabilities.map(
                                                        (item) => item.key,
                                                    )}
                                                    selected={selected}
                                                    grantable={grantable}
                                                    onToggle={onToggle}
                                                />
                                            )}
                                    </div>
                                )}
                                <ul className="divide-y">
                                    {section.capabilities.map((capability) => (
                                        <CapabilityItem
                                            key={capability.key}
                                            capability={capability}
                                            checked={selected.has(
                                                capability.key,
                                            )}
                                            disabled={
                                                readOnly ||
                                                !grantable.has(capability.key)
                                            }
                                            diff={diffOf(
                                                capability.key,
                                                selected,
                                                defaults,
                                            )}
                                            onToggle={onToggle}
                                        />
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </SectionCard>
                );
            })}
        </div>
    );
}

type Diff = 'added' | 'removed' | null;

function diffOf(
    key: string,
    selected: Set<string>,
    defaults: Set<string> | null,
): Diff {
    if (!defaults || selected.has(key) === defaults.has(key)) {
        return null;
    }

    return selected.has(key) ? 'added' : 'removed';
}

// A tri-state box that ticks or clears every capability the viewer may give.
function SelectAll({
    label,
    keys,
    selected,
    grantable,
    onToggle,
}: {
    label: string;
    keys: string[];
    selected: Set<string>;
    grantable: Set<string>;
    onToggle: (keys: string[], on: boolean) => void;
}) {
    const editable = keys.filter((key) => grantable.has(key));
    const state: CheckState = checkState(keys, selected);

    return (
        <span className="inline-flex size-10 items-center justify-center">
            <Checkbox
                aria-label={label}
                checked={state}
                disabled={editable.length === 0}
                onCheckedChange={() =>
                    onToggle(editable, checkState(editable, selected) !== true)
                }
                className="size-[1.125rem]"
            />
        </span>
    );
}

type ItemProps = {
    capability: Capability;
    checked: boolean;
    disabled: boolean;
    diff: Diff;
    onToggle: (keys: string[], on: boolean) => void;
};

// One capability with its box, risk, description and change versus the defaults.
const CapabilityItem = memo(function CapabilityItem({
    capability,
    checked,
    disabled,
    diff,
    onToggle,
}: ItemProps) {
    const id = useId();

    return (
        <li
            className={cn(
                'flex items-start gap-3 px-4 py-2.5 sm:px-5',
                diff === 'added' && 'bg-success-soft/40',
                diff === 'removed' && 'bg-danger-soft/40',
            )}
        >
            <span className="-m-3 inline-flex size-10 shrink-0 items-center justify-center">
                <Checkbox
                    id={id}
                    checked={checked}
                    disabled={disabled}
                    aria-describedby={
                        capability.description ? `${id}-description` : undefined
                    }
                    title={
                        disabled
                            ? t(
                                  'You cannot give a capability that you do not have yourself',
                              )
                            : undefined
                    }
                    onCheckedChange={(value) =>
                        onToggle([capability.key], value === true)
                    }
                    className="size-[1.125rem]"
                />
            </span>
            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <label
                        htmlFor={id}
                        className="text-sm font-medium text-foreground"
                    >
                        {capability.label}
                    </label>
                    <RiskBadge risk={capability.risk} />
                    {diff === 'added' && (
                        <StatusBadge tone="success">{t('Added')}</StatusBadge>
                    )}
                    {diff === 'removed' && (
                        <StatusBadge tone="danger">{t('Removed')}</StatusBadge>
                    )}
                </span>
                {capability.description && (
                    <span
                        id={`${id}-description`}
                        className="mt-0.5 block text-xs text-muted-foreground"
                    >
                        {capability.description}
                    </span>
                )}
            </span>
        </li>
    );
});
