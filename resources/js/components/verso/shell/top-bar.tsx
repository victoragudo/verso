import { usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { VersoIcon } from '@/components/verso/icons';
import { t } from '@/lib/i18n';
import type { BreadcrumbItem } from '@/types';
import { UserMenu } from './user-menu';

const SEARCH_SHORTCUT = '⌘K';

type Props = {
    breadcrumbs: BreadcrumbItem[];
    onOpenNav: () => void;
    onOpenSearch: () => void;
    onOpenHelp: () => void;
};

// Top bar: menu button on small screens, breadcrumbs, search, help and account.
export function TopBar({
    breadcrumbs,
    onOpenNav,
    onOpenSearch,
    onOpenHelp,
}: Props) {
    // The help panel is for people who write or build the site (they get the simple mode prop).
    const showHelp = usePage().props.simpleMode != null;

    return (
        <header
            data-slot="admin-top-bar"
            className="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b bg-card/90 px-2 backdrop-blur supports-[backdrop-filter]:bg-card/75 sm:px-4 lg:px-6"
        >
            <button
                type="button"
                onClick={onOpenNav}
                aria-label={t('Open navigation')}
                className="flex size-10 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground lg:hidden"
            >
                <Menu className="size-5" />
            </button>

            <div className="min-w-0 flex-1">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <button
                type="button"
                onClick={onOpenSearch}
                className="flex size-10 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground md:h-9 md:w-64 md:justify-start md:gap-2 md:border md:bg-background md:px-3 md:text-sm pointer-coarse:min-h-11"
            >
                <VersoIcon name="search" size={18} className="shrink-0" />
                <span className="sr-only flex-1 text-start md:not-sr-only">
                    {t('Search pages')}
                </span>
                <kbd className="hidden rounded border bg-card px-1.5 py-0.5 font-sans text-[11px] font-medium md:inline">
                    {SEARCH_SHORTCUT}
                </kbd>
            </button>

            {showHelp && (
                <button
                    type="button"
                    onClick={onOpenHelp}
                    aria-haspopup="dialog"
                    className="flex size-10 shrink-0 items-center justify-center gap-1.5 rounded-md text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground md:h-9 md:w-auto md:px-2.5 pointer-coarse:min-h-11"
                >
                    <VersoIcon name="help" size={18} className="shrink-0" />
                    <span className="sr-only md:not-sr-only">{t('Help')}</span>
                </button>
            )}

            <UserMenu />
        </header>
    );
}
