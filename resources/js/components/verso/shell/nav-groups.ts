import { msg, t } from '@/lib/i18n';
import type { VersoNavGroup, VersoNavItem } from '@/types';

// One sidebar item with the items shown inside it.
export type NavNode = VersoNavItem & { children: NavNode[] };

export type NavSection = {
    group: VersoNavGroup;
    label: string | null;
    items: NavNode[];
};

// Section names. The dashboard section has no heading.
export const GROUP_LABELS: Record<VersoNavGroup, string | null> = {
    dashboard: null,
    content: msg('Content'),
    design: msg('Design'),
    tools: msg('Tools'),
    administration: msg('Administration'),
    settings: msg('Settings'),
};

export const GROUP_ORDER: VersoNavGroup[] = [
    'dashboard',
    'content',
    'design',
    'tools',
    'administration',
    'settings',
];

export function groupLabel(group: VersoNavGroup): string | null {
    const label = GROUP_LABELS[group];

    return label ? t(label) : null;
}

// Turns the flat list from the server into a tree, keeping the server order.
// A child whose parent is missing is shown at the top of its group.
export function buildNavTree(items: VersoNavItem[]): NavNode[] {
    const nodes = new Map<string, NavNode>();

    for (const item of items) {
        nodes.set(item.key, { ...item, children: [] });
    }

    const roots: NavNode[] = [];

    for (const item of items) {
        const node = nodes.get(item.key)!;
        const parent = item.parent ? nodes.get(item.parent) : undefined;

        if (parent && parent !== node) {
            parent.children.push(node);
        } else {
            roots.push(node);
        }
    }

    return roots;
}

// Splits the nav into sidebar sections, in the fixed group order.
export function groupNavItems(items: VersoNavItem[]): NavSection[] {
    const tree = buildNavTree(items);

    return GROUP_ORDER.map((group) => ({
        group,
        label: groupLabel(group),
        items: tree.filter((node) => (node.group ?? 'content') === group),
    })).filter((section) => section.items.length > 0);
}

function pathOf(href: string): string {
    return href.split(/[?#]/)[0].replace(/\/+$/, '') || '/';
}

// Finds the nav item for the current path: the longest link that matches.
// Dashboard links only match their exact path, so /admin does not match every page.
// On a tie the later item wins, so a child beats the parent item that links to it.
export function activeNavKey(
    items: VersoNavItem[],
    currentPath: string,
): string | null {
    const current = pathOf(currentPath);
    let best: { key: string; length: number } | null = null;

    for (const item of items) {
        // Links to a part of a page (#invitations) never mark the page itself.
        if (item.href.includes('#')) {
            continue;
        }

        const path = pathOf(item.href);
        const matches =
            current === path ||
            (item.group !== 'dashboard' && current.startsWith(`${path}/`));

        if (matches && (best === null || path.length >= best.length)) {
            best = { key: item.key, length: path.length };
        }
    }

    return best?.key ?? null;
}

// Keys of the parents of an item, from the top down.
export function ancestorKeys(items: VersoNavItem[], key: string): string[] {
    const byKey = new Map(items.map((item) => [item.key, item]));
    const keys: string[] = [];
    let item = byKey.get(key);

    while (
        item?.parent &&
        byKey.has(item.parent) &&
        !keys.includes(item.parent)
    ) {
        keys.unshift(item.parent);
        item = byKey.get(item.parent);
    }

    return keys;
}

// The labels from the section down to the item, like ["Tools", "Shop", "Orders"].
export function navPath(items: VersoNavItem[], key: string): string[] {
    const byKey = new Map(items.map((item) => [item.key, item]));
    const item = byKey.get(key);

    if (!item) {
        return [];
    }

    const labels = [
        ...ancestorKeys(items, key).map((parent) => byKey.get(parent)!.label),
        item.label,
    ];
    const top = byKey.get(ancestorKeys(items, key)[0] ?? key)!;
    const section = groupLabel(top.group);

    return section && top.key !== 'dashboard' ? [section, ...labels] : labels;
}

// True when the item or one of its children is new for the user.
export function hasNewItem(node: NavNode): boolean {
    return Boolean(node.isNew) || node.children.some(hasNewItem);
}
