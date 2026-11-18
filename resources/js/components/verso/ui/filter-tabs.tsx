import { Link } from '@inertiajs/react';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type FilterTab = {
    key: string;
    label: string;
    href: string;
    count?: number;
};

type Props = {
    tabs: FilterTab[];
    active: string;
    label?: string;
};

// A row of filter links, like All, Published and Drafts, with counts.
export function FilterTabs({ tabs, active, label = t('Filters') }: Props) {
    return (
        <nav
            aria-label={label}
            className="-m-1 flex max-w-full gap-1 overflow-x-auto p-1 text-sm"
        >
            {tabs.map((tab) => {
                const isActive = tab.key === active;

                return (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        preserveScroll
                        aria-current={isActive ? 'page' : undefined}
                        className={cn(
                            'inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-md px-3 whitespace-nowrap transition-colors md:min-h-8',
                            isActive
                                ? 'bg-card font-medium text-foreground shadow-xs ring-1 ring-border'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                        )}
                    >
                        {tab.label}
                        {tab.count !== undefined && (
                            <span
                                className={cn(
                                    'rounded px-1.5 text-xs tabular-nums',
                                    isActive
                                        ? 'bg-muted text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {tab.count}
                            </span>
                        )}
                    </Link>
                );
            })}
        </nav>
    );
}
