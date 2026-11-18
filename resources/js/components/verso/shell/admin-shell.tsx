import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { ViewAsBanner } from '@/components/verso/access/view-as-banner';
import { HelpPanel } from '@/components/verso/easy/help-panel';
import { useAdminNavWidth } from '@/hooks/use-admin-nav-width';
import { OPEN_NAV_EVENT } from '@/hooks/use-flash-toast';
import { usePageChangeFocus } from '@/hooks/use-page-change-focus';
import { useReturnFocus } from '@/hooks/use-return-focus';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { useFocusMode } from '@/components/verso/workspace/focus-mode';
import { CommandMenu, useCommandShortcut } from './command-menu';
import { NavResizer } from './nav-resizer';
import { SidebarNav } from './sidebar-nav';
import { TopBar } from './top-bar';

export const ADMIN_CONTENT_ID = 'admin-content';

// How long a new sidebar item stays highlighted, in milliseconds.
export const NAV_HIGHLIGHT_MS = 6000;

type Props = {
    breadcrumbs: BreadcrumbItem[];
    children: ReactNode;
    // Builder pages: the page fills the screen height and never scrolls as a whole. Focus mode hides the sidebar and top bar.
    workspace?: boolean;
};

// The admin frame: fixed sidebar on large screens, a sheet on small ones.
export function AdminShell({
    breadcrumbs,
    children,
    workspace = false,
}: Props) {
    const [focusMode] = useFocusMode();
    const focused = workspace && focusMode;
    const [navOpen, setNavOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [helpOpen, setHelpOpen] = useState(false);
    const navFocus = useReturnFocus();
    const searchFocus = useReturnFocus();
    const helpFocus = useReturnFocus();
    const rememberSearch = searchFocus.remember;
    const openSearch = useCallback(() => {
        rememberSearch();
        setSearchOpen(true);
    }, [rememberSearch]);
    const openHelp = () => {
        helpFocus.remember();
        setHelpOpen(true);
    };
    const openNav = () => {
        navFocus.remember();
        setNavOpen(true);
    };

    useCommandShortcut(openSearch);

    useEffect(() => router.on('navigate', () => setNavOpen(false)), []);
    usePageChangeFocus(ADMIN_CONTENT_ID);

    const highlightKey = useNavHighlight();
    const navWidth = useAdminNavWidth();
    const { versoNav } = usePage().props;

    // The "Open" button of a toast opens a sidebar item by its key.
    useEffect(() => {
        const open = (event: Event) => {
            const key = (event as CustomEvent<string>).detail;
            const item = versoNav.find((entry) => entry.key === key);

            if (item) {
                router.visit(item.href);
            }
        };

        window.addEventListener(OPEN_NAV_EVENT, open);

        return () => window.removeEventListener(OPEN_NAV_EVENT, open);
    }, [versoNav]);

    return (
        <div
            className={cn(
                'bg-background',
                workspace ? 'h-dvh overflow-hidden' : 'min-h-svh',
            )}
            data-workspace={workspace ? '' : undefined}
            data-focus={focused ? '' : undefined}
            style={
                {
                    '--admin-nav-width': focused
                        ? '0px'
                        : `${navWidth.width}px`,
                } as CSSProperties
            }
        >
            <a
                href={`#${ADMIN_CONTENT_ID}`}
                className="sr-only rounded-md bg-card px-4 py-2 text-sm font-medium text-foreground shadow-xs focus:not-sr-only focus:fixed focus:start-3 focus:top-3 focus:z-50 focus:ring-[3px] focus:ring-ring/50"
            >
                {t('Skip to content')}
            </a>

            <aside
                data-slot="admin-sidebar"
                className={cn(
                    'fixed inset-y-0 start-0 z-40 hidden w-(--admin-nav-width) border-e border-sidebar-border bg-sidebar',
                    !focused && 'lg:block',
                )}
            >
                <SidebarNav highlightKey={highlightKey} />
                <NavResizer
                    width={navWidth.width}
                    onResize={navWidth.setWidth}
                    onReset={navWidth.reset}
                />
            </aside>

            <Sheet open={navOpen} onOpenChange={setNavOpen}>
                <SheetContent
                    side="left"
                    onCloseAutoFocus={navFocus.restore}
                    className="w-72 max-w-[85vw] gap-0 bg-sidebar p-0"
                >
                    <SheetTitle className="sr-only">
                        {t('Navigation')}
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        {t('Links to every admin page.')}
                    </SheetDescription>
                    <SidebarNav
                        onNavigate={() => setNavOpen(false)}
                        highlightKey={highlightKey}
                    />
                </SheetContent>
            </Sheet>

            <div
                className={cn(
                    'flex min-w-0 flex-col lg:ps-(--admin-nav-width)',
                    workspace ? 'h-dvh' : 'min-h-svh',
                )}
            >
                {/* The preview banner and the top bar stay on top together. */}
                <div className="sticky top-0 z-30" hidden={focused}>
                    <ViewAsBanner />
                    <TopBar
                        breadcrumbs={breadcrumbs}
                        onOpenNav={openNav}
                        onOpenSearch={openSearch}
                        onOpenHelp={openHelp}
                    />
                </div>
                {/* The page uses all the width; pages adapt with container queries. */}
                <main
                    id={ADMIN_CONTENT_ID}
                    tabIndex={-1}
                    className={cn(
                        '@container/page w-full min-w-0 flex-1 focus:outline-none',
                        workspace
                            ? 'flex min-h-0 flex-col overflow-hidden'
                            : 'scroll-mt-14 admin-gutter',
                    )}
                >
                    {children}
                </main>
            </div>

            <CommandMenu
                open={searchOpen}
                onOpenChange={setSearchOpen}
                onCloseAutoFocus={searchFocus.restore}
            />

            <HelpPanel
                open={helpOpen}
                onOpenChange={setHelpOpen}
                onCloseAutoFocus={helpFocus.restore}
            />
        </div>
    );
}

// The sidebar item the server asked to show for a moment, like a plugin that was just enabled.
function useNavHighlight(): string | null {
    const flash = usePage().flash as { navHighlight?: unknown } | undefined;
    const flashed =
        typeof flash?.navHighlight === 'string' ? flash.navHighlight : null;
    const [key, setKey] = useState<string | null>(null);

    useEffect(() => {
        if (flashed === null) {
            return;
        }

        setKey(flashed);
        const timer = window.setTimeout(() => setKey(null), NAV_HIGHLIGHT_MS);

        return () => window.clearTimeout(timer);
    }, [flashed]);

    return key;
}
