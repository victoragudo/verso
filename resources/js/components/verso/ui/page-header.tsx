import type { ReactNode } from 'react';
import { VersoIcon } from '@/components/verso/icons';
import { cn } from '@/lib/utils';

type Props = {
    title: ReactNode;
    description?: ReactNode;
    icon?: string;
    actions?: ReactNode;
    className?: string;
};

// Page title row: icon, title, short description and actions on the right.
export function PageHeader({
    title,
    description,
    icon,
    actions,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                {icon && (
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-card text-muted-foreground shadow-xs">
                        <VersoIcon name={icon} size={20} />
                    </span>
                )}
                <div className="min-w-0">
                    <h1 className="text-xl leading-tight font-semibold tracking-tight text-balance text-foreground">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1 max-w-prose text-sm text-pretty text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
