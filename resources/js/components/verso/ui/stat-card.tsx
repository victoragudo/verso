import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { VersoIcon } from '@/components/verso/icons';
import { formatNumber } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: number | string;
    href?: string | null;
    icon?: string | null;
    hint?: ReactNode;
};

// One number with a label. Links to its list when it has an href.
export function StatCard({ label, value, href, icon, hint }: Props) {
    const body = (
        <div
            className={cn(
                'flex h-full flex-col gap-3 rounded-lg border bg-card p-4 shadow-xs',
                href && 'transition-colors hover:border-foreground/20',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="min-w-0 text-sm text-muted-foreground">
                    {label}
                </span>
                {icon && (
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md border bg-muted/60 text-muted-foreground">
                        <VersoIcon name={icon} size={18} />
                    </span>
                )}
            </div>
            <span className="text-2xl font-semibold text-foreground tabular-nums">
                {typeof value === 'number' ? formatNumber(value) : value}
            </span>
            {hint && (
                <span className="text-xs text-muted-foreground">{hint}</span>
            )}
        </div>
    );

    return href ? (
        <Link
            href={href}
            className="block rounded-lg outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
            {body}
        </Link>
    ) : (
        body
    );
}
