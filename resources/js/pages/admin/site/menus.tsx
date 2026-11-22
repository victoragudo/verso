import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    GripVertical,
    Pencil,
    Plus,
    Trash,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OptionSelect } from '@/components/verso/ui/option-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    canIndent,
    canMoveDown,
    canMoveUp,
    flatten,
    indent,
    moveDown,
    moveBlock,
    moveUp,
    newKey,
    outdent,
    remove,
    toTree,
} from '@/components/verso/site/menu-tree';
import type { FlatMenuItem } from '@/components/verso/site/menu-tree';
import { searchMenuSource } from '@/components/verso/site/menu-search';
import { useMenuDrag } from '@/components/verso/site/use-menu-drag';
import {
    EmptyState,
    PageHeader,
    SectionCard,
    StatusBadge,
    SearchableSelect,
} from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    destroy,
    index,
    items as saveItems,
    store,
    update,
} from '@/routes/admin/site/menus';
import type {
    MenuItemNode,
    MenuItemType,
    MenuItemVisibility,
    MenuLocation,
    MenuSource,
    MenuSourceItem,
    MenuSummary,
} from '@/types';

type SelectedMenu = {
    id: number;
    name: string;
    location: string | null;
    items: MenuItemNode[];
};

type Props = {
    menus: MenuSummary[];
    menu: SelectedMenu | null;
    locations: MenuLocation[];
    sources: { entries: MenuSource[]; terms: MenuSource[] };
    maxDepth: number;
    relWords: string[];
    visibilities: { value: MenuItemVisibility; label: string }[];
};

type MenuItemPayload = {
    type: MenuItemType;
    entry_id: number | null;
    term_id: number | null;
    label: string | null;
    url: string | null;
    open_in_new_tab: boolean;
    mega: boolean;
    rel: string[];
    visibility: MenuItemVisibility;
    children: MenuItemPayload[];
};

const NO_LOCATION = 'none';

const SEARCH_DELAY_MS = 250;

const FILTER_FROM_ITEMS = 8;

const TYPE_LABELS: Record<MenuItemType, string> = {
    entry: msg('Entry'),
    term: msg('Term'),
    custom: msg('Link'),
};

// Menu builder: pick a menu, add entries, terms and links, then nest and order them.
export default function MenusIndex({
    menus,
    menu,
    locations,
    sources,
    maxDepth,
    relWords,
    visibilities,
}: Props) {
    const [creating, setCreating] = useState(false);
    const menuOptions = useMemo(
        () =>
            menus.map((item) => ({
                value: String(item.id),
                label: item.name,
                description: item.location
                    ? locationLabel(locations, item.location)
                    : undefined,
            })),
        [menus, locations],
    );

    return (
        <>
            <Head title={t('Menus')} />
            <PageHeader
                icon="menus"
                title={t('Menus')}
                description={t('Build the navigation your theme shows.')}
                actions={
                    <>
                        {menus.length > 0 && menu && (
                            <SearchableSelect
                                value={String(menu.id)}
                                onValueChange={(value) =>
                                    router.get(
                                        index.url({ query: { menu: value } }),
                                    )
                                }
                                options={menuOptions}
                                searchPlaceholder={t('Search menus')}
                                emptyText={t('No menu matches.')}
                                aria-label={t('Menu to edit')}
                                className="w-full sm:w-56"
                            />
                        )}
                        <Button
                            onClick={() => setCreating(true)}
                            className="min-h-10 md:min-h-9"
                        >
                            <Plus />
                            {t('New menu')}
                        </Button>
                    </>
                }
            />

            {menu ? (
                <MenuBuilder
                    key={menu.id}
                    menu={menu}
                    locations={locations}
                    sources={sources}
                    maxDepth={maxDepth}
                    relWords={relWords}
                    visibilities={visibilities}
                />
            ) : (
                <SectionCard>
                    <EmptyState
                        icon="menus"
                        title={t('No menus yet.')}
                        description={t('Create one to start.')}
                        action={
                            <Button
                                variant="outline"
                                onClick={() => setCreating(true)}
                            >
                                <Plus />
                                {t('New menu')}
                            </Button>
                        }
                    />
                </SectionCard>
            )}

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('New menu')}</DialogTitle>
                    </DialogHeader>
                    <MenuSettingsForm
                        menu={null}
                        locations={locations}
                        onSaved={() => setCreating(false)}
                    />
                </DialogContent>
            </Dialog>
        </>
    );
}

