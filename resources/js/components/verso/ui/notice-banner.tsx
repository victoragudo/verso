import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { VersoIcon } from '@/components/verso/icons';
import { cn } from '@/lib/utils';
import type { StatusTone } from './status-badge';

const TONES: Record<StatusTone, string> = {
    success: 'border-success-text/20 bg-success-soft text-success-text',
    warning: 'border-warning-text/20 bg-warning-soft text-warning-text',
    danger: 'border-danger-text/20 bg-danger-soft text-danger-text',
    neutral: 'border-border bg-neutral-soft text-neutral-text',
    info: 'border-info-text/20 bg-info-soft text-info-text',
};

type Props = {
    title: ReactNode;
    description?: ReactNode;
    tone?: StatusTone;
    icon?: string;
    href?: string | null;
    action?: string | null;
    className?: string;
};

// Soft colored box with a short message and an optional link, for notices like a new release.
export function NoticeBanner({
    title,
    description,
    tone = 'info',
    icon,
    href,
    action,
    className,
}: Props) {
    return (
        <div
            role="status"
            className={cn(
                'flex flex-col gap-3 rounded-lg border px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5',
                TONES[tone],
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-2.5">
                {icon && (
                    <VersoIcon
                        name={icon}
                        size={18}
                        className="mt-px shrink-0"
                    />
                )}
                <div className="min-w-0">
                    <p className="text-sm font-semibold">{title}</p>
                    {description && (
                        <p className="mt-0.5 text-sm opacity-90">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            {href && action && (
                <Link
                    href={href}
                    className="inline-flex min-h-10 shrink-0 items-center text-sm font-medium underline-offset-4 hover:underline md:min-h-9 pointer-coarse:min-h-11"
                >
                    {action}
                </Link>
            )}
        </div>
    );
}
