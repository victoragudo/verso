import type { RequestPayload } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import { Power, RotateCcw, Trash, Undo2 } from 'lucide-react';
import { useCallback, useEffect, useId, useRef, useState } from 'react';
import type { EditorManifest } from '@/builder';
import { findBlock, replaceBlock } from '@/builder/tree';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { EditorSettingsTabs } from '@/components/verso/content/editor-settings-tabs';
import type { EditorSettingsTab } from '@/components/verso/content/editor-settings-tabs';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import { BlockInspector } from '@/components/verso/editor/block-inspector';
import { ConditionsEditor } from '@/components/verso/layouts/conditions-editor';
import { LayoutSettingsFields } from '@/components/verso/layouts/layout-settings';
import type {
    LayoutConditionDefinition,
    LayoutRule,
    LayoutSettingsValue,
    LayoutSummary,
} from '@/components/verso/layouts/types';
import { SiteDate, StatusBadge } from '@/components/verso/ui';
import { BuilderWorkspace } from '@/components/verso/workspace/builder-workspace';
import { PreviewPane } from '@/components/verso/workspace/preview-pane';
import {
    previewTargetFor,
    targetUsesLayout,
} from '@/components/verso/workspace/preview-target';
import type { PreviewTarget } from '@/components/verso/workspace/preview-target';
import { msg, t } from '@/lib/i18n';
import {
    destroy,
    index,
    publish,
    restore,
    switchMethod,
    update,
} from '@/routes/admin/theme-builder';
import type {
    Block,
    BlockPattern,
    PluginBlockDefinition,
    SitePreviewWidths,
} from '@/types';

type Props = {
    layout: LayoutSummary & { blocks: Block[]; settings: LayoutSettingsValue };
    siteBlocks: PluginBlockDefinition[];
    patterns: BlockPattern[];
    editor?: EditorManifest;
    conditions: LayoutConditionDefinition[];
    previewTargets: PreviewTarget[];
    previewUrl: string;
    previewWidths: SitePreviewWidths;
    previewDesktopMinWidth?: number;
    autosaveDelay: number;
    revisions: {
        id: number;
        createdAt: string | null;
        author: string | null;
    }[];
};

type SaveState = 'idle' | 'saving' | 'saved' | 'error';

const SAVE_TEXT: Record<SaveState, string> = {
    idle: '',
    saving: msg('Saving draft…'),
    saved: msg('Draft saved'),
    error: msg('Could not save. Check the blocks and the rules.'),
};

const STATUS: Record<
    LayoutSummary['status'],
    { label: string; tone: 'success' | 'warning' | 'neutral' }
> = {
    published: { label: msg('Live'), tone: 'success' },
    draft: { label: msg('Unpublished changes'), tone: 'warning' },
    off: { label: msg('Off'), tone: 'neutral' },
};

type Draft = {
    name: string;
    priority: number;
    blocks: Block[];
    conditions: LayoutRule[];
    settings: LayoutSettingsValue;
};

