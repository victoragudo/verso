import type { ReactNode } from 'react';
import { VersoIcon } from '@/components/verso/icons';
import { cn } from '@/lib/utils';

type Props = {
    title?: ReactNode;
    description?: ReactNode;
    icon?: string;
    actions?: ReactNode;
    footer?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
    id?: string;
};

// White card with an optional header row (title, description, actions).
export function SectionCard({
    title,
    description,
    icon,
    actions,
    footer,
    children,
    className,
    contentClassName,
    id,
}: Props) {
    const hasHeader = title || description || actions;

    return (
        <section
            id={id}
            className={cn(
                'min-w-0 rounded-lg border bg-card text-card-foreground shadow-xs',
                className,
            )}
        >
            {hasHeader && (
                <header className="flex flex-wrap items-start justify-between gap-3 border-b px-4 py-3 sm:px-5">
                    <div className="flex min-w-0 items-start gap-2.5">
                        {icon && (
                            <VersoIcon
                                name={icon}
                                size={18}
                                className="mt-px shrink-0 text-muted-foreground"
                            />
                        )}
                        <div className="min-w-0">
                            {title && (
                                <h2 className="text-sm font-semibold text-foreground">
                                    {title}
                                </h2>
                            )}
                            {description && (
                                <p className="mt-0.5 max-w-prose text-sm text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                    </div>
                    {actions && (
                        <div className="flex shrink-0 items-center gap-2">
                            {actions}
                        </div>
                    )}
                </header>
            )}
            <div className={cn('p-4 sm:p-5', contentClassName)}>{children}</div>
            {footer && (
                <footer className="flex flex-wrap items-center justify-end gap-2 rounded-b-lg border-t bg-muted/40 px-4 py-3 sm:px-5">
                    {footer}
                </footer>
            )}
        </section>
    );
}
