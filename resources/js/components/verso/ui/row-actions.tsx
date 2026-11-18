import { Link } from '@inertiajs/react';
import type { Method } from '@inertiajs/core';
import { MoreHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type RowAction = {
    label: string;
    icon?: ReactNode;
    href?: string;
    method?: Method;
    external?: boolean;
    onSelect?: () => void;
    destructive?: boolean;
    hidden?: boolean;
};

type Props = {
    actions: RowAction[];
    label?: string;
};

// The "..." button with the actions for one table row.
export function RowActions({ actions, label = t('Row actions') }: Props) {
    const visible = actions.filter((action) => !action.hidden);

    if (visible.length === 0) {
        return null;
    }

    const safe = visible.filter((action) => !action.destructive);
    const destructive = visible.filter((action) => action.destructive);

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger
                aria-label={label}
                className="inline-flex size-10 items-center justify-center rounded-md text-muted-foreground transition-colors outline-none hover:bg-accent hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 data-[state=open]:bg-accent md:size-8"
            >
                <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-40">
                {safe.map((action) => (
                    <ActionItem key={action.label} action={action} />
                ))}
                {safe.length > 0 && destructive.length > 0 && (
                    <DropdownMenuSeparator />
                )}
                {destructive.map((action) => (
                    <ActionItem key={action.label} action={action} />
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function ActionItem({ action }: { action: RowAction }) {
    const className = cn(
        'w-full cursor-pointer',
        action.destructive &&
            'text-destructive-foreground focus:bg-danger-soft focus:text-danger-text',
    );

    if (action.href && action.external) {
        return (
            <DropdownMenuItem asChild className={className}>
                <a href={action.href} target="_blank" rel="noreferrer">
                    {action.icon}
                    {action.label}
                </a>
            </DropdownMenuItem>
        );
    }

    if (action.href) {
        const isGet = !action.method || action.method === 'get';

        return (
            <DropdownMenuItem asChild className={className}>
                <Link
                    href={action.href}
                    method={action.method}
                    as={isGet ? 'a' : 'button'}
                    preserveScroll={!isGet}
                >
                    {action.icon}
                    {action.label}
                </Link>
            </DropdownMenuItem>
        );
    }

    return (
        <DropdownMenuItem className={className} onSelect={action.onSelect}>
            {action.icon}
            {action.label}
        </DropdownMenuItem>
    );
}
