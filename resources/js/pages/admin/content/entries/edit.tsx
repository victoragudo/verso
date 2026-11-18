import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { featureEnabled } from '@/builder';
import type { EditorManifest } from '@/builder';
import { EntryCustomCode } from '@/components/verso/editor/entry-custom-code';
import { findBlock, replaceBlock } from '@/builder/tree';
import { BlockInspector } from '@/components/verso/editor/block-inspector';
import type { Editor } from '@tiptap/react';
import {
    Copy,
    ExternalLink,
    History,
    Keyboard,
    Save,
    Trash2,
} from 'lucide-react';
import { Fragment, useMemo, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    PluginPanel,
    panelValuesFromMeta,
} from '@/components/verso/content/plugin-panel';
import {
    DEFAULT_TEMPLATE,
    templateFromMeta,
    templateOptions,
} from '@/components/verso/content/page-template';
import { PartChoiceFields } from '@/components/verso/content/part-choice-fields';
import {
    partErrors,
    partsForMeta,
    partsFromMeta,
} from '@/components/verso/content/part-choices';
import { EditorLayout } from '@/components/verso/content/editor-layout';
import {
    EditorSettingsTabs,
    type EditorSettingsTab,
} from '@/components/verso/content/editor-settings-tabs';
import { EditorShortcutsDialog } from '@/components/verso/content/editor-shortcuts-dialog';
import { inlineLabel } from '@/components/verso/content/inline-label';
import { EntryStatusBadge } from '@/components/verso/content/status-badge';
import { FieldGroupsPanel } from '@/components/verso/fields/field-groups-panel';
import { groupMatches, initialValues } from '@/components/verso/fields/logic';
import type {
    FieldGroupDefinition,
    FieldValues,
} from '@/components/verso/fields/types';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import {
    Slot,
    SlotItemView,
    slotComponentOptions,
    useSlotItems,
} from '@/components/verso/slots';
import type { EntryEditorContext, SlotItem } from '@/components/verso/slots';
import {
    DateTimePicker,
    SearchableSelect,
    SectionCard,
} from '@/components/verso/ui';
import { useEditorShortcuts } from '@/hooks/use-editor-shortcuts';
import { useEditorSidebar } from '@/hooks/use-editor-sidebar';
import { useMediaQuery } from '@/hooks/use-media-query';
import { useReturnFocus } from '@/hooks/use-return-focus';
import { useSiteDates } from '@/hooks/use-site-dates';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { msg, t, Trans } from '@/lib/i18n';
import { destroy, index, store, update } from '@/routes/admin/entries';
import {
    compare as compareRevision,
    restore as restoreRevision,
} from '@/routes/admin/entries/revisions';
import { RevisionCompareDialog } from '@/components/verso/content/revision-compare';
import type {
    Block,
    BlockPattern,
    ContentTypeInfo,
    Entry,
    EntryStatus,
    PageTemplateOption,
    PartChoice,
    PluginBlockDefinition,
    PluginEntryPanel,
    RevisionSummary,
    TaxonomyInfo,
} from '@/types';

type TermOption = { id: number; name: string; parent_id: number | null };

type Props = {
    contentType: ContentTypeInfo;
    entry: Entry | null;
    taxonomies: (TaxonomyInfo & { terms: TermOption[] })[];
    parents: { id: number; title: string; parent_id: number | null }[];
    statuses: { value: EntryStatus; label: string }[];
    revisions: RevisionSummary[];
    pluginBlocks: PluginBlockDefinition[];
    // Core blocks the server describes like plugin blocks, such as the section.
    coreBlocks?: PluginBlockDefinition[];
    editor?: EditorManifest;
    pluginPanels?: PluginEntryPanel[];
    fieldGroups?: FieldGroupDefinition[];
    pageTemplates?: PageTemplateOption[];
    partChoices?: PartChoice[];
    blockPatterns?: BlockPattern[];
    can: { publish: boolean; delete: boolean };
};

type EntryForm = {
    title: string;
    slug: string;
    status: EntryStatus;
    published_at: string;
    excerpt: string;
    parent_id: string;
    menu_order: number;
    terms: Record<string, number[]>;
};

const NO_PARENT = 'none';
const FORM_ID = 'entry-form';
const REVISIONS_ID = 'entry-revisions';
const PHONE_QUERY = '(max-width: 639.98px)';

