import { formatList, t } from '@/lib/i18n';
import { VersoIcon } from '@/components/verso/icons';
import { StatusBadge } from '@/components/verso/ui';
import { pageTitles, pluginsLine, postsLine } from '@/lib/easy/starters';
import type { StarterSummary } from '@/lib/easy/starters';
import { StarterPreview } from './starter-preview';

type Props = {
    starter: StarterSummary;
    checked: boolean;
    current: boolean;
    disabled?: boolean;
    onSelect: (key: string) => void;
};

// One choice of the guided start: a radio in a big card with a drawing, a short text and the pages it adds.
export function StarterCard({
    starter,
    checked,
    current,
    disabled = false,
    onSelect,
}: Props) {
    const titleId = `starter-${starter.key}-title`;
    const detailsId = `starter-${starter.key}-details`;
    const posts = postsLine(starter);
    const plugins = pluginsLine(starter);

    return (
        <label className="group relative flex cursor-pointer flex-col gap-3 rounded-lg border bg-card p-4 shadow-xs transition-colors hover:border-zinc-300 has-checked:border-brand has-checked:ring-1 has-checked:ring-brand has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand has-disabled:cursor-not-allowed has-disabled:opacity-70 dark:hover:border-zinc-600">
            <StarterPreview sections={starter.sections} />

            <span className="flex items-start gap-3">
                <input
                    type="radio"
                    name="starter"
                    value={starter.key}
                    checked={checked}
                    disabled={disabled}
                    onChange={() => onSelect(starter.key)}
                    aria-labelledby={titleId}
                    aria-describedby={detailsId}
                    className="mt-1 size-4 shrink-0 accent-brand focus-visible:outline-none"
                />
                <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-center gap-2">
                        <VersoIcon
                            name={starter.icon}
                            size={18}
                            className="shrink-0 text-muted-foreground"
                        />
                        <span
                            id={titleId}
                            className="text-base font-semibold text-foreground"
                        >
                            {starter.label}
                        </span>
                        {current && (
                            <StatusBadge tone="success">
                                {t('Your site now')}
                            </StatusBadge>
                        )}
                    </span>
                    <span
                        id={detailsId}
                        className="mt-1 block text-sm text-muted-foreground"
                    >
                        <span className="block">{starter.description}</span>
                        <span className="mt-2 block font-medium text-foreground">
                            {t('Pages: {pages}', {
                                pages: formatList(pageTitles(starter), 'unit'),
                            })}
                        </span>
                        {posts && <span className="block">{posts}</span>}
                        {plugins && <span className="block">{plugins}</span>}
                    </span>
                </span>
            </span>
        </label>
    );
}