function locationLabel(locations: MenuLocation[], name: string): string {
    return locations.find((location) => location.name === name)?.label ?? name;
}

// Name and location of a menu. Creates a menu when menu is null.
function MenuSettingsForm({
    menu,
    locations,
    onSaved,
}: {
    menu: SelectedMenu | null;
    locations: MenuLocation[];
    onSaved?: () => void;
}) {
    const form = useForm({
        name: menu?.name ?? '',
        location: menu?.location ?? NO_LOCATION,
    });
    const { data, setData, errors, processing } = form;
    const prefix = menu ? `menu-${menu.id}` : 'new-menu';

    form.transform((values) => ({
        ...values,
        location: values.location === NO_LOCATION ? null : values.location,
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onSaved?.() };

        if (menu) {
            form.put(update.url(menu.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <form onSubmit={submit} className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-name`}>{t('Name')}</Label>
                <Input
                    id={`${prefix}-name`}
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    placeholder={t('Main navigation')}
                    required
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-location`}>{t('Show in')}</Label>
                <Select
                    value={data.location}
                    onValueChange={(value) => setData('location', value)}
                >
                    <SelectTrigger id={`${prefix}-location`}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_LOCATION}>
                            {t('Nowhere yet')}
                        </SelectItem>
                        {locations.map((location) => (
                            <SelectItem
                                key={location.name}
                                value={location.name}
                            >
                                {location.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.location} />
            </div>
            <Button
                type="submit"
                disabled={processing}
                className="min-h-10 md:min-h-9"
            >
                {menu ? t('Save menu') : t('Create menu')}
            </Button>
        </form>
    );
}

// The add panel and the item structure of one menu.
function MenuBuilder({
    menu,
    locations,
    sources,
    maxDepth,
    relWords,
    visibilities,
}: {
    menu: SelectedMenu;
    locations: MenuLocation[];
    sources: { entries: MenuSource[]; terms: MenuSource[] };
    maxDepth: number;
    relWords: string[];
    visibilities: { value: MenuItemVisibility; label: string }[];
}) {
    const [items, setItems] = useState<FlatMenuItem[]>(() =>
        flatten(menu.items),
    );
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [editing, setEditing] = useState<string | null>(null);
    const { errors } = usePage().props as { errors: Record<string, string> };
    const itemErrors = Object.entries(errors).filter(([key]) =>
        key.startsWith('items'),
    );

    const [announcement, setAnnouncement] = useState('');

    const change = (next: FlatMenuItem[]) => {
        setItems(next);
        setDirty(true);
    };

    const { listRef, drag, handleProps } = useMenuDrag({
        count: items.length,
        onDrop: (from, to) => {
            const next = moveBlock(items, from, to, maxDepth);

            if (next === items) {
                setAnnouncement(t('That spot would make the menu too deep.'));

                return;
            }

            const moved = items[from];
            change(next);
            setAnnouncement(
                t(
                    'Moved {name} to position {position, number} of {total, number}.',
                    {
                        name: moved.label || moved.title,
                        position:
                            next.findIndex((item) => item.key === moved.key) +
                            1,
                        total: next.length,
                    },
                ),
            );
        },
    });

    const add = (added: Omit<FlatMenuItem, 'key' | 'depth'>[]) =>
        change([
            ...items,
            ...added.map((item) => ({ ...item, key: newKey(), depth: 0 })),
        ]);

    const patch = (key: string, values: Partial<FlatMenuItem>) =>
        change(
            items.map((item) =>
                item.key === key ? { ...item, ...values } : item,
            ),
        );

    const save = () => {
        router.put(
            saveItems.url(menu.id),
            { items: toPayload(toTree(items)) },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => setDirty(false),
            },
        );
    };

    const removeMenu = () => {
        if (
            window.confirm(t('Delete the menu “{name}”?', { name: menu.name }))
        ) {
            router.delete(destroy.url(menu.id));
        }
    };

    return (
        <div className="grid items-start gap-4 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]">
            <div className="flex min-w-0 flex-col gap-4">
                <SectionCard
                    icon="menus"
                    title={t('Structure')}
                    description={t(
                        'Drag the handles or use the arrows to order and nest items, up to {count, plural, one {# level} other {# levels}}.',
                        { count: maxDepth },
                    )}
                    actions={
                        <>
                            {dirty && (
                                <StatusBadge tone="warning">
                                    {t('Unsaved changes')}
                                </StatusBadge>
                            )}
                            <Button
                                onClick={save}
                                disabled={saving}
                                className="min-h-10 md:min-h-9"
                            >
                                {t('Save items')}
                            </Button>
                        </>
                    }
                    contentClassName="grid gap-2"
                >
                    {itemErrors.length > 0 && (
                        <div
                            role="alert"
                            className="rounded-md bg-danger-soft p-3 text-sm text-danger-text"
                        >
                            {itemErrors.map(([key, message]) => (
                                <p key={key}>{message}</p>
                            ))}
                        </div>
                    )}
                    {items.length === 0 && (
                        <EmptyState
                            icon="menus"
                            title={t('This menu is empty.')}
                            description={t(
                                'Add entries, terms or links with the panels next to it.',
                            )}
                            className="py-8"
                        />
                    )}
                    <p role="status" className="sr-only">
                        {announcement}
                    </p>
                    <div ref={listRef} className="grid gap-2">
                        {items.map((item, position) => (
                            <MenuItemRow
                                key={item.key}
                                item={item}
                                relWords={relWords}
                                visibilities={visibilities}
                                dragHandle={handleProps(position)}
                                dragging={drag?.from === position}
                                dropBefore={
                                    drag !== null &&
                                    drag.to === position &&
                                    drag.to !== drag.from &&
                                    drag.to !== drag.from + 1
                                }
                                dropAfter={
                                    drag !== null &&
                                    position === items.length - 1 &&
                                    drag.to === items.length &&
                                    drag.from !== items.length - 1
                                }
                                editing={editing === item.key}
                                onEdit={() =>
                                    setEditing(
                                        editing === item.key ? null : item.key,
                                    )
                                }
                                onChange={(values) => patch(item.key, values)}
                                onUp={
                                    canMoveUp(items, position)
                                        ? () => change(moveUp(items, position))
                                        : undefined
                                }
                                onDown={
                                    canMoveDown(items, position)
                                        ? () =>
                                              change(moveDown(items, position))
                                        : undefined
                                }
                                onIndent={
                                    canIndent(items, position, maxDepth)
                                        ? () =>
                                              change(
                                                  indent(
                                                      items,
                                                      position,
                                                      maxDepth,
                                                  ),
                                              )
                                        : undefined
                                }
                                onOutdent={
                                    item.depth > 0
                                        ? () => change(outdent(items, position))
                                        : undefined
                                }
                                onRemove={() => change(remove(items, position))}
                            />
                        ))}
                    </div>
                </SectionCard>

                <SectionCard
                    icon="settings"
                    title={t('Menu settings')}
                    description={t('Name this menu and pick where it shows.')}
                    footer={
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="me-auto min-h-10 text-destructive-foreground md:min-h-8"
                            onClick={removeMenu}
                        >
                            <Trash />
                            {t('Delete menu')}
                        </Button>
                    }
                >
                    <MenuSettingsForm menu={menu} locations={locations} />
                </SectionCard>
            </div>

            <div className="flex min-w-0 flex-col gap-4">
                {[...sources.entries, ...sources.terms].map((source) => (
                    <SourcePicker
                        key={`${source.kind}-${source.name}`}
                        source={source}
                        onAdd={(picked) =>
                            add(
                                picked.map((item) =>
                                    source.kind === 'entry'
                                        ? linkItem('entry', item, {
                                              entry_id: item.id,
                                          })
                                        : linkItem('term', item, {
                                              term_id: item.id,
                                          }),
                                ),
                            )
                        }
                    />
                ))}
                <CustomLinkForm
                    onAdd={(label, url) =>
                        add([
                            linkItem(
                                'custom',
                                { id: 0, title: label },
                                { label, url },
                            ),
                        ])
                    }
                />
            </div>
        </div>
    );
}

function linkItem(
    type: MenuItemType,
    source: { id: number; title: string },
    values: Partial<FlatMenuItem>,
): Omit<FlatMenuItem, 'key' | 'depth'> {
    return {
        id: null,
        type,
        entry_id: null,
        term_id: null,
        label: null,
        url: null,
        open_in_new_tab: false,
        mega: false,
        rel: [],
        visibility: 'everyone',
        title: source.title,
        visible: true,
        ...values,
    };
}

// Only the fields the server stores.
function toPayload(nodes: MenuItemNode[]): MenuItemPayload[] {
    return nodes.map((node) => ({
        type: node.type,
        entry_id: node.entry_id,
        term_id: node.term_id,
        label: node.label,
        url: node.url,
        open_in_new_tab: node.open_in_new_tab,
        mega: node.mega,
        rel: node.rel,
        visibility: node.visibility,
        children: toPayload(node.children),
    }));
}

// A checkbox list of entries or terms with an "Add to menu" button. Long lists search on the server.
function SourcePicker({
    source,
    onAdd,
}: {
    source: MenuSource;
    onAdd: (items: MenuSourceItem[]) => void;
}) {
    const [picked, setPicked] = useState<MenuSourceItem[]>([]);
    const [filter, setFilter] = useState('');
    const [found, setFound] = useState<MenuSourceItem[] | null>(null);
    const [searching, setSearching] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);
    const query = filter.trim();
    const searchOnServer = source.hasMore && query !== '';

    useEffect(() => {
        if (!searchOnServer) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setSearching(true);
            setSearchError(null);
            searchMenuSource(source.kind, source.name, query, controller.signal)
                .then((result) => setFound(result.items))
                .catch((error: unknown) => {
                    if (!controller.signal.aborted) {
                        setSearchError(
                            error instanceof Error
                                ? error.message
                                : t('Could not search this list.'),
                        );
                    }
                })
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setSearching(false);
                    }
                });
        }, SEARCH_DELAY_MS);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [searchOnServer, source.kind, source.name, query]);

    const visible = searchOnServer
        ? (found ?? [])
        : source.items.filter((item) =>
              item.title.toLowerCase().includes(query.toLowerCase()),
          );
    const pickedIds = picked.map((item) => item.id);

    const toggle = (item: MenuSourceItem, checked: boolean) =>
        setPicked(
            checked
                ? [...picked, item]
                : picked.filter((value) => value.id !== item.id),
        );

    return (
        <SectionCard title={source.label} contentClassName="grid gap-3">
            {source.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('Nothing published yet.')}
                </p>
            ) : (
                <>
                    {(source.items.length > FILTER_FROM_ITEMS ||
                        source.hasMore) && (
                        <Input
                            type="search"
                            value={filter}
                            onChange={(event) => setFilter(event.target.value)}
                            placeholder={t('Search {list}', {
                                list: source.label.toLowerCase(),
                            })}
                            aria-label={t('Search {list}', {
                                list: source.label.toLowerCase(),
                            })}
                        />
                    )}
                    <div
                        className="-mx-2 grid max-h-52 overflow-y-auto"
                        aria-busy={searching}
                    >
                        {visible.map((item) => (
                            <label
                                key={item.id}
                                className="flex min-h-10 cursor-pointer items-center gap-2 rounded-md px-2 text-sm transition-colors hover:bg-muted/50 md:min-h-8"
                            >
                                <Checkbox
                                    checked={pickedIds.includes(item.id)}
                                    onCheckedChange={(checked) =>
                                        toggle(item, checked === true)
                                    }
                                />
                                <span className="truncate">{item.title}</span>
                            </label>
                        ))}
                        {searchError && (
                            <p
                                role="alert"
                                className="px-2 text-sm text-danger-text"
                            >
                                {searchError}
                            </p>
                        )}
                        {!searching &&
                            !searchError &&
                            query !== '' &&
                            visible.length === 0 && (
                                <p className="px-2 text-sm text-muted-foreground">
                                    {t('No matches.')}
                                </p>
                            )}
                    </div>
                    {source.hasMore && query === '' && (
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Showing the first {count, number}. Search to find more.',
                                { count: source.items.length },
                            )}
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="min-h-10 md:min-h-8"
                        disabled={picked.length === 0}
                        onClick={() => {
                            onAdd(picked);
                            setPicked([]);
                        }}
                    >
                        {picked.length > 0
                            ? t('Add to menu ({count, number})', {
                                  count: picked.length,
                              })
                            : t('Add to menu')}
                    </Button>
                </>
            )}
        </SectionCard>
    );
}