// Full screen editor of one theme builder layout: blocks, where it shows, its options and a live preview.
export default function ThemeBuilderEdit({
    layout,
    siteBlocks,
    patterns,
    editor,
    conditions,
    previewTargets,
    previewUrl,
    previewWidths,
    previewDesktopMinWidth,
    autosaveDelay,
    revisions,
}: Props) {
    const id = useId();
    const [draft, setDraft] = useState<Draft>({
        name: layout.name,
        priority: layout.priority,
        blocks: layout.blocks,
        conditions: layout.conditions,
        settings: layout.settings,
    });
    const [history, setHistory] = useState<Draft[]>([]);
    const [editorVersion, setEditorVersion] = useState(0);
    const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
    const [status, setStatus] = useState(layout.status);
    const [saveState, setSaveState] = useState<SaveState>('idle');
    // The page people picked in "Preview on". Until they pick one, the preview follows the rules of the layout.
    const [pickedTarget, setPickedTarget] = useState<string | null>(null);
    const [frameVersion, setFrameVersion] = useState(0);
    const [settingsTab, setSettingsTab] =
        useState<EditorSettingsTab>('document');
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const dirty = useRef(false);

    const payload = (next: Draft) => next as unknown as RequestPayload;

    const saveDraft = useCallback(
        (next: Draft) => {
            setSaveState('saving');
            router.put(update(layout.id).url, payload(next), {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    dirty.current = false;
                    setSaveState('saved');
                    setStatus((current) =>
                        current === 'off' ? 'off' : 'draft',
                    );
                    setFrameVersion((version) => version + 1);
                },
                onError: () => setSaveState('error'),
            });
        },
        [layout.id],
    );

    // Saves a draft a moment after the last change, then refreshes the preview.
    useEffect(() => {
        if (!dirty.current) {
            return;
        }

        // Publish sends the draft too, so a save that waits is skipped after it.
        const timer = window.setTimeout(() => {
            if (dirty.current) {
                saveDraft(draft);
            }
        }, autosaveDelay);

        return () => window.clearTimeout(timer);
    }, [draft, autosaveDelay, saveDraft]);

    const change = (next: Partial<Draft>) => {
        setHistory((past) => [...past, draft].slice(-100));
        dirty.current = true;
        setDraft({ ...draft, ...next });
    };

    const undo = () => {
        const previous = history.at(-1);

        if (!previous) {
            return;
        }

        setHistory((past) => past.slice(0, -1));
        dirty.current = true;
        setDraft(previous);
        setEditorVersion((version) => version + 1);
    };

    const publishChanges = () => {
        // A late autosave would cancel this request, and the user would not see that it is live.
        dirty.current = false;
        setPublishing(true);
        router.post(publish(layout.id).url, payload(draft), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setStatus('published');
                setSaveState('idle');
                setFrameVersion((version) => version + 1);
            },
            onError: () => {
                dirty.current = true;
                setSaveState('error');
            },
            onFinish: () => setPublishing(false),
        });
    };

    const switchLayout = (on: boolean) =>
        router.put(
            switchMethod(layout.id).url,
            { on },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => setStatus(on ? 'published' : 'off'),
            },
        );

    const restoreRevision = (revision: number) =>
        router.post(
            restore({ layout: layout.id, revision }).url,
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const fresh = (page.props as unknown as Props).layout;
                    setDraft({
                        name: fresh.name,
                        priority: fresh.priority,
                        blocks: fresh.blocks,
                        conditions: fresh.conditions,
                        settings: fresh.settings,
                    });
                    setHistory((past) => [...past, draft]);
                    setStatus(fresh.status);
                    setEditorVersion((version) => version + 1);
                    setFrameVersion((version) => version + 1);
                },
            },
        );

    const target = previewTargetFor(previewTargets, pickedTarget);
    const usesLayout = targetUsesLayout(previewTargets, target);
    const suggestion =
        previewTargets.find((item) => item.usesLayout === true) ?? null;
    const frameSrc = `${previewUrl}?target=${encodeURIComponent(target)}&v=${frameVersion}`;
    // A newly picked block opens the Block tab of the settings.
    const selectBlock = (blockId: string | null) => {
        if (blockId !== null && blockId !== selectedBlockId) {
            setSettingsTab('block');
        }

        setSelectedBlockId(blockId);
    };

    return (
        <>
            <Head title={t('Edit {name}', { name: draft.name })} />
            <BuilderWorkspace
                name="theme-builder"
                backHref={index().url}
                backLabel={t('Back to the theme builder')}
                status={
                    <>
                        <h1 className="truncate text-sm font-semibold">
                            {draft.name}
                        </h1>
                        <span className="hidden text-xs text-muted-foreground sm:inline">
                            {layout.kindLabel}
                        </span>
                        <StatusBadge tone={STATUS[status].tone}>
                            {t(STATUS[status].label)}
                        </StatusBadge>
                        <span
                            role="status"
                            className="hidden truncate text-xs text-muted-foreground md:inline"
                        >
                            {SAVE_TEXT[saveState] && t(SAVE_TEXT[saveState])}
                        </span>
                    </>
                }
                actions={
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-10"
                            aria-label={t('Undo')}
                            title={t('Undo')}
                            disabled={history.length === 0}
                            onClick={undo}
                        >
                            <Undo2 aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="hidden sm:inline-flex"
                            disabled={saveState === 'saving'}
                            onClick={() => saveDraft(draft)}
                        >
                            {t('Save draft')}
                        </Button>
                        <Button
                            type="button"
                            disabled={publishing}
                            onClick={publishChanges}
                            data-test="publish-layout"
                        >
                            {t('Publish')}
                        </Button>
                    </>
                }
                menu={
                    <>
                        <DropdownMenuItem
                            onSelect={() => saveDraft(draft)}
                            className="sm:hidden"
                        >
                            {t('Save draft')}
                        </DropdownMenuItem>
                        {status !== 'off' ? (
                            <DropdownMenuItem
                                onSelect={() => switchLayout(false)}
                            >
                                <Power aria-hidden="true" />
                                {t('Turn off')}
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                onSelect={() => switchLayout(true)}
                            >
                                <Power aria-hidden="true" />
                                {t('Turn on')}
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setConfirmDelete(true)}
                        >
                            <Trash aria-hidden="true" />
                            {t('Delete')}
                        </DropdownMenuItem>
                    </>
                }
                settingsDescription={t(
                    'Name, priority, where it shows, options and the selected block.',
                )}
                settings={
                    <EditorSettingsTabs
                        tab={settingsTab}
                        onTabChange={setSettingsTab}
                        documentLabel={t('Layout')}
                        document={
                            <div className="grid gap-5 text-sm">
                                <section
                                    className="grid gap-3"
                                    aria-labelledby={`${id}-about`}
                                >
                                    <h2
                                        id={`${id}-about`}
                                        className="text-base font-semibold"
                                    >
                                        {t('Name and priority')}
                                    </h2>
                                    <div className="grid gap-1">
                                        <Label
                                            htmlFor={`${id}-name`}
                                            className="text-xs"
                                        >
                                            {t('Name')}
                                        </Label>
                                        <Input
                                            id={`${id}-name`}
                                            value={draft.name}
                                            maxLength={120}
                                            onChange={(event) =>
                                                change({
                                                    name: event.target.value,
                                                })
                                            }
                                            className="h-9"
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label
                                            htmlFor={`${id}-priority`}
                                            className="text-xs"
                                        >
                                            {t('Priority')}
                                        </Label>
                                        <Input
                                            id={`${id}-priority`}
                                            type="number"
                                            min={-100}
                                            max={100}
                                            value={draft.priority}
                                            onChange={(event) =>
                                                change({
                                                    priority:
                                                        Number(
                                                            event.target.value,
                                                        ) || 0,
                                                })
                                            }
                                            className="h-9 w-24"
                                            aria-describedby={`${id}-priority-help`}
                                        />
                                        <p
                                            id={`${id}-priority-help`}
                                            className="text-xs text-muted-foreground"
                                        >
                                            {t(
                                                'When two layouts fit a page, the higher priority wins, then the more exact rule.',
                                            )}
                                        </p>
                                    </div>
                                </section>
                                <section
                                    className="grid gap-3"
                                    aria-labelledby={`${id}-where`}
                                >
                                    <h2
                                        id={`${id}-where`}
                                        className="text-base font-semibold"
                                    >
                                        {t('Where it shows')}
                                    </h2>
                                    <ConditionsEditor
                                        rules={draft.conditions}
                                        definitions={conditions}
                                        onChange={(rules) =>
                                            change({ conditions: rules })
                                        }
                                    />
                                </section>
                                <section
                                    className="grid gap-3"
                                    aria-labelledby={`${id}-options`}
                                >
                                    <h2
                                        id={`${id}-options`}
                                        className="text-base font-semibold"
                                    >
                                        {t('Options')}
                                    </h2>
                                    <LayoutSettingsFields
                                        kind={layout.kind}
                                        value={draft.settings}
                                        onChange={(settings) =>
                                            change({ settings })
                                        }
                                    />
                                </section>
                                {revisions.length > 0 && (
                                    <section
                                        className="grid gap-2"
                                        aria-labelledby={`${id}-versions`}
                                    >
                                        <h2
                                            id={`${id}-versions`}
                                            className="text-base font-semibold"
                                        >
                                            {t('Published versions')}
                                        </h2>
                                        <ul className="grid gap-2">
                                            {revisions.map((revision) => (
                                                <li
                                                    key={revision.id}
                                                    className="flex items-center justify-between gap-2"
                                                >
                                                    <span className="text-xs text-muted-foreground">
                                                        <SiteDate
                                                            iso={
                                                                revision.createdAt
                                                            }
                                                        />
                                                        {revision.author
                                                            ? ` · ${revision.author}`
                                                            : ''}
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            restoreRevision(
                                                                revision.id,
                                                            )
                                                        }
                                                    >
                                                        <RotateCcw />
                                                        {t('Use this version')}
                                                    </Button>
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                )}
                            </div>
                        }
                        block={
                            <BlockInspector
                                block={findBlock(draft.blocks, selectedBlockId)}
                                onChange={(next) =>
                                    change({
                                        blocks: replaceBlock(
                                            draft.blocks,
                                            next.id,
                                            next,
                                        ),
                                    })
                                }
                                editor={editor}
                            />
                        }
                    />
                }
                tip={{
                    id: `layout-${layout.kind === 'template' ? 'template' : 'part'}`,
                    text:
                        layout.kind === 'template'
                            ? t(
                                  'Use the Page data blocks (title, content, date, query loop) to show the entry or the list of the page.',
                              )
                            : t(
                                  'Add, move and change blocks. The preview shows the result on a real page.',
                              ),
                }}
                canvasLabel={t('Blocks of {name}', { name: draft.name })}
                canvas={
                    <BlockEditor
                        key={editorVersion}
                        value={draft.blocks}
                        onChange={(blocks) => change({ blocks })}
                        pluginBlocks={siteBlocks}
                        editor={editor}
                        onSelectBlock={selectBlock}
                        history={false}
                        patterns={patterns}
                    />
                }
                preview={
                    <PreviewPane
                        src={frameSrc}
                        title={t('Preview of {name}', { name: draft.name })}
                        widths={previewWidths}
                        desktopMinWidth={previewDesktopMinWidth}
                        testId="layout-preview"
                        target={{
                            targets: previewTargets,
                            value: target,
                            onChange: setPickedTarget,
                            usesLayout,
                            suggestion,
                        }}
                    />
                }
            />

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('Delete {name}?', { name: draft.name })}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'Visitors stop seeing it right away. This cannot be undone. To keep it for later, turn it off instead.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmDelete(false)}
                        >
                            {t('Keep it')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                router.delete(destroy(layout.id).url)
                            }
                        >
                            {t('Delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
