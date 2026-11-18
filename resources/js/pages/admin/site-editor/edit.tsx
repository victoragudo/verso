import type { RequestPayload } from '@inertiajs/core';
import type { EditorManifest } from '@/builder';
import { findBlock, replaceBlock } from '@/builder/tree';
import { BlockInspector } from '@/components/verso/editor/block-inspector';
import { Head, router } from '@inertiajs/react';
import { RotateCcw, Undo2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
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
import { EditorSettingsTabs } from '@/components/verso/content/editor-settings-tabs';
import type { EditorSettingsTab } from '@/components/verso/content/editor-settings-tabs';
import {
    PART_STATUS,
    partNameInSentence,
} from '@/components/verso/site/site-part-status';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import { StatusBadge } from '@/components/verso/ui';
import { BuilderWorkspace } from '@/components/verso/workspace/builder-workspace';
import { PreviewPane } from '@/components/verso/workspace/preview-pane';
import { msg, t } from '@/lib/i18n';
import { siteEditor } from '@/routes/admin';
import { destroy, publish, update } from '@/routes/admin/site-editor';
import type {
    Block,
    BlockPattern,
    PluginBlockDefinition,
    SitePartStatus,
    SitePartSummary,
    SitePreviewWidths,
} from '@/types';

type Props = {
    theme: { name: string; slug: string };
    part: SitePartSummary;
    blocks: Block[];
    siteBlocks: PluginBlockDefinition[];
    editor?: EditorManifest;
    patterns: BlockPattern[];
    previewUrl: string;
    // Set when visitors see another style of this part, so the editor can say so.
    shownWith: { inUse: string; thisStyle: string } | null;
    previewWidths: SitePreviewWidths;
    previewDesktopMinWidth?: number;
    autosaveDelay: number;
    undoSteps: number;
};

// What the top bar says about the draft.
type SaveState = 'idle' | 'saving' | 'saved' | 'error';

const SAVE_TEXT: Record<SaveState, string> = {
    idle: '',
    saving: msg('Saving draft…'),
    saved: msg('Draft saved'),
    error: msg('Could not save. Check the blocks.'),
};

// Full screen editor for one part (header, footer...) with a live preview of the home page.
export default function SiteEditorEdit({
    part,
    blocks: initialBlocks,
    siteBlocks,
    editor,
    patterns,
    previewUrl,
    shownWith,
    previewWidths,
    previewDesktopMinWidth,
    autosaveDelay,
    undoSteps,
}: Props) {
    const [blocks, setBlocks] = useState<Block[]>(initialBlocks);
    const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
    const [history, setHistory] = useState<Block[][]>([]);
    const [editorVersion, setEditorVersion] = useState(0);
    const [status, setStatus] = useState<SitePartStatus>(part.status);
    const [saveState, setSaveState] = useState<SaveState>('idle');
    const [frameVersion, setFrameVersion] = useState(0);
    const [settingsTab, setSettingsTab] =
        useState<EditorSettingsTab>('document');
    const [confirmRestore, setConfirmRestore] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const dirty = useRef(false);

    const saveDraft = useCallback(
        (next: Block[]) => {
            setSaveState('saving');
            router.put(
                update(part.key).url,
                { blocks: next } as unknown as RequestPayload,
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        dirty.current = false;
                        setSaveState('saved');
                        setStatus('unpublished');
                        setFrameVersion((version) => version + 1);
                    },
                    onError: () => setSaveState('error'),
                },
            );
        },
        [part.key],
    );

    // Saves a draft a moment after the last change, then refreshes the preview.
    useEffect(() => {
        if (!dirty.current) {
            return;
        }

        // Publish sends the blocks too, so a save that waits is skipped after it.
        const timer = window.setTimeout(() => {
            if (dirty.current) {
                saveDraft(blocks);
            }
        }, autosaveDelay);

        return () => window.clearTimeout(timer);
    }, [blocks, autosaveDelay, saveDraft]);

    const change = (next: Block[]) => {
        setHistory((past) => [...past, blocks].slice(-undoSteps));
        dirty.current = true;
        setBlocks(next);
    };

    const undo = () => {
        const previous = history.at(-1);

        if (!previous) {
            return;
        }

        setHistory((past) => past.slice(0, -1));
        dirty.current = true;
        setBlocks(previous);
        setEditorVersion((version) => version + 1);
    };

    const publishChanges = () => {
        // A late autosave would cancel this request, and the user would not see that it is live.
        dirty.current = false;
        setPublishing(true);
        router.post(
            publish(part.key).url,
            { blocks } as unknown as RequestPayload,
            {
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
            },
        );
    };

    const restoreOriginal = () => {
        setConfirmRestore(false);
        dirty.current = false;
        router.delete(destroy(part.key).url, {
            preserveScroll: true,
            onSuccess: (page) => {
                const fresh = page.props as unknown as Props;
                setBlocks(fresh.blocks);
                setHistory([]);
                setStatus(fresh.part.status);
                setSaveState('idle');
                setEditorVersion((version) => version + 1);
                setFrameVersion((version) => version + 1);
            },
        });
    };

    // A newly picked block opens the Block tab of the settings.
    const selectBlock = (blockId: string | null) => {
        if (blockId !== null && blockId !== selectedBlockId) {
            setSettingsTab('block');
        }

        setSelectedBlockId(blockId);
    };
    const partName = partNameInSentence(part.label);
    const frameSrc = `${previewUrl}${previewUrl.includes('?') ? '&' : '?'}v=${frameVersion}`;

    return (
        <>
            <Head title={t('Edit {part}', { part: partName })} />
            <BuilderWorkspace
                name="site-editor"
                backHref={siteEditor().url}
                backLabel={t('Back to the Site Editor')}
                status={
                    <>
                        <h1 className="truncate text-sm font-semibold">
                            {part.label}
                        </h1>
                        <StatusBadge tone={PART_STATUS[status].tone}>
                            {t(PART_STATUS[status].label)}
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
                            onClick={() => saveDraft(blocks)}
                        >
                            {t('Save draft')}
                        </Button>
                        <Button
                            type="button"
                            disabled={publishing}
                            onClick={publishChanges}
                            data-test="publish-part"
                        >
                            {t('Publish changes')}
                        </Button>
                    </>
                }
                menu={
                    <>
                        <DropdownMenuItem
                            onSelect={() => saveDraft(blocks)}
                            className="sm:hidden"
                        >
                            {t('Save draft')}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={status === 'original'}
                            onSelect={() => setConfirmRestore(true)}
                        >
                            <RotateCcw aria-hidden="true" />
                            {t('Restore original')}
                        </DropdownMenuItem>
                    </>
                }
                settingsDescription={t(
                    'How this part is saved and the settings of the selected block.',
                )}
                settings={
                    <EditorSettingsTabs
                        tab={settingsTab}
                        onTabChange={setSettingsTab}
                        documentLabel={part.label}
                        document={
                            <div className="grid gap-3 text-sm text-muted-foreground">
                                <h2 className="text-base font-semibold text-foreground">
                                    {t('How this works')}
                                </h2>
                                <p>
                                    {t(
                                        'Changes are saved as a draft while you work. Only you see them in the preview.',
                                    )}
                                </p>
                                <p>
                                    {t(
                                        '"Publish changes" shows them to every visitor. "Restore original" brings back the theme\'s own {part}.',
                                        { part: partName },
                                    )}
                                </p>
                                {part.revisions > 0 && (
                                    <p>
                                        {t(
                                            '{count, plural, one {Published # time.} other {Published # times.}}',
                                            { count: part.revisions },
                                        )}
                                    </p>
                                )}
                            </div>
                        }
                        block={
                            <BlockInspector
                                block={findBlock(blocks, selectedBlockId)}
                                onChange={(next) =>
                                    change(replaceBlock(blocks, next.id, next))
                                }
                                editor={editor}
                            />
                        }
                    />
                }
                tip={{
                    id: 'site-part',
                    text: t(
                        'Add, move and change blocks. The preview shows the result on your home page.',
                    ),
                }}
                canvasLabel={t('Blocks of the {part}', { part: partName })}
                canvas={
                    <>
                        {shownWith && (
                            <p
                                role="note"
                                className="mb-3 rounded-md bg-warning-soft p-3 text-sm text-warning-text"
                            >
                                {t(
                                    'Visitors see the {inUse} style now. Pick {thisStyle} in Header and footer style to show this one.',
                                    {
                                        inUse: shownWith.inUse,
                                        thisStyle: shownWith.thisStyle,
                                    },
                                )}
                            </p>
                        )}
                        <BlockEditor
                            key={editorVersion}
                            value={blocks}
                            onChange={change}
                            pluginBlocks={siteBlocks}
                            editor={editor}
                            onSelectBlock={selectBlock}
                            history={false}
                            patterns={patterns}
                        />
                    </>
                }
                preview={
                    <PreviewPane
                        src={frameSrc}
                        title={t('Preview of the {part} on your home page', {
                            part: partName,
                        })}
                        widths={previewWidths}
                        desktopMinWidth={previewDesktopMinWidth}
                        testId="site-preview"
                    />
                }
            />

            <Dialog open={confirmRestore} onOpenChange={setConfirmRestore}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Restore the original?')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                "Your changes to the {part} are removed and visitors see the theme's own version again. This cannot be undone.",
                                { part: partName },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmRestore(false)}
                        >
                            {t('Keep my changes')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={restoreOriginal}
                        >
                            {t('Restore original')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
