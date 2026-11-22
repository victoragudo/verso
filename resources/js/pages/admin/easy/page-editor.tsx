import type { RequestPayload } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    RotateCcwClock,
    MoreVertical,
    Redo2,
    RotateCcw,
    Undo2,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useReducer,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import { toast } from 'sonner';
import { builder } from '@/builder';
import type { EditorManifest } from '@/builder';
import { setupBuilder } from '@/builder/setup';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { ConfirmDialog } from '@/components/verso/easy/confirm-dialog';
import { DraftBanner } from '@/components/verso/easy/draft-banner';
import { EditorFrame } from '@/components/verso/easy/editor-frame';
import { DEVICES, DeviceLabel } from '@/components/verso/easy/preview-devices';
import type { Device } from '@/components/verso/easy/preview-devices';
import { RestoreDialog } from '@/components/verso/easy/restore-dialog';
import { SectionGallery } from '@/components/verso/easy/section-gallery';
import { MediaPicker } from '@/components/verso/media/media-picker';
import { Slot } from '@/components/verso/slots';
import { StatusBadge } from '@/components/verso/ui';
import { coreSectionActions, FIX_COLORS_ACTION } from '@/lib/easy/frame-bridge';
import type { FrameFocus, FrameSection } from '@/lib/easy/frame-bridge';
import {
    duplicateSection,
    insertSections,
    isHidden,
    moveSection,
    removeSection,
    sectionName,
    setImage,
    setText,
    toggleHidden,
} from '@/lib/easy/page-blocks';
import type { ImageTarget, TextField } from '@/lib/easy/page-blocks';
import {
    clearDeviceDraft,
    draftTime,
    pickDraft,
    readDeviceDraft,
    writeDeviceDraft,
} from '@/lib/easy/page-drafts';
import type { PageEditorContext } from '@/lib/easy/page-editor-registry';
import {
    canRedo,
    canUndo,
    isDirty,
    pageEditorReducer,
    startPageEditor,
} from '@/lib/easy/page-history';
import {
    buildSection,
    discardDraft,
    drawPage,
    PageEditorError,
    saveDraft,
} from '@/lib/easy/page-requests';
import type { PageVersion, ServerDraft } from '@/lib/easy/page-requests';
import {
    firstValidationMessage,
    plainMessage,
    plainVisitErrors,
} from '@/lib/easy/plain-errors';
import {
    fixSectionColors,
    reviewSectionColors,
} from '@/lib/easy/section-colors';
import type { SiteColors } from '@/lib/easy/section-colors';
import type { SectionInfo } from '@/lib/easy/sections';
import { markFocus, returnFocus } from '@/lib/easy/return-focus';
import type { FocusMark } from '@/lib/easy/return-focus';
import { useAutosave } from '@/lib/easy/use-autosave';
import { msg, t } from '@/lib/i18n';
import {
    draft as draftRoute,
    frame as frameRoute,
    update,
    versions as versionsRoute,
} from '@/routes/admin/easy/pages';
import { discard as discardRoute } from '@/routes/admin/easy/pages/draft';
import { build as buildRoute } from '@/routes/admin/easy/sections';
import type { Block, MediaItem, SitePreviewWidths } from '@/types';

export type PageEditorEntry = {
    id: number;
    title: string;
    type: string;
    typeLabel: string;
    status: string;
    url: string | null;
    backUrl: string;
    advancedUrl: string;
    canPublish: boolean;
    isFrontPage?: boolean;
    updatedAt?: string | null;
};

type Props = {
    entry: PageEditorEntry;
    blocks: Block[];
    draft?: ServerDraft | null;
    sections: SectionInfo[];
    editor?: EditorManifest;
    previewWidths: SitePreviewWidths;
    renderDelay: number;
    undoSteps: number;
    undoSeconds: number;
    autosaveDelay?: number;
    // Color tokens of the current style, for the "Colors hard to read" warnings.
    colors?: SiteColors;
};

