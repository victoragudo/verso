import { router, usePage } from '@inertiajs/react';
import { CornerDownLeft } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { VersoIcon } from '@/components/verso/icons';
import { msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { edit as profile } from '@/routes/profile';
import type { VersoNavItem } from '@/types';
import { groupNavItems, navPath } from './nav-groups';
import type { NavNode } from './nav-groups';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCloseAutoFocus?: (event: Event) => void;
};

export type Command = {
    key: string;
    label: string;
    section: string;
    // Labels from the section down to the item, like ["Tools", "Shop", "Orders"].
    path: string[];
    keywords: string[];
    href: string;
    icon: string;
};

const PROFILE_LABEL = msg('Your profile');
const ACCOUNT_LABEL = msg('Account');
const GENERAL_LABEL = msg('General');
const PROFILE_KEYWORDS = [msg('Password'), msg('Profile')] as const;

export function withSourceKeywords(
    translated: readonly string[],
    source: readonly string[],
): string[] {
    return [...new Set([...translated, ...source])];
}

export function extraCommands(): Command[] {
    const label = t(PROFILE_LABEL);
    const section = t(ACCOUNT_LABEL);

    return [
        {
            key: 'account.profile',
            label,
            section,
            path: [section, label],
            keywords: withSourceKeywords(
                PROFILE_KEYWORDS.map((keyword) => t(keyword)),
                [...PROFILE_KEYWORDS, PROFILE_LABEL, ACCOUNT_LABEL],
            ),
            href: profile().url,
            icon: 'users',
        },
    ];
}

// Every nav item and child, with the path that leads to it. Only items the server sent are here,
// so hidden items never show up in the search.
export function toCommands(items: VersoNavItem[]): Command[] {
    return groupNavItems(items).flatMap((section) => {
        const walk = (nodes: NavNode[]): Command[] =>
            nodes.flatMap((node) => [
                {
                    key: node.key,
                    label: node.label,
                    section: section.label ?? t(GENERAL_LABEL),
                    path: navPath(items, node.key),
                    keywords: node.keywords ?? [],
                    href: node.href,
                    icon: node.icon,
                },
                ...walk(node.children),
            ]);

        return walk(section.items);
    });
}

// Lowercase text without accents, so "pedidos" also finds "Pedidos" and "categorias" finds "categorías".
export function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

// How well a command matches the search. 0 means no match. The label counts most, then keywords, then the path.
export function matchScore(command: Command, query: string): number {
    const words = normalize(query).split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return 1;
    }

    const label = normalize(command.label);
    const keywords = command.keywords.map(normalize);
    const path = normalize(command.path.join(' '));
    let score = 0;

    for (const word of words) {
        if (label.startsWith(word)) {
            score += 4;
        } else if (label.includes(word)) {
            score += 3;
        } else if (keywords.some((keyword) => keyword.startsWith(word))) {
            score += 2;
        } else if (
            keywords.some((keyword) => keyword.includes(word)) ||
            path.includes(word)
        ) {
            score += 1;
        } else {
            return 0;
        }
    }

    return score;
}

// The commands that match, best first. Ties keep the sidebar order.
export function searchCommands(commands: Command[], query: string): Command[] {
    if (normalize(query) === '') {
        return commands;
    }

    return commands
        .map((command, index) => ({
            command,
            index,
            score: matchScore(command, query),
        }))
        .filter((result) => result.score > 0)
        .sort((a, b) => b.score - a.score || a.index - b.index)
        .map((result) => result.command);
}

// Opens the command menu with Cmd+K or Ctrl+K from anywhere in the admin.
export function useCommandShortcut(onOpen: () => void) {
    useEffect(() => {
        const handler = (event: globalThis.KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                onOpen();
            }
        };

        window.addEventListener('keydown', handler);

        return () => window.removeEventListener('keydown', handler);
    }, [onOpen]);
}

