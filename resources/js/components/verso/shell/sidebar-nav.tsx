import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ExternalLink } from 'lucide-react';
import { useEffect, useId, useMemo, useRef } from 'react';
import { VersoLogo } from '@/components/verso/brand/verso-logo';
import { VersoIcon } from '@/components/verso/icons';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { dashboard } from '@/routes/admin';
import {
    activeNavKey,
    ancestorKeys,
    groupNavItems,
    hasNewItem,
} from './nav-groups';
import type { NavNode, NavSection } from './nav-groups';
import { navStorageKey, useNavState } from './use-nav-state';

type Props = {
    onNavigate?: () => void;
    // Key of an item to show for a moment, like a plugin that was just enabled.
    highlightKey?: string | null;
};

type NavState = ReturnType<typeof useNavState>;

// The sidebar body: site block, grouped links that open and close, and a link to the public site.
export function SidebarNav({ onNavigate, highlightKey = null }: Props) {
    const { versoNav, siteName, auth } = usePage().props;
    const { currentUrl } = useCurrentUrl();
    const sections = useMemo(() => groupNavItems(versoNav), [versoNav]);
    const activeKey = activeNavKey(versoNav, currentUrl);
    const activeParents = activeKey ? ancestorKeys(versoNav, activeKey) : [];
    const activeGroup =
        versoNav.find((item) => item.key === activeKey)?.group ?? null;
    const userId = (auth as { user?: { id?: number } } | undefined)?.user?.id;
    const state = useNavState(
        navStorageKey(userId),
        activeGroup,
        activeParents,
    );
    const name = siteName || 'Verso';
    const navRef = useRef<HTMLElement>(null);
    const openPath = state.openPath;

    // Keep the link of the current page in view, also when it is far down the sidebar.
    useEffect(() => {
        const frame = requestAnimationFrame(() => {
            navRef.current
                ?.querySelector('[aria-current="page"]')
                ?.scrollIntoView?.({ block: 'nearest' });
        });

        return () => cancelAnimationFrame(frame);
    }, [activeKey]);

    useEffect(() => {
        if (!highlightKey) {
            return;
        }

        const item = versoNav.find((entry) => entry.key === highlightKey);

        if (!item) {
            return;
        }

        // Open its section, its parents and the item itself, so its pages show too.
        openPath(item.group, [
            ...ancestorKeys(versoNav, highlightKey),
            highlightKey,
        ]);

        const frame = requestAnimationFrame(() => {
            navRef.current
                ?.querySelector(`[data-nav-key="${CSS.escape(highlightKey)}"]`)
                ?.scrollIntoView?.({ block: 'nearest' });
        });

        return () => cancelAnimationFrame(frame);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [highlightKey]);

    return (
        <div className="flex h-full flex-col">
            <div className="flex h-14 shrink-0 items-center border-b border-sidebar-border px-4">
                <Link
                    href={dashboard()}
                    onClick={onNavigate}
                    className="flex min-h-10 min-w-0 flex-1 items-center gap-2.5 rounded-md px-2 transition-colors hover:bg-sidebar-accent"
                >
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <VersoLogo className="size-[18px]" aria-hidden="true" />
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-semibold text-foreground">
                            {name}
                        </span>
                        <span className="block truncate text-xs text-muted-foreground">
                            {t('Site admin')}
                        </span>
                    </span>
                </Link>
            </div>

            <nav
                ref={navRef}
                aria-label={t('Admin')}
                className="flex-1 overflow-y-auto overscroll-contain px-4 py-5"
            >
                {sections.map((section) => (
                    <SidebarSection
                        key={section.group}
                        section={section}
                        state={state}
                        activeKey={activeKey}
                        highlightKey={highlightKey}
                        onNavigate={onNavigate}
                    />
                ))}
            </nav>

            <div className="shrink-0 border-t border-sidebar-border p-4">
                <a
                    href={home().url}
                    target="_blank"
                    rel="noreferrer"
                    className="group flex min-h-10 items-center gap-2.5 rounded-md px-2.5 text-sm text-sidebar-foreground transition-colors hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground pointer-fine:lg:min-h-9"
                >
                    <VersoIcon
                        name="site"
                        size={18}
                        className="shrink-0 text-muted-foreground group-hover:text-sidebar-accent-foreground"
                    />
                    <span className="flex-1">{t('View site')}</span>
                    <ExternalLink className="size-3.5 text-muted-foreground" />
                </a>
            </div>
        </div>
    );
}

type SectionProps = {
    section: NavSection;
    state: NavState;
    activeKey: string | null;
    highlightKey: string | null;
    onNavigate?: () => void;
};

// One section with a heading button that opens and closes it.
function SidebarSection({
    section,
    state,
    activeKey,
    highlightKey,
    onNavigate,
}: SectionProps) {
    const listId = useId();
    const open = section.label === null || state.isGroupOpen(section.group);
    const showNew = section.items.some(hasNewItem);

    return (
        <div className="mb-5 last:mb-0" data-nav-group={section.group}>
            {section.label && (
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={listId}
                    onClick={() => state.setGroupOpen(section.group, !open)}
                    className="mb-1 flex min-h-8 w-full items-center gap-1.5 rounded-md px-2.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none pointer-coarse:min-h-10"
                >
                    <span className="flex-1 text-start">{section.label}</span>
                    {!open && showNew && <NewDot />}
                    <ChevronDown
                        aria-hidden="true"
                        className={cn(
                            'size-3.5 transition-transform motion-reduce:transition-none',
                            !open && '-rotate-90 rtl:rotate-90',
                        )}
                    />
                </button>
            )}
            <ul id={listId} hidden={!open} className="space-y-0.5">
                {section.items.map((node) => (
                    <SidebarItem
                        key={node.key}
                        node={node}
                        depth={0}
                        state={state}
                        activeKey={activeKey}
                        highlightKey={highlightKey}
                        onNavigate={onNavigate}
                        showChildren={section.group !== 'dashboard'}
                    />
                ))}
            </ul>
        </div>
    );
}

type ItemProps = {
    node: NavNode;
    depth: number;
    state: NavState;
    activeKey: string | null;
    highlightKey: string | null;
    onNavigate?: () => void;
    showChildren: boolean;
};

// One link. Items with children get a button that opens and closes them.
function SidebarItem({
    node,
    depth,
    state,
    activeKey,
    highlightKey,
    onNavigate,
    showChildren,
}: ItemProps) {
    const childrenId = useId();
    const hasChildren = showChildren && node.children.length > 0;
    const open = hasChildren && state.isItemOpen(node.key);
    const isActive = node.key === activeKey;
    const containsActive =
        !isActive &&
        activeKey !== null &&
        node.children.some(
            (child) =>
                child.key === activeKey ||
                child.children.some((grand) => grand.key === activeKey),
        );
    const isNew = hasChildren ? hasNewItem(node) : Boolean(node.isNew);
    const isHighlighted = node.key === highlightKey;

    return (
        <li>
            <div
                data-nav-key={node.key}
                data-highlighted={isHighlighted || undefined}
                className={cn(
                    'group flex items-center rounded-md transition-colors',
                    isActive
                        ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                        : 'text-sidebar-foreground hover:bg-sidebar-accent/70 hover:text-sidebar-accent-foreground',
                    isHighlighted && 'nav-highlight',
                )}
            >
                <Link
                    href={node.href}
                    prefetch
                    onClick={onNavigate}
                    aria-current={isActive ? 'page' : undefined}
                    className={cn(
                        'flex min-h-10 min-w-0 flex-1 items-center gap-2.5 rounded-md text-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none pointer-fine:lg:min-h-9',
                        depth === 0 ? 'px-2.5' : 'ps-9 pe-2.5',
                        (isActive || containsActive) && 'font-medium',
                    )}
                >
                    {depth === 0 && (
                        <VersoIcon
                            name={node.icon}
                            size={18}
                            className={cn(
                                'shrink-0',
                                isActive || containsActive
                                    ? 'text-sidebar-primary'
                                    : 'text-muted-foreground group-hover:text-sidebar-accent-foreground',
                            )}
                        />
                    )}
                    {/* Long labels wrap instead of being cut, so the full name stays readable. */}
                    <span className="min-w-0 py-1.5 leading-snug [overflow-wrap:anywhere]">
                        {node.label}
                    </span>
                    {isNew && <NewBadge />}
                </Link>
                {hasChildren && (
                    <button
                        type="button"
                        aria-expanded={open}
                        aria-controls={childrenId}
                        aria-label={
                            open
                                ? t('Hide pages in {label}', {
                                      label: node.label,
                                  })
                                : t('Show pages in {label}', {
                                      label: node.label,
                                  })
                        }
                        onClick={() => state.setItemOpen(node.key, !open)}
                        className="me-0.5 flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none pointer-coarse:size-10"
                    >
                        <ChevronDown
                            aria-hidden="true"
                            className={cn(
                                'size-4 transition-transform motion-reduce:transition-none',
                                !open && '-rotate-90 rtl:rotate-90',
                            )}
                        />
                    </button>
                )}
            </div>
            {hasChildren && (
                <ul
                    id={childrenId}
                    hidden={!open}
                    className="mt-0.5 space-y-0.5"
                >
                    {node.children.map((child) => (
                        <SidebarItem
                            key={child.key}
                            node={child}
                            depth={depth + 1}
                            state={state}
                            activeKey={activeKey}
                            highlightKey={highlightKey}
                            onNavigate={onNavigate}
                            showChildren={showChildren}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

// Marks an item the user has not opened yet.
function NewBadge() {
    return (
        <span className="ms-auto shrink-0 rounded-full bg-brand-soft px-1.5 py-px text-[10px] font-semibold tracking-wide text-brand uppercase">
            {t('New')}
        </span>
    );
}

// A small mark on a closed section that holds a new item.
function NewDot() {
    return (
        <span className="flex items-center gap-1 text-[10px] font-semibold tracking-wide text-brand normal-case">
            <span
                aria-hidden="true"
                className="size-1.5 rounded-full bg-brand"
            />
            {t('New')}
        </span>
    );
}