// A question the editor asks before an action that is hard to see coming.
type Ask =
    | { kind: 'publish-empty' }
    | { kind: 'start-over' }
    | { kind: 'delete-front'; sectionId: string }
    | null;

const DEFAULT_AUTOSAVE_DELAY = 2000;

const STATUS_LABELS: Record<string, string> = {
    published: msg('Published'),
    draft: msg('Draft'),
    scheduled: msg('Scheduled'),
};

const ICON_BUTTON = 'size-11 shrink-0 md:size-11';

// Buttons of the top bar keep 44px on every screen, for fingers on tablets.
const BAR_BUTTON = 'h-11 md:h-11';

const NO_COLORS: SiteColors = {};

// The items of a builder registry, read again when a plugin adds or removes one.
function useRegistryItems<T>(registry: {
    all: () => T[];
    subscribe: (listener: () => void) => () => void;
}): T[] {
    return useSyncExternalStore(registry.subscribe, registry.all, registry.all);
}

// What the page last drew, with the blocks it was drawn from.
type Drawn = { html: string; blocks: Block[] };

// Visual page editor: the real page in a frame, with click to edit text and pictures, and ready sections.
export default function PageEditor({
    entry,
    blocks: initialBlocks,
    draft = null,
    sections,
    editor,
    previewWidths,
    renderDelay,
    undoSteps,
    undoSeconds,
    autosaveDelay = DEFAULT_AUTOSAVE_DELAY,
    colors = NO_COLORS,
}: Props) {
    const reducer = useMemo(() => pageEditorReducer(undoSteps), [undoSteps]);
    const [state, dispatch] = useReducer(
        reducer,
        initialBlocks,
        startPageEditor,
    );
    const blocks = state.history.value;
    const dirty = isDirty(state);
    const [drawn, setDrawn] = useState<Drawn | null>(null);
    const [drawError, setDrawError] = useState<string | null>(null);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [saving, setSaving] = useState<'save' | 'publish' | null>(null);
    const [device, setDevice] = useState<Device>('desktop');
    const [announcement, setAnnouncement] = useState('');
    const [galleryIndex, setGalleryIndex] = useState<number | null>(null);
    const [busyKey, setBusyKey] = useState<string | null>(null);
    const [galleryError, setGalleryError] = useState<string | null>(null);
    const [imageTarget, setImageTarget] = useState<ImageTarget | null>(null);
    const [ask, setAsk] = useState<Ask>(null);
    const [restoring, setRestoring] = useState(false);
    const [found, setFound] = useState(() =>
        pickDraft(draft, readDeviceDraft(entry.id), {
            blocks: initialBlocks,
            updatedAt: entry.updatedAt ?? null,
        }),
    );
    const blocksRef = useRef(blocks);
    const focusRef = useRef<FrameFocus | null>(null);
    const firstDraw = useRef(true);
    const dialogFocus = useRef<FocusMark | null>(null);

    useState(() => setupBuilder(undefined, editor?.modules ?? []));

    useEffect(() => {
        blocksRef.current = blocks;
    }, [blocks]);

    const announce = useCallback((message: string) => {
        setAnnouncement('');
        window.setTimeout(() => setAnnouncement(message), 50);
    }, []);

    const change = useCallback(
        (next: Block[], message?: string, focus?: FrameFocus) => {
            if (next === blocksRef.current) {
                return;
            }

            focusRef.current = focus ?? null;
            blocksRef.current = next;
            dispatch({ type: 'change', blocks: next });

            if (message) {
                announce(message);
            }
        },
        [announce],
    );

    // Draws the page again a moment after the last change. Older requests are cancelled.
    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(
            () => {
                drawPage(frameRoute(entry.id).url, blocks, controller.signal)
                    .then((html) => {
                        setDrawn({ html, blocks });
                        setDrawError(null);
                    })
                    .catch((error: unknown) => {
                        if (error instanceof PageEditorError) {
                            setDrawError(error.message);
                        }
                    });
            },
            firstDraw.current ? 0 : renderDelay,
        );
        firstDraw.current = false;

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [blocks, entry.id, renderDelay]);

    const autosave = useAutosave({
        entryId: entry.id,
        blocks,
        dirty,
        delay: autosaveDelay,
        enabled: found === null,
        save: (next, signal) =>
            saveDraft(draftRoute(entry.id).url, next, signal),
        discard: () => discardDraft(discardRoute(entry.id).url),
    });

    const keepDraft = () => {
        if (found) {
            change(
                found.blocks,
                t(
                    'Your unsaved changes are back. Press Undo to go back to the saved page.',
                ),
            );
        }

        setFound(null);
    };

    const throwDraftAway = () => {
        clearDeviceDraft(entry.id);
        discardDraft(discardRoute(entry.id).url).catch(() => undefined);
        setFound(null);
        announce(t('Unsaved changes thrown away'));
    };

    const undoChange = useCallback(() => {
        if (canUndo(state)) {
            dispatch({ type: 'undo' });
            announce(t('Change undone'));
        }
    }, [state, announce]);

    const redoChange = useCallback(() => {
        if (canRedo(state)) {
            dispatch({ type: 'redo' });
            announce(t('Change done again'));
        }
    }, [state, announce]);

    const save = useCallback(
        (publish: boolean, confirmEmpty = false) => {
            const sent = blocksRef.current;

            if (publish && sent.length === 0 && !confirmEmpty) {
                setAsk({ kind: 'publish-empty' });

                return;
            }

            setSaving(publish ? 'publish' : 'save');
            setSaveError(null);
            const action = { retry: publish ? msg('Publish') : msg('Save') };
            const failed = (message: string) => {
                writeDeviceDraft(entry.id, sent);
                setSaveError(message);
                announce(message);
            };

            router.put(
                update(entry.id).url,
                {
                    blocks: sent,
                    publish,
                    confirmEmpty,
                } as unknown as RequestPayload,
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        dispatch({ type: 'saved', blocks: sent });
                        clearDeviceDraft(entry.id);
                        announce(
                            publish ? t('Page published') : t('Page saved'),
                        );
                    },
                    onError: (errors) =>
                        failed(
                            plainMessage(
                                'invalid',
                                action,
                                firstValidationMessage(errors, 'blocks'),
                            ),
                        ),
                    ...plainVisitErrors(failed, action),
                    onFinish: () => setSaving(null),
                },
            );
        },
        [entry.id, announce],
    );

    const startOver = () => {
        change(
            state.saved,
            t(
                'Back to the last saved version. Press Undo to get your changes back.',
            ),
        );
    };

    const restoreVersion = (version: PageVersion) => {
        const time = version.savedAt ? draftTime(version.savedAt) : '';
        change(
            version.blocks,
            time
                ? t(
                      'The version from {time} is back in the editor. Press Save to keep it, or Undo to go back.',
                      { time },
                  )
                : t(
                      'An earlier version is back in the editor. Press Save to keep it, or Undo to go back.',
                  ),
        );
        toast(
            time
                ? t(
                      'The version from {time} is in the editor. Press Save to keep it.',
                      { time },
                  )
                : t(
                      'An earlier version is in the editor. Press Save to keep it.',
                  ),
        );
    };

    // Undo, redo and save from the keyboard.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (
                !(event.metaKey || event.ctrlKey) ||
                target?.closest('input, textarea, [contenteditable="true"]')
            ) {
                return;
            }

            const key = event.key.toLowerCase();

            if (key === 'z') {
                event.preventDefault();

                if (event.shiftKey) {
                    redoChange();
                } else {
                    undoChange();
                }
            } else if (key === 'y') {
                event.preventDefault();
                redoChange();
            } else if (key === 's') {
                event.preventDefault();
                save(false);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [undoChange, redoChange, save]);

    // Warns before leaving with unsaved changes.
    useEffect(() => {
        if (!dirty) {
            return;
        }

        const onLeave = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', onLeave);

        return () => window.removeEventListener('beforeunload', onLeave);
    }, [dirty]);

    const context = useCallback(
        (): PageEditorContext => ({
            entry: { id: entry.id, title: entry.title, type: entry.type },
            blocks: blocksRef.current,
            change: (next, message) => change(next, message),
            announce,
        }),
        [entry.id, entry.title, entry.type, change, announce],
    );

    // Dialogs opened from a button in the frame give focus back to that button when they close.
    const openFromFrame = () => {
        dialogFocus.current = markFocus();
    };
    const focusBack = (event: Event) => {
        if (returnFocus(dialogFocus.current)) {
            event.preventDefault();
        }

        dialogFocus.current = null;
    };

    const restore = useCallback(
        (removed: Block, index: number) => {
            change(
                insertSections(blocksRef.current, index, [removed]),
                t('Section put back'),
                {
                    kind: 'section',
                    sectionId: removed.id,
                },
            );
        },
        [change],
    );

    // Deletes a section with an Undo button in a toast.
    const deleteSection = useCallback(
        (id: string) => {
            const {
                blocks: next,
                removed,
                index,
            } = removeSection(blocksRef.current, id);

            if (!removed) {
                return;
            }

            change(next, t('Section deleted'), { kind: 'add', index });
            toast(t('Section deleted.'), {
                duration: undoSeconds * 1000,
                action: {
                    label: t('Undo'),
                    onClick: () => restore(removed, index),
                },
            });
        },
        [change, restore, undoSeconds],
    );

    const onSectionAction = useCallback(
        (id: string, action: string) => {
            const current = blocksRef.current;

            switch (action) {
                case FIX_COLORS_ACTION: {
                    const warning = reviewSectionColors(current, colors).find(
                        (item) => item.sectionId === id,
                    );

                    if (warning) {
                        change(
                            fixSectionColors(current, warning, colors),
                            t(
                                'Colors fixed. The text in this section is easy to read now.',
                            ),
                            { kind: 'section', sectionId: id },
                        );
                    }

                    break;
                }
                case 'move-up':
                case 'move-down': {
                    const direction = action === 'move-up' ? 'up' : 'down';
                    change(
                        moveSection(current, id, direction),
                        direction === 'up'
                            ? t('Section moved up')
                            : t('Section moved down'),
                        { kind: 'action', sectionId: id, action },
                    );
                    break;
                }
                case 'duplicate':
                    change(
                        duplicateSection(current, id),
                        t('Section duplicated'),
                        { kind: 'action', sectionId: id, action },
                    );
                    break;
                case 'toggle-hidden': {
                    const hidden = current.some(
                        (block) => block.id === id && isHidden(block),
                    );
                    change(
                        toggleHidden(current, id),
                        hidden
                            ? t('Section shown on your site')
                            : t('Section hidden. Visitors will not see it.'),
                        { kind: 'action', sectionId: id, action },
                    );
                    break;
                }
                case 'delete':
                    if (entry.isFrontPage && current.length === 1) {
                        setAsk({ kind: 'delete-front', sectionId: id });
                    } else {
                        deleteSection(id);
                    }

                    break;
                default: {
                    const extra = builder.pageSectionActions.get(action);
                    const section = current.find((block) => block.id === id);

                    if (extra && section) {
                        extra.run({ ...context(), section });
                    }
                }
            }
        },
        [change, colors, context, deleteSection, entry.isFrontPage],
    );

    const onText = useCallback(
        (blockId: string, field: TextField, value: string) =>
            change(setText(blocksRef.current, blockId, field, value)),
        [change],
    );

    const pickSection = async (key: string) => {
        if (galleryIndex === null) {
            return;
        }

        setBusyKey(key);
        setGalleryError(null);

        try {
            const built = await buildSection(buildRoute(key).url);

            if (built.length > 0) {
                change(
                    insertSections(blocksRef.current, galleryIndex, built),
                    t('Section added'),
                    {
                        kind: 'section',
                        sectionId: built[0].id,
                    },
                );
            }

            setGalleryIndex(null);
        } catch (error) {
            setGalleryError(
                error instanceof Error
                    ? error.message
                    : t('This section could not be added.'),
            );
        } finally {
            setBusyKey(null);
        }
    };

    const pickImage = (media: MediaItem) => {
        if (imageTarget) {
            change(
                setImage(blocksRef.current, imageTarget, media),
                t('Picture replaced'),
                { kind: 'image', ...imageTarget },
            );
        }

        setImageTarget(null);
    };

    const extraActions = useRegistryItems(builder.pageSectionActions);
    const drawnBlocks = drawn?.blocks;
    const colorWarnings = useMemo(
        () => reviewSectionColors(drawnBlocks ?? [], colors),
        [drawnBlocks, colors],
    );
    const frameSections: FrameSection[] = (drawnBlocks ?? []).map((block) => ({
        id: block.id,
        name: sectionName(drawnBlocks ?? [], block.id),
        hidden: isHidden(block),
        warning: colorWarnings.find((item) => item.sectionId === block.id)
            ?.message,
        skip: extraActions
            .filter((action) => action.shows && !action.shows(block))
            .map((action) => action.name),
    }));
    const actions = [
        ...coreSectionActions(),
        ...extraActions.map(({ name, label }) => ({ name, label })),
    ];
    const buttons = useRegistryItems(builder.pageEditorButtons);
    const published = entry.status === 'published';
    const kind = entry.type === 'post' ? 'post' : 'page';
    const backLabel = published ? t('Back to site') : t('Back');
    const saveLabel = published ? t('Save changes') : t('Save draft');
    const saveState = saving
        ? saving === 'publish'
            ? t('Publishing…')
            : t('Saving…')
        : dirty
          ? autosave.label || t('Unsaved changes')
          : t('All changes saved');
    const spokenState =
        !saving && dirty && autosave.status !== 'saving' ? autosave.label : '';
    const width = previewWidths[device];

    return (
        <>
            <Head title={t('Edit {title}', { title: entry.title })} />
            <div
                className="flex h-dvh flex-col bg-background"
                data-test="page-editor"
            >
                <header className="flex shrink-0 flex-wrap items-center gap-2 border-b bg-card px-2 py-1.5 sm:px-3">
                    <Button
                        asChild
                        variant="ghost"
                        className={`${BAR_BUTTON} shrink-0 px-3`}
                    >
                        <a href={entry.backUrl} aria-label={backLabel}>
                            <ArrowLeft aria-hidden="true" />
                            <span
                                className="hidden sm:inline"
                                aria-hidden="true"
                            >
                                {backLabel}
                            </span>
                        </a>
                    </Button>

                    <div className="flex min-w-0 flex-1 items-center gap-2">
                        <h1 className="truncate text-sm font-semibold">
                            {entry.title}
                        </h1>
                        <StatusBadge tone={published ? 'success' : 'neutral'}>
                            {STATUS_LABELS[entry.status]
                                ? t(STATUS_LABELS[entry.status])
                                : entry.status}
                        </StatusBadge>
                        <span
                            className="hidden truncate text-xs text-muted-foreground sm:inline"
                            data-test="save-state"
                            aria-hidden="true"
                        >
                            {saveState}
                        </span>
                        <span
                            role="status"
                            className="sr-only"
                            data-test="autosave-status"
                        >
                            {spokenState}
                        </span>
                    </div>

                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={device}
                        onValueChange={(value) =>
                            value && setDevice(value as Device)
                        }
                        aria-label={t('Screen size')}
                        className="hidden lg:flex"
                    >
                        {DEVICES.map(({ value, label, Icon }) => (
                            <ToggleGroupItem
                                key={value}
                                value={value}
                                aria-label={t(label)}
                                title={t(label)}
                                className="h-11 px-3"
                            >
                                <Icon aria-hidden="true" />
                                <span className="hidden xl:inline">
                                    {t(label)}
                                </span>
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>

                    <div className="flex shrink-0 items-center gap-1.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className={ICON_BUTTON}
                            aria-label={t('Undo')}
                            title={t('Undo')}
                            aria-disabled={!canUndo(state)}
                            onClick={undoChange}
                        >
                            <Undo2 aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className={ICON_BUTTON}
                            aria-label={t('Redo')}
                            title={t('Redo')}
                            aria-disabled={!canRedo(state)}
                            onClick={redoChange}
                        >
                            <Redo2 aria-hidden="true" />
                        </Button>
                        {buttons.map((item) => {
                            const Icon = item.icon;

                            return (
                                <Button
                                    key={item.name}
                                    type="button"
                                    variant="outline"
                                    className={BAR_BUTTON}
                                    onClick={() => item.run(context())}
                                    aria-label={item.label}
                                    title={item.label}
                                    data-test={`page-button-${item.name}`}
                                >
                                    {Icon && <Icon aria-hidden="true" />}
                                    <span
                                        className={
                                            Icon
                                                ? 'hidden sm:inline'
                                                : undefined
                                        }
                                        aria-hidden="true"
                                    >
                                        {item.label}
                                    </span>
                                </Button>
                            );
                        })}
                        <Button
                            type="button"
                            variant="outline"
                            className={`${BAR_BUTTON} hidden sm:inline-flex`}
                            disabled={saving !== null}
                            onClick={() => save(false)}
                        >
                            {saveLabel}
                        </Button>
                        {entry.canPublish && !published && (
                            <Button
                                type="button"
                                className={BAR_BUTTON}
                                disabled={saving !== null}
                                onClick={() => save(true)}
                                data-test="publish-page"
                            >
                                {t('Publish')}
                            </Button>
                        )}
                        <DropdownMenu modal={false}>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className={ICON_BUTTON}
                                    aria-label={t('More actions')}
                                    title={t('More actions')}
                                >
                                    <MoreVertical aria-hidden="true" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-60">
                                <DropdownMenuItem
                                    className="min-h-11 sm:hidden"
                                    onSelect={() => save(false)}
                                >
                                    {saveLabel}
                                </DropdownMenuItem>
                                {DEVICES.map(({ value, Icon }) => (
                                    <DropdownMenuItem
                                        key={value}
                                        className="min-h-11 lg:hidden"
                                        onSelect={() => setDevice(value)}
                                    >
                                        <Icon aria-hidden="true" />
                                        {t(
                                            '{device, select, desktop {Show on a computer} tablet {Show on a tablet} other {Show on a phone}}',
                                            { device: value },
                                        )}
                                    </DropdownMenuItem>
                                ))}
                                <DropdownMenuItem
                                    className="min-h-11"
                                    onSelect={() => setRestoring(true)}
                                    data-test="open-restore"
                                >
                                    <RotateCcwClock aria-hidden="true" />
                                    {t('Restore this page')}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    className="min-h-11"
                                    disabled={!dirty}
                                    onSelect={() =>
                                        setAsk({ kind: 'start-over' })
                                    }
                                    data-test="start-over"
                                >
                                    <RotateCcw aria-hidden="true" />
                                    {t('Start over')}
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild className="min-h-11">
                                    <a href={entry.advancedUrl}>
                                        {t('Advanced editor')}
                                    </a>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>

                {found && (
                    <DraftBanner
                        savedAt={found.savedAt}
                        onKeep={keepDraft}
                        onDiscard={throwDraftAway}
                    />
                )}

                {(saveError || drawError || autosave.error) && (
                    <div
                        role="alert"
                        className="shrink-0 border-b bg-danger-soft px-4 py-2 text-sm text-danger-text"
                        data-test="editor-error"
                    >
                        {saveError ?? drawError ?? autosave.error}
                    </div>
                )}

                <main className="flex min-h-0 flex-1 flex-col items-center gap-2 overflow-hidden bg-muted/40 p-0 pt-2 md:p-4 md:pt-3">
                    <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                        <DeviceLabel device={device} width={width} />
                        {/* Phones have no room for the save state in the top bar, so it shows here. */}
                        <p
                            className="text-xs text-muted-foreground sm:hidden"
                            data-test="save-state-phone"
                            aria-hidden="true"
                        >
                            {saveState}
                        </p>
                    </div>
                    <EditorFrame
                        html={drawn?.html ?? null}
                        width={width}
                        title={t(
                            '{kind, select, post {Your post “{title}”, click a text or a picture to change it} other {Your page “{title}”, click a text or a picture to change it}}',
                            { kind, title: entry.title },
                        )}
                        takeFocus={() => {
                            const focus = focusRef.current;
                            focusRef.current = null;

                            return focus;
                        }}
                        bridge={{
                            sections: frameSections,
                            actions,
                            onText,
                            onImage: (target) => {
                                openFromFrame();
                                setImageTarget(target);
                            },
                            onSectionAction,
                            onAddSection: (index) => {
                                openFromFrame();
                                setGalleryError(null);
                                setGalleryIndex(index);
                            },
                        }}
                        onUndo={undoChange}
                        onRedo={redoChange}
                        onSave={() => save(false)}
                    />
                </main>

                <p role="status" className="sr-only" data-test="announcer">
                    {announcement}
                </p>
            </div>

            <SectionGallery
                open={galleryIndex !== null}
                onOpenChange={(open) =>
                    !open && busyKey === null && setGalleryIndex(null)
                }
                sections={sections}
                busyKey={busyKey}
                error={galleryError}
                onPick={pickSection}
                onCloseAutoFocus={focusBack}
            />
            <MediaPicker
                open={imageTarget !== null}
                onOpenChange={(open) => !open && setImageTarget(null)}
                onSelect={pickImage}
                onCloseAutoFocus={focusBack}
                accept="image"
            />
            <RestoreDialog
                open={restoring}
                versionsUrl={versionsRoute(entry.id).url}
                frameUrl={frameRoute(entry.id).url}
                onRestore={restoreVersion}
                onClose={() => setRestoring(false)}
            />
            <ConfirmDialog
                open={ask?.kind === 'publish-empty'}
                title={t(
                    '{kind, select, post {Publish an empty post?} other {Publish an empty page?}}',
                    { kind },
                )}
                confirmLabel={t('Publish anyway')}
                onConfirm={() => save(true, true)}
                onClose={() => setAsk(null)}
            >
                {t(
                    '{kind, select, post {This post has no sections yet, so visitors will see an empty page. You can add a section first.} other {This page has no sections yet, so visitors will see an empty page. You can add a section first.}}',
                    { kind },
                )}
            </ConfirmDialog>
            <ConfirmDialog
                open={ask?.kind === 'start-over'}
                title={t('Start over?')}
                confirmLabel={t('Start over')}
                danger
                onConfirm={startOver}
                onClose={() => setAsk(null)}
            >
                {t(
                    '{kind, select, post {This puts back the last saved version of the post. You can still press Undo to get your changes back.} other {This puts back the last saved version of the page. You can still press Undo to get your changes back.}}',
                    { kind },
                )}
            </ConfirmDialog>
            <ConfirmDialog
                open={ask?.kind === 'delete-front'}
                title={t('Empty your home page?')}
                confirmLabel={t('Delete the section')}
                danger
                onConfirm={() =>
                    ask?.kind === 'delete-front' && deleteSection(ask.sectionId)
                }
                onClose={() => setAsk(null)}
            >
                {t(
                    'This is the last section of your home page. Visitors see your home page first, so it would look empty until you add a new section. You can press Undo.',
                )}
            </ConfirmDialog>
            <Slot name="page-editor" context={{}} />
        </>
    );
}