// Search box that jumps to any admin page the user can see.
export function CommandMenu({ open, onOpenChange, onCloseAutoFocus }: Props) {
    const { versoNav } = usePage().props;
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const listRef = useRef<HTMLUListElement>(null);

    const commands = useMemo(
        () => [...toCommands(versoNav), ...extraCommands()],
        [versoNav],
    );

    const results = useMemo(
        () => searchCommands(commands, query),
        [commands, query],
    );
    const searching = normalize(query) !== '';

    const changeOpen = (next: boolean) => {
        if (!next) {
            setQuery('');
            setActiveIndex(0);
        }

        onOpenChange(next);
    };

    const go = (command: Command) => {
        changeOpen(false);
        router.visit(command.href);
    };

    const moveTo = (index: number) => {
        const count = results.length;

        if (count === 0) {
            return;
        }

        const next = (index + count) % count;
        setActiveIndex(next);
        listRef.current
            ?.querySelector(`[data-index="${next}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            moveTo(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            moveTo(activeIndex - 1);
        } else if (event.key === 'Enter' && results[activeIndex]) {
            event.preventDefault();
            go(results[activeIndex]);
        }
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogContent
                onCloseAutoFocus={onCloseAutoFocus}
                className="mt-[12dvh] gap-0 overflow-hidden p-0 text-[clamp(0.875rem,0.78rem+0.22vw,1.125rem)] sm:max-w-[clamp(32rem,40vw,60rem)] [&>button:last-child]:hidden"
            >
                <DialogTitle className="sr-only">
                    {t('Search admin pages')}
                </DialogTitle>
                <DialogDescription className="sr-only">
                    {t(
                        'Type to filter, use the arrow keys to move and Enter to open.',
                    )}
                </DialogDescription>
                <div className="flex items-center gap-2 border-b px-4">
                    <VersoIcon
                        name="search"
                        size={18}
                        className="shrink-0 text-muted-foreground"
                    />
                    <input
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setActiveIndex(0);
                        }}
                        onKeyDown={onKeyDown}
                        placeholder={t('Search pages and settings')}
                        role="combobox"
                        aria-expanded="true"
                        aria-controls="verso-command-list"
                        aria-activedescendant={
                            results[activeIndex]
                                ? `verso-command-${results[activeIndex].key}`
                                : undefined
                        }
                        className="h-[clamp(3rem,2.6rem+1vw,4rem)] w-full bg-transparent text-base text-foreground outline-none placeholder:text-muted-foreground sm:text-[1em]"
                    />
                    <kbd className="hidden rounded border bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground sm:inline">
                        Esc
                    </kbd>
                </div>
                <ul
                    ref={listRef}
                    id="verso-command-list"
                    role="listbox"
                    className="max-h-[clamp(20rem,60dvh,48rem)] overflow-y-auto overscroll-contain p-2"
                >
                    {results.length === 0 && (
                        <li className="px-3 py-8 text-center text-sm text-muted-foreground">
                            {t('No pages match “{query}”.', { query })}
                        </li>
                    )}
                    {results.map((command, index) => {
                        const showSection =
                            !searching &&
                            (index === 0 ||
                                results[index - 1].section !== command.section);
                        const trail = command.path.slice(0, -1);
                        const isActive = index === activeIndex;

                        return (
                            <li key={command.key} role="presentation">
                                {showSection && (
                                    <p className="px-2 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                        {command.section}
                                    </p>
                                )}
                                <div
                                    id={`verso-command-${command.key}`}
                                    role="option"
                                    aria-selected={isActive}
                                    data-index={index}
                                    onMouseMove={() => setActiveIndex(index)}
                                    onClick={() => go(command)}
                                    className={cn(
                                        'flex min-h-[clamp(2.5rem,2.2rem+0.6vw,3.25rem)] cursor-pointer items-center gap-3 rounded-md px-2',
                                        isActive
                                            ? 'bg-accent text-foreground'
                                            : 'text-body',
                                    )}
                                >
                                    <VersoIcon
                                        name={command.icon}
                                        size={18}
                                        className={
                                            isActive
                                                ? 'text-brand'
                                                : 'text-muted-foreground'
                                        }
                                    />
                                    <span className="flex min-w-0 flex-1 items-baseline gap-2">
                                        <span
                                            data-command-label
                                            className="truncate"
                                        >
                                            {command.label}
                                        </span>
                                        {trail.length > 0 && (
                                            <span className="truncate text-[0.85em] text-muted-foreground">
                                                {trail.join(' › ')}
                                            </span>
                                        )}
                                    </span>
                                    {isActive && (
                                        <CornerDownLeft className="size-3.5 text-muted-foreground" />
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            </DialogContent>
        </Dialog>
    );
}
