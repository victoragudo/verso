import type { ReactNode } from 'react';
import { VersoIcon } from '@/components/verso/icons';
import { cn } from '@/lib/utils';

type Props = {
    icon?: string;
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    className?: string;
};

// Shown when a list is empty: an icon, one line and one action.
export function EmptyState({
    icon = 'dot',
    title,
    description,
    action,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center px-6 py-12 text-center',
                className,
            )}
        >
            <span className="mb-3 flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <VersoIcon name={icon} size={20} />
            </span>
            <p className="text-sm font-medium text-foreground">{title}</p>
            {description && (
                <p className="mt-1 max-w-sm text-sm text-pretty text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