// Places of the core cards in the side panel. Cards from the entry-sidebar slot sit between them by order.
const SIDEBAR_ORDER = {
    publish: 0,
    link: 10,
    template: 15,
    attributes: 20,
    taxonomies: 30,
    fields: 35,
    panels: 40,
    code: 90,
    revisions: 100,
};

type SidebarSection = { key: string; order: number; node: ReactNode };

// The entry meta with the values that slot components add when the editor opens.
function initialMeta(
    meta: Record<string, unknown> | undefined,
    items: SlotItem[],
): Record<string, unknown> {
    const base = { ...meta };

    return items.reduce(
        (current, item) => ({
            ...current,
            ...slotComponentOptions(item.component).initialMeta?.(
                base,
                item.props,
            ),
        }),
        base,
    );
}

// Creates or edits one entry. The key gives a fresh form when "create"
// redirects to "edit", because Inertia keeps the same page component.
// One shared empty list, so the editor keeps the same block list between renders.
const NO_CORE_BLOCKS: PluginBlockDefinition[] = [];

export default function EntryEdit(props: Props) {
    return <EntryEditor key={props.entry?.id ?? 'new'} {...props} />;
}

// The entry form: content, status, terms, parent and revisions.
function EntryEditor({
    contentType,
    entry,
    taxonomies,
    parents,
    statuses,
    revisions,
    pluginBlocks,
    coreBlocks = NO_CORE_BLOCKS,
    editor,
    pluginPanels = [],
    fieldGroups = [],
    pageTemplates = [],
    partChoices = [],
    blockPatterns = [],
    can,
}: Props) {
    const type = contentType.name;
    const { formatDate, toLocalInput, fromLocalInput, timeZone } =
        useSiteDates();
    const form = useForm<EntryForm>({
        title: entry?.title ?? '',
        slug: entry?.slug ?? '',
        status: entry?.status ?? 'draft',
        published_at: toLocalInput(entry?.published_at ?? null),
        excerpt: entry?.excerpt ?? '',
        parent_id: entry?.parent_id ? String(entry.parent_id) : NO_PARENT,
        menu_order: entry?.menu_order ?? 0,
        terms: Object.fromEntries(
            taxonomies.map((taxonomy) => [
                taxonomy.name,
                entry?.terms?.[taxonomy.name] ?? [],
            ]),
        ),
    });
    const { data, setData, errors, processing } = form;
    // Blocks live outside useForm because their attrs are free-form JSON.
    const [blocks, setBlocks] = useState<Block[]>(entry?.blocks ?? []);
    const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
    const blockError = (errors as Record<string, string | undefined>).blocks;
    const sidebarItems = useSlotItems('entry-sidebar');
    const publishItems = useSlotItems('entry-publish');
    const toolbarItems = useSlotItems('entry-toolbar');
    const [meta, setMetaState] = useState(() =>
        initialMeta(entry?.meta, [
            ...sidebarItems,
            ...publishItems,
            ...toolbarItems,
        ]),
    );
    const [template, setTemplate] = useState(() =>
        templateFromMeta(entry?.meta),
    );
    const [parts, setParts] = useState(() => partsFromMeta(entry?.meta));
    const editorBlocks = useMemo(
        () => [...coreBlocks, ...pluginBlocks],
        [coreBlocks, pluginBlocks],
    );
    const pageTemplateOptions = useMemo(
        () => templateOptions(pageTemplates, template),
        [pageTemplates, template],
    );
    const templateError = (errors as Record<string, string | undefined>)[
        'meta.template'
    ];
    const parentOptions = useMemo(
        () => [
            { value: NO_PARENT, label: t('No parent') },
            ...parents.map((parent) => ({
                value: String(parent.id),
                label: parent.title,
            })),
        ],
        [parents],
    );
    // Custom field values, saved in meta.fields. Values of groups that do not show stay as they were.
    const [fieldValues, setFieldValues] = useState<FieldValues>(() => {
        const saved = (entry?.meta?.fields ?? {}) as FieldValues;

        return {
            ...saved,
            ...initialValues(
                fieldGroups.flatMap((group) => group.fields),
                saved,
            ),
        };
    });
    const activeTemplate = template === DEFAULT_TEMPLATE ? '' : template;
    const termIds = Object.values(data.terms).flat();
    const activeFieldGroups = fieldGroups.filter((group) =>
        groupMatches(group, {
            contentType: type,
            template: activeTemplate,
            termIds,
        }),
    );
    const [panelValues, setPanelValues] = useState(() =>
        panelValuesFromMeta(pluginPanels, entry?.meta),
    );
    const [activeEditor, setActiveEditor] = useState<Editor | null>(null);
    // A new key remounts the block editor so it shows blocks replaced from outside.
    const [editorVersion, setEditorVersion] = useState(0);

    const updateMeta = (key: string, update: (current: unknown) => unknown) =>
        setMetaState((current) => ({
            ...current,
            [key]: update(current[key]),
        }));

    // Everything slot components can read and change in the editor.
    const editorContext: EntryEditorContext = {
        entry,
        contentType,
        title: data.title,
        setTitle: (value) => setData('title', value),
        excerpt: data.excerpt,
        setExcerpt: (value) => setData('excerpt', value),
        blocks,
        setBlocks,
        replaceBlocks: (value) => {
            setBlocks(value);
            setEditorVersion((version) => version + 1);
        },
        meta,
        setMeta: (key, value) => updateMeta(key, () => value),
        updateMeta,
        activeEditor,
    };

    // The status picked by "Save draft" or "Publish". Null keeps the status of the form.
    const statusOverride = useRef<EntryStatus | null>(null);
    // True while our own save or trash visit runs, so the leave check lets it pass.
    const allowVisit = useRef(false);
    const formRef = useRef<HTMLFormElement>(null);
    const snapshotOf = (status: EntryStatus) =>
        JSON.stringify([
            { ...data, status },
            blocks,
            meta,
            panelValues,
            fieldValues,
            template,
            parts,
        ]);
    const snapshot = snapshotOf(data.status);
    const [savedSnapshot, setSavedSnapshot] = useState(snapshot);
    const dirty = snapshot !== savedSnapshot;

    useUnsavedChanges(dirty, allowVisit);

    form.transform((values) => ({
        ...values,
        status: statusOverride.current ?? values.status,
        blocks,
        meta: {
            ...meta,
            ...panelValues,
            ...(fieldGroups.length > 0 || entry?.meta?.fields
                ? { fields: fieldValues }
                : {}),
            template: activeTemplate === '' ? undefined : activeTemplate,
            parts: partsForMeta(parts),
        },
        published_at: fromLocalInput(values.published_at),
        parent_id:
            values.parent_id === NO_PARENT ? null : Number(values.parent_id),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const previousStatus = data.status;
        const status = statusOverride.current ?? previousStatus;
        const submitted = snapshotOf(status);
        const options = {
            preserveScroll: true,
            onSuccess: () => setSavedSnapshot(submitted),
            onError: () => setData('status', previousStatus),
            onFinish: () => {
                allowVisit.current = false;
                statusOverride.current = null;
            },
        };

        if (status !== previousStatus) {
            setData('status', status);
        }

        allowVisit.current = true;

        if (entry) {
            form.put(
                update.url({ contentType: type, entry: entry.id }),
                options,
            );
        } else {
            form.post(store.url(type), options);
        }
    };

    // Saves with the given status, or with the status of the form. Browser checks (like the required title) run first.
    const save = (status: EntryStatus | null = null) => {
        if (processing) {
            return;
        }

        statusOverride.current = status;
        formRef.current?.requestSubmit();
    };

    const toggleTerm = (taxonomy: string, id: number, checked: boolean) => {
        const current = data.terms[taxonomy] ?? [];
        setData('terms', {
            ...data.terms,
            [taxonomy]: checked
                ? [...current, id]
                : current.filter((value) => value !== id),
        });
    };

    const userId = usePage().props.auth?.user?.id;
    const sidebarState = useEditorSidebar(userId);
    const isPhone = useMediaQuery(PHONE_QUERY);
    const [settingsTab, setSettingsTab] =
        useState<EditorSettingsTab>('document');
    const [shortcutsOpen, setShortcutsOpen] = useState(false);
    const shortcutsFocus = useReturnFocus();

    useEditorShortcuts({
        onSave: () => save(),
        onToggleSidebar: sidebarState.toggle,
    });

    const label = inlineLabel(contentType.label);
    const title = entry
        ? t('Edit {type}', { type: label })
        : t('New {type}', { type: label });
    const live = entry?.status === 'published' || entry?.status === 'scheduled';
    const inFuture =
        data.published_at !== '' &&
        data.published_at > toLocalInput(new Date().toISOString());
    const primary = primaryAction({ canPublish: can.publish, live, inFuture });
    const showSaveDraft = can.publish && !live;
    const saveState = processing
        ? t('Saving…')
        : dirty
          ? t('Unsaved changes')
          : entry
            ? t('Saved')
            : t('Not saved yet');
    const canPreview = entry?.status === 'published';

    const copyLink = async () => {
        if (!entry) {
            return;
        }

        try {
            await navigator.clipboard.writeText(entry.url);
            toast.success(t('Link copied.'));
        } catch {
            toast.error(t('The link could not be copied.'));
        }
    };

    // Opens the settings on the entry tab and scrolls to the revisions card.
    const [comparing, setComparing] = useState<string | null>(null);

    const showRevisions = () => {
        setSettingsTab('document');
        sidebarState.setOpen(true);
        requestAnimationFrame(() =>
            requestAnimationFrame(() => {
                const card = document.getElementById(REVISIONS_ID);
                card?.scrollIntoView({ block: 'start' });
                card?.focus({ preventScroll: true });
            }),
        );
    };

    const moveToTrash = () => {
        if (!entry) {
            return;
        }

        allowVisit.current = true;
        router.delete(destroy.url({ contentType: type, entry: entry.id }), {
            onFinish: () => {
                allowVisit.current = false;
            },
        });
    };

    const termCount = (taxonomy: string) => (data.terms[taxonomy] ?? []).length;

    const sidebar: SidebarSection[] = [
        {
            key: 'publish',
            order: SIDEBAR_ORDER.publish,
            node: (
                <SectionCard
                    title={t('Status and visibility')}
                    actions={
                        entry && <EntryStatusBadge status={entry.status} />
                    }
                    contentClassName="grid grid-cols-[minmax(0,1fr)] gap-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="status">{t('Status')}</Label>
                        <Select
                            value={data.status}
                            onValueChange={(value) =>
                                setData('status', value as EntryStatus)
                            }
                        >
                            <SelectTrigger id="status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {!can.publish && (
                            <p className="text-xs text-muted-foreground">
                                {t('An editor will review and publish it.')}
                            </p>
                        )}
                        <InputError message={errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="published_at">
                            {t('Publish date')}
                        </Label>
                        <DateTimePicker
                            id="published_at"
                            value={data.published_at}
                            onChange={(value) => setData('published_at', value)}
                            placeholder={t('Not set')}
                            min={
                                data.status === 'scheduled'
                                    ? toLocalInput(new Date().toISOString())
                                    : undefined
                            }
                            aria-invalid={Boolean(errors.published_at)}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('Time zone: {timeZone}', { timeZone })}
                        </p>
                        <InputError message={errors.published_at} />
                    </div>
                    {entry?.author && (
                        <p className="text-sm text-muted-foreground">
                            <Trans
                                text={t('Author: <name>{author}</name>', {
                                    author: entry.author.name,
                                })}
                                components={{
                                    name: (text) => (
                                        <span className="text-foreground">
                                            {text}
                                        </span>
                                    ),
                                }}
                            />
                        </p>
                    )}
                    <Slot name="entry-publish" context={editorContext} />
                </SectionCard>
            ),
        },
        {
            key: 'link',
            order: SIDEBAR_ORDER.link,
            node: (
                <SectionCard
                    title={t('Link')}
                    icon="site"
                    contentClassName="grid gap-2"
                >
                    <Label htmlFor="slug">{t('Slug')}</Label>
                    <Input
                        id="slug"
                        value={data.slug}
                        onChange={(event) =>
                            setData('slug', event.target.value)
                        }
                        placeholder={t('Made from the title when empty')}
                    />
                    <InputError message={errors.slug} />
                    {entry && (
                        <a
                            href={entry.url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-sm [overflow-wrap:anywhere] text-brand underline-offset-4 hover:underline"
                        >
                            {entry.url}
                        </a>
                    )}
                </SectionCard>
            ),
        },
        {
            key: 'attributes',
            order: SIDEBAR_ORDER.attributes,
            node: contentType.hierarchical && (
                <SectionCard
                    title={t('Page attributes')}
                    icon="pages"
                    contentClassName="grid grid-cols-[minmax(0,1fr)] gap-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="parent_id">{t('Parent')}</Label>
                        <SearchableSelect
                            id="parent_id"
                            value={data.parent_id}
                            onValueChange={(value) =>
                                setData('parent_id', value)
                            }
                            options={parentOptions}
                            searchPlaceholder={t('Search pages')}
                            emptyText={t('No page matches.')}
                            aria-invalid={Boolean(errors.parent_id)}
                            className="w-full"
                        />
                        <InputError message={errors.parent_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="menu_order">{t('Order')}</Label>
                        <Input
                            id="menu_order"
                            type="number"
                            value={data.menu_order}
                            onChange={(event) =>
                                setData(
                                    'menu_order',
                                    Number(event.target.value),
                                )
                            }
                        />
                    </div>
                </SectionCard>
            ),
        },
        {
            key: 'taxonomies',
            order: SIDEBAR_ORDER.taxonomies,
            node: taxonomies.map((taxonomy) => (
                <SectionCard
                    key={taxonomy.name}
                    title={taxonomy.pluralLabel}
                    icon={taxonomy.icon}
                    actions={
                        termCount(taxonomy.name) > 0 && (
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {t('{count, number} selected', {
                                    count: termCount(taxonomy.name),
                                })}
                            </span>
                        )
                    }
                    contentClassName="max-h-64 overflow-y-auto overscroll-contain px-2 py-2 sm:px-2"
                >
                    {taxonomy.terms.length === 0 ? (
                        <p className="px-2 py-2 text-sm text-muted-foreground">
                            {t('No {type} yet.', {
                                type: inlineLabel(taxonomy.pluralLabel),
                            })}
                        </p>
                    ) : (
                        <ul>
                            {taxonomy.terms.map((term) => (
                                <li key={term.id}>
                                    <label className="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-md px-2 text-sm hover:bg-muted/60 md:min-h-8">
                                        <Checkbox
                                            checked={(
                                                data.terms[taxonomy.name] ?? []
                                            ).includes(term.id)}
                                            onCheckedChange={(checked) =>
                                                toggleTerm(
                                                    taxonomy.name,
                                                    term.id,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span className="truncate">
                                            {term.name}
                                        </span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}
                </SectionCard>
            )),
        },
        {
            key: 'template',
            order: SIDEBAR_ORDER.template,
            node: (pageTemplates.length > 0 || partChoices.length > 0) && (
                <SectionCard
                    title={t('Template')}
                    icon="themes"
                    contentClassName="grid gap-2"
                >
                    {pageTemplates.length > 0 && (
                        <>
                            <Label htmlFor="template">
                                {t('Page template')}
                            </Label>
                            <SearchableSelect
                                id="template"
                                value={template}
                                onValueChange={setTemplate}
                                options={pageTemplateOptions}
                                searchPlaceholder={t('Search templates')}
                                emptyText={t('No template matches.')}
                                aria-invalid={Boolean(templateError)}
                                className="w-full"
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('Templates come from the active theme.')}
                            </p>
                            <InputError message={templateError} />
                        </>
                    )}
                    <PartChoiceFields
                        choices={partChoices}
                        value={parts}
                        onChange={setParts}
                        errors={partErrors(errors)}
                    />
                </SectionCard>
            ),
        },
        {
            key: 'fields',
            order: SIDEBAR_ORDER.fields,
            node: (
                <FieldGroupsPanel
                    groups={activeFieldGroups}
                    position="sidebar"
                    values={fieldValues}
                    onChange={setFieldValues}
                    errors={errors}
                />
            ),
        },
        {
            key: 'panels',
            order: SIDEBAR_ORDER.panels,
            node: pluginPanels.map((panel) => (
                <PluginPanel
                    key={panel.key}
                    panel={panel}
                    value={panelValues[panel.key] ?? {}}
                    onChange={(value) =>
                        setPanelValues((current) => ({
                            ...current,
                            [panel.key]: value,
                        }))
                    }
                />
            )),
        },
        {
            key: 'code',
            order: SIDEBAR_ORDER.code,
            node: entry &&
                (featureEnabled(editor, 'custom_css') ||
                    featureEnabled(editor, 'custom_js')) && (
                    <SectionCard title={t('Custom code')} icon="settings">
                        <p className="mb-3 text-sm text-muted-foreground">
                            {t('Extra CSS and JavaScript for this page only.')}
                        </p>
                        <EntryCustomCode entryId={entry.id} />
                    </SectionCard>
                ),
        },
        {
            key: 'revisions',
            order: SIDEBAR_ORDER.revisions,
            node: entry && (
                <div
                    id={REVISIONS_ID}
                    tabIndex={-1}
                    className="min-w-0 scroll-mt-14 rounded-lg focus:outline-none"
                >
                    <SectionCard
                        title={t('Revisions')}
                        icon="entries"
                        contentClassName="p-0 sm:p-0"
                    >
                        {revisions.length === 0 ? (
                            <p className="px-4 py-4 text-sm text-muted-foreground sm:px-5">
                                {t('No revisions yet.')}
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {revisions.map((revision) => (
                                    <li
                                        key={revision.id}
                                        className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm sm:px-5"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-foreground">
                                                {revision.title}
                                            </p>
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                {revision.author
                                                    ? t('{date} by {author}', {
                                                          date: formatDate(
                                                              revision.created_at,
                                                          ),
                                                          author: revision.author,
                                                      })
                                                    : formatDate(
                                                          revision.created_at,
                                                      )}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setComparing(
                                                    compareRevision({
                                                        contentType: type,
                                                        entry: entry.id,
                                                        revision: revision.id,
                                                    }).url,
                                                )
                                            }
                                        >
                                            {t('Compare')}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={restoreRevision({
                                                    contentType: type,
                                                    entry: entry.id,
                                                    revision: revision.id,
                                                })}
                                                as="button"
                                                preserveScroll
                                            >
                                                {t('Restore')}
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <RevisionCompareDialog
                            url={comparing}
                            onClose={() => setComparing(null)}
                        />
                    </SectionCard>
                </div>
            ),
        },
        ...sidebarItems.map((item) => ({
            key: `slot-${item.key}`,
            order: item.order,
            node: (
                <SlotItemView<'entry-sidebar'>
                    item={item}
                    context={editorContext}
                />
            ),
        })),
    ].sort((first, second) => first.order - second.order);

    const previewLink = canPreview && entry ? entry.url : undefined;

    return (
        <>
            <Head title={title} />
            <EditorLayout
                backHref={index.url(type)}
                backLabel={t('Back to {type}', {
                    type: inlineLabel(contentType.pluralLabel),
                })}
                wide={sidebarState.wide}
                sidebarOpen={sidebarState.open}
                onSidebarOpenChange={sidebarState.setOpen}
                status={
                    <>
                        <span className="hidden sm:inline-flex">
                            <EntryStatusBadge
                                status={entry?.status ?? 'draft'}
                            />
                        </span>
                        <p
                            role="status"
                            className="truncate text-sm text-muted-foreground"
                        >
                            {saveState}
                        </p>
                    </>
                }
                actions={
                    <>
                        {!isPhone && (
                            <Slot
                                name="entry-toolbar"
                                context={editorContext}
                            />
                        )}
                        {previewLink ? (
                            <Button
                                variant="ghost"
                                className="hidden md:inline-flex"
                                asChild
                            >
                                <a
                                    href={previewLink}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {t('Preview')}
                                    <ExternalLink aria-hidden="true" />
                                </a>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="ghost"
                                className="hidden md:inline-flex"
                                disabled
                                title={t('Publish it to see it on the site')}
                            >
                                {t('Preview')}
                            </Button>
                        )}
                        {showSaveDraft && (
                            <Button
                                type="button"
                                variant="outline"
                                className="hidden sm:inline-flex"
                                disabled={processing}
                                onClick={() => save('draft')}
                            >
                                {t('Save draft')}
                            </Button>
                        )}
                        <Button
                            type="button"
                            disabled={processing}
                            onClick={() => save(primary.status)}
                        >
                            {t(primary.label)}
                        </Button>
                    </>
                }
                menu={
                    <>
                        {previewLink ? (
                            <DropdownMenuItem className="md:hidden" asChild>
                                <a
                                    href={previewLink}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink />
                                    {t('Preview')}
                                </a>
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem className="md:hidden" disabled>
                                <ExternalLink />
                                {t('Preview')}
                            </DropdownMenuItem>
                        )}
                        {showSaveDraft && (
                            <DropdownMenuItem
                                className="sm:hidden"
                                disabled={processing}
                                onSelect={() => save('draft')}
                            >
                                <Save />
                                {t('Save draft')}
                            </DropdownMenuItem>
                        )}
                        {entry && (
                            <DropdownMenuItem onSelect={() => void copyLink()}>
                                <Copy />
                                {t('Copy link')}
                            </DropdownMenuItem>
                        )}
                        {entry && (
                            <DropdownMenuItem onSelect={showRevisions}>
                                <History />
                                {t('View revisions')}
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                            onSelect={() => {
                                shortcutsFocus.remember();
                                setShortcutsOpen(true);
                            }}
                        >
                            <Keyboard />
                            {t('Keyboard shortcuts')}
                        </DropdownMenuItem>
                        {entry && can.delete && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={moveToTrash}
                                >
                                    <Trash2 />
                                    {t('Move to trash')}
                                </DropdownMenuItem>
                            </>
                        )}
                    </>
                }
                sidebar={
                    <EditorSettingsTabs
                        tab={settingsTab}
                        onTabChange={setSettingsTab}
                        documentLabel={contentType.label}
                        document={
                            <>
                                {isPhone && (
                                    <Slot
                                        name="entry-toolbar"
                                        context={editorContext}
                                    />
                                )}
                                {sidebar.map((section) => (
                                    <Fragment key={section.key}>
                                        {section.node}
                                    </Fragment>
                                ))}
                            </>
                        }
                        block={
                            <BlockInspector
                                block={findBlock(blocks, selectedBlockId)}
                                onChange={(next) =>
                                    setBlocks(
                                        replaceBlock(blocks, next.id, next),
                                    )
                                }
                                editor={editor}
                            />
                        }
                    />
                }
            >
                <form
                    id={FORM_ID}
                    ref={formRef}
                    onSubmit={submit}
                    className="mx-auto grid w-full grid-cols-[minmax(0,1fr)] gap-8 px-[clamp(1rem,4cqi,4rem)] pt-8 pb-24 in-data-[content-width=reading]:max-w-[52rem] sm:pt-12"
                >
                    <div className="grid gap-2">
                        <textarea
                            id="title"
                            aria-label={t('Title')}
                            rows={1}
                            value={data.title}
                            onChange={(event) =>
                                setData(
                                    'title',
                                    event.target.value.replace(/\r?\n/g, ' '),
                                )
                            }
                            onKeyDown={keepTitleOnOneLine}
                            placeholder={t('Add title')}
                            required
                            aria-invalid={Boolean(errors.title)}
                            className="[field-sizing:content] w-full resize-none border-0 bg-transparent p-0 text-3xl leading-tight font-semibold tracking-tight text-balance text-foreground placeholder:text-zinc-400 focus:outline-none sm:text-4xl dark:placeholder:text-zinc-600"
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <BlockEditor
                            key={editorVersion}
                            value={blocks}
                            onChange={setBlocks}
                            pluginBlocks={editorBlocks}
                            editor={editor}
                            onSelectBlock={setSelectedBlockId}
                            patterns={blockPatterns}
                            onActiveEditorChange={setActiveEditor}
                        />
                        <InputError message={blockError} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="excerpt">{t('Excerpt')}</Label>
                        <Textarea
                            id="excerpt"
                            value={data.excerpt}
                            onChange={(event) =>
                                setData('excerpt', event.target.value)
                            }
                            placeholder={t(
                                'A short summary for lists and search results',
                            )}
                            rows={3}
                        />
                        <InputError message={errors.excerpt} />
                    </div>

                    <FieldGroupsPanel
                        groups={activeFieldGroups}
                        position="main"
                        values={fieldValues}
                        onChange={setFieldValues}
                        errors={errors}
                    />
                </form>
            </EditorLayout>
            <EditorShortcutsDialog
                open={shortcutsOpen}
                onOpenChange={setShortcutsOpen}
                onCloseAutoFocus={shortcutsFocus.restore}
            />
        </>
    );
}

// The label and status of the main button: Publish, Schedule, Update, or Save draft for people who cannot publish.
function primaryAction({
    canPublish,
    live,
    inFuture,
}: {
    canPublish: boolean;
    live: boolean;
    inFuture: boolean;
}): { label: string; status: EntryStatus | null } {
    if (!canPublish) {
        return { label: msg('Save draft'), status: 'draft' };
    }

    if (live) {
        return { label: msg('Update'), status: null };
    }

    return inFuture
        ? { label: msg('Schedule'), status: 'scheduled' }
        : { label: msg('Publish'), status: 'published' };
}

// Enter in the title does not add a new line, like a heading. Enter that confirms an IME word still works.
function keepTitleOnOneLine(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === 'Enter' && !event.nativeEvent.isComposing) {
        event.preventDefault();
    }
}