// Form to add a link to any URL.
function CustomLinkForm({
    onAdd,
}: {
    onAdd: (label: string, url: string) => void;
}) {
    const [label, setLabel] = useState('');
    const [url, setUrl] = useState('');

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (label.trim() === '' || url.trim() === '') {
            return;
        }

        onAdd(label.trim(), url.trim());
        setLabel('');
        setUrl('');
    };

    return (
        <SectionCard title={t('Custom link')}>
            <form onSubmit={submit} className="grid gap-3">
                <div className="grid gap-2">
                    <Label htmlFor="custom-url">{t('URL')}</Label>
                    <Input
                        id="custom-url"
                        value={url}
                        onChange={(event) => setUrl(event.target.value)}
                        placeholder={t('https:// or /contact')}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="custom-label">{t('Link text')}</Label>
                    <Input
                        id="custom-label"
                        value={label}
                        onChange={(event) => setLabel(event.target.value)}
                    />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    className="min-h-10 md:min-h-8"
                >
                    {t('Add to menu')}
                </Button>
            </form>
        </SectionCard>
    );
}

// One item in the structure list, with order and nesting controls.
function MenuItemRow({
    item,
    relWords,
    visibilities,
    dragHandle,
    dragging,
    dropBefore,
    dropAfter,
    editing,
    onEdit,
    onChange,
    onUp,
    onDown,
    onIndent,
    onOutdent,
    onRemove,
}: {
    item: FlatMenuItem;
    relWords: string[];
    visibilities: { value: MenuItemVisibility; label: string }[];
    dragHandle: ReturnType<ReturnType<typeof useMenuDrag>['handleProps']>;
    dragging: boolean;
    dropBefore: boolean;
    dropAfter: boolean;
    editing: boolean;
    onEdit: () => void;
    onChange: (values: Partial<FlatMenuItem>) => void;
    onUp?: () => void;
    onDown?: () => void;
    onIndent?: () => void;
    onOutdent?: () => void;
    onRemove: () => void;
}) {
    const name = item.label || item.title;

    return (
        <div
            data-menu-row
            className={cn(
                'relative ms-[calc(var(--depth)*0.75rem)] min-w-0 rounded-md border bg-card has-[[data-drag-handle]:focus-visible]:ring-[3px] has-[[data-drag-handle]:focus-visible]:ring-ring/50 sm:ms-[calc(var(--depth)*1.5rem)]',
                dragging && 'z-10 scale-[1.01] shadow-md',
                dropBefore &&
                    'before:pointer-events-none before:absolute before:inset-x-0 before:-top-1.5 before:h-0.5 before:rounded-full before:bg-primary',
                dropAfter &&
                    'after:pointer-events-none after:absolute after:inset-x-0 after:-bottom-1.5 after:h-0.5 after:rounded-full after:bg-primary',
            )}
            style={{ '--depth': item.depth } as CSSProperties}
        >
            <div className="flex flex-wrap items-center gap-2 p-2 ps-1">
                <button
                    type="button"
                    data-drag-handle
                    aria-label={t(
                        'Drag {name} to move it. The arrow buttons move it with the keyboard.',
                        { name },
                    )}
                    title={t('Drag to move')}
                    className="flex size-10 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground select-none [-webkit-user-select:none] hover:bg-accent focus-visible:bg-accent focus-visible:outline-none active:cursor-grabbing md:size-8"
                    {...dragHandle}
                >
                    <GripVertical aria-hidden="true" className="size-4" />
                </button>
                <span className="min-w-0 flex-1 basis-32 truncate text-sm font-medium text-foreground">
                    {name}
                </span>
                {!item.visible && (
                    <StatusBadge tone="warning">{t('Hidden')}</StatusBadge>
                )}
                <Badge variant="secondary">{t(TYPE_LABELS[item.type])}</Badge>
                <div className="flex flex-wrap items-center">
                    <IconButton
                        label={t('Move {name} up', { name })}
                        onClick={onUp}
                    >
                        <ChevronUp />
                    </IconButton>
                    <IconButton
                        label={t('Move {name} down', { name })}
                        onClick={onDown}
                    >
                        <ChevronDown />
                    </IconButton>
                    <IconButton
                        label={t('Move {name} out one level', { name })}
                        onClick={onOutdent}
                    >
                        <ChevronLeft />
                    </IconButton>
                    <IconButton
                        label={t('Nest {name} under the item above', { name })}
                        onClick={onIndent}
                    >
                        <ChevronRight />
                    </IconButton>
                    <IconButton
                        label={t('Edit {name}', { name })}
                        onClick={onEdit}
                    >
                        <Pencil />
                    </IconButton>
                    <IconButton
                        label={t('Remove {name}', { name })}
                        onClick={onRemove}
                        className="text-destructive-foreground"
                    >
                        <Trash />
                    </IconButton>
                </div>
            </div>
            {editing && (
                <div className="grid gap-3 border-t p-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${item.key}-label`}>
                            {t('Link text')}
                        </Label>
                        <Input
                            id={`${item.key}-label`}
                            value={item.label ?? ''}
                            placeholder={item.title}
                            onChange={(event) =>
                                onChange({
                                    label: event.target.value || null,
                                })
                            }
                        />
                    </div>
                    {item.type === 'custom' && (
                        <div className="grid gap-2">
                            <Label htmlFor={`${item.key}-url`}>
                                {t('URL')}
                            </Label>
                            <Input
                                id={`${item.key}-url`}
                                value={item.url ?? ''}
                                onChange={(event) =>
                                    onChange({ url: event.target.value })
                                }
                            />
                        </div>
                    )}
                    <label className="flex min-h-10 cursor-pointer items-center gap-2 text-sm sm:col-span-2 md:min-h-8">
                        <Checkbox
                            checked={item.open_in_new_tab}
                            onCheckedChange={(checked) =>
                                onChange({ open_in_new_tab: checked === true })
                            }
                        />
                        {t('Open in a new tab')}
                    </label>
                    <div className="grid gap-2">
                        <Label htmlFor={`${item.key}-visibility`}>
                            {t('Who sees it')}
                        </Label>
                        <OptionSelect
                            id={`${item.key}-visibility`}
                            value={item.visibility}
                            onValueChange={(visibility) =>
                                onChange({
                                    visibility:
                                        visibility as MenuItemVisibility,
                                })
                            }
                            options={visibilities}
                        />
                    </div>
                    {item.depth === 0 && (
                        <label className="flex min-h-10 cursor-pointer items-center gap-2 self-end text-sm md:min-h-8">
                            <Checkbox
                                checked={item.mega}
                                onCheckedChange={(checked) =>
                                    onChange({ mega: checked === true })
                                }
                            />
                            {t('Show sub items as a wide panel (mega menu)')}
                        </label>
                    )}
                    <fieldset className="grid gap-2 sm:col-span-2">
                        <legend className="mb-1 text-sm font-medium">
                            {t('Link type for search engines')}
                        </legend>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Mark paid or untrusted links. Most links need none.',
                            )}
                        </p>
                        <div className="flex flex-wrap gap-x-4 gap-y-1">
                            {relWords.map((word) => (
                                <label
                                    key={word}
                                    className="flex min-h-10 cursor-pointer items-center gap-2 text-sm md:min-h-8"
                                >
                                    <Checkbox
                                        checked={item.rel.includes(word)}
                                        onCheckedChange={(checked) =>
                                            onChange({
                                                rel:
                                                    checked === true
                                                        ? [...item.rel, word]
                                                        : item.rel.filter(
                                                              (rel) =>
                                                                  rel !== word,
                                                          ),
                                            })
                                        }
                                    />
                                    {word}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                </div>
            )}
        </div>
    );
}

// Small square icon button used to move and edit menu items.
function IconButton({
    label,
    onClick,
    className,
    children,
}: {
    label: string;
    onClick?: () => void;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className={cn('size-10 md:size-8', className)}
            onClick={onClick}
            disabled={!onClick}
            aria-label={label}
            title={label}
        >
            {children}
        </Button>
    );
}

MenusIndex.layout = {
    breadcrumbs: [{ title: msg('Menus'), href: index() }],
};
