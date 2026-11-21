import type { Editor } from '@tiptap/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    ClipboardCopy,
    ClipboardPaste,
    Copy,
    GripVertical,
    ListTree,
    type LucideIcon,
    LibraryBig,
    MoreHorizontal,
    ZoomOut,
    Plus,
    Redo2,
    Trash2,
    Undo2,
} from 'lucide-react';
import {
    createContext,
    type DragEvent,
    useContext,
    useId,
    useMemo,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import { useRovingToolbar } from '@/hooks/use-roving-toolbar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    builder,
    canDrop,
    editorBlockTypes,
    featureEnabled,
    matchesShortcut,
} from '@/builder';
import type { EditorManifest } from '@/builder';
import { DRAG_MIME, dragState, edgeFromPointer } from '@/builder/dnd';
import type { DropSpot } from '@/builder/dnd';
import { prepareBuilder } from '@/builder/setup';
import {
    BLOCKS_MIME,
    blocksFromClipboard,
    blocksToClipboard,
    inTextField,
} from '@/builder/clipboard';
import { recordChange, redo, startHistory, undo } from '@/builder/history';
import type { History } from '@/builder/history';
import {
    canvasClass,
    canvasStyleSheet,
    CANVAS_CHILDREN_SUFFIX,
    hasCanvasStyles,
    styleOptionsFrom,
} from '@/builder/canvas-styles';
import { useEditorDevice } from '@/builder/device';
import { library, libraryDialog, syncedBlockFor } from '@/builder/library';
import { stylePresets } from '@/builder/presets';
import {
    applyPick,
    cleanSelection,
    duplicateSelection,
    EMPTY_SELECTION,
    extendSelection,
    groupSelection,
    moveSelection,
    pickMode,
    removeSelection,
    selectedBlocks,
} from '@/builder/selection';
import type { BlockSelection, PickMode } from '@/builder/selection';
import {
    findBlock,
    insertBlocks,
    locateBlock,
    parentType,
} from '@/builder/tree';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Block, BlockPattern, PluginBlockDefinition } from '@/types';
import { ShortcodeDefinitionsContext } from './shortcode-chip';
import {
    BLOCK_TYPES,
    type BlockTypeDefinition,
    cloneBlock,
    createColumn,
    findBlockType,
    insertBlockAfter,
    moveItem,
    newBlockId,
    plainText,
    searchBlockTypes,
    TEXT_BLOCK_TYPES,
} from './block-types';
import { InsertToolbar } from './insert-toolbar';
import { PluginBlockFields } from './blocks/plugin-block';
import type { BlockFieldProps } from './blocks/shared';
import { AddPanel } from './add-panel';
import { CodeMode, finishCode, startCode } from './code-mode';
import type { CodeModeState } from './code-mode';
import { OutlineView } from './outline-view';
import { PatternPicker } from './pattern-picker';
import {
    blockLabel,
    MOVE_LABELS,
    TreeContext,
    useBuilderTree,
    useTree,
} from './tree-context';
import { insertBlocksAfter, patternToBlocks } from './patterns';
import { MarkToolbar } from './mark-toolbar';
import {
    EditorPanelDefaultContext,
    EditorSidePanel,
} from './editor-side-panel';
import { OverflowToolbar } from './overflow-toolbar';
import type { ToolbarGroup } from './overflow-toolbar';
import { BlockBreadcrumb } from './block-breadcrumb';
import { SaveToLibraryDialog } from './save-to-library';
import { SelectionBar } from './selection-bar';
import type { SelectionActions } from './selection-bar';
import { SlashMenu } from './slash-menu';
// Block editor for entry content. Text blocks use Tiptap, other blocks use simple fields.
export type BlockEditorProps = {
    value: Block[];
    onChange: (blocks: Block[]) => void;
    pluginBlocks?: PluginBlockDefinition[];
    // Block patterns of the active theme, listed by the "Patterns" button.
    patterns?: BlockPattern[];
    // Called when another text block gets focus, so tools outside can read its selection.
    onActiveEditorChange?: (editor: Editor) => void;
    // Builder settings from the server: allowed blocks, features, style options.
    editor?: EditorManifest;
    // Called with the id of the block people click or focus, at any depth.
    onSelectBlock?: (id: string) => void;
    // Keeps an undo history of the whole document. Off where the page has its own.
    history?: boolean;
};

// Undo settings when the server sends none.
const DEFAULT_UNDO_STEPS = 100;
const DEFAULT_UNDO_MERGE_MS = 800;

type NestedBlockEditorProps = {
    value: Block[];
    onChange: (blocks: Block[]) => void;
    types: BlockTypeDefinition[];
};

// Most columns a columns block can have.
const MAX_COLUMNS = 4;

// Longest custom CSS of one block when the server sends no limit (same default as verso.editor.custom_css_max).
const DEFAULT_CUSTOM_CSS_MAX = 2000;

// Block that "Group in a section" puts picked blocks into.
const GROUP_BLOCK_TYPE = 'section';

// Toolbar button texts hide in a narrow canvas; the buttons keep their names for screen readers.
const TOOLBAR_LABEL = '@max-xl/canvas:sr-only';

// Text blocks fill the canvas in "Full width" and keep a comfortable line length in "Reading width".
const TEXT_MEASURE = 'in-data-[content-width=reading]:max-w-[80ch]';

// Blocks picked together, shared with every nested list.
type SelectionTools = {
    ids: string[];
    pick: (id: string, mode: PickMode) => void;
    extend: (id: string, direction: 'up' | 'down') => void;
};

const SelectionContext = createContext<SelectionTools | null>(null);

// Lets nested lists report the block people work on, for the Block tab.
const SelectBlockContext = createContext<((id: string) => void) | null>(null);

// Lets nested editors tell the root editor which text block has focus.
const ActiveEditorContext = createContext<((editor: Editor) => void) | null>(
    null,
);

// The slash command typed in a paragraph, like "/ima", or null.
function slashQuery(block: Block): string | null {
    if (block.type !== 'paragraph') {
        return null;
    }

    const text = plainText(block.content);

    return /^\/\S*$/.test(text) ? text.slice(1) : null;
}

// One shared empty list, so the text editors keep the same shortcode extensions.
const NO_PLUGIN_BLOCKS: PluginBlockDefinition[] = [];

export function BlockEditor({
    value,
    onChange,
    pluginBlocks = NO_PLUGIN_BLOCKS,
    patterns = [],
    onActiveEditorChange,
    editor,
    onSelectBlock,
    history = true,
}: BlockEditorProps) {
    const [activeEditor, setActiveEditor] = useState<Editor | null>(null);
    const [cursorBlockId, setCursorBlockId] = useState<string | null>(null);
    // Zoomed out view: the whole page at half size, to see and move big parts.
    const [zoomedOut, setZoomedOut] = useState(false);
    const [focusRequestId, setFocusRequestId] = useState<string | null>(null);
    const toolbarRef = useRef<HTMLDivElement>(null);
    useRovingToolbar(toolbarRef);
    const reportEditor = (editor: Editor) => {
        setActiveEditor(editor);
        onActiveEditorChange?.(editor);
    };
    prepareBuilder(editor, pluginBlocks);
    const types = editorBlockTypes(editor);
    const tree = useBuilderTree(value, onChange);
    const [log, setLog] = useState(() => startHistory(value));
    const historyOptions = {
        limit: editor?.undoSteps ?? DEFAULT_UNDO_STEPS,
        mergeMs: editor?.undoMergeMs ?? DEFAULT_UNDO_MERGE_MS,
    };

    // Every new value, from the canvas or from the sidebar, becomes an undo step.
    if (history && log.value !== value) {
        setLog(recordChange(log, value, performance.now(), historyOptions));
    }

    const travel = (step: (current: History) => History, label: string) => {
        const next = step(log);

        if (next !== log) {
            setLog(next);
            onChange(next.value);
            tree.announce(label);
        }
    };
    const undoChange = () => travel(undo, t('Change undone.'));
    const redoChange = () => travel(redo, t('Change redone.'));

    // Copies the selected block as JSON, so it can be pasted in another page or tab.
    const copySelected = (data: DataTransfer | null): boolean => {
        const picked =
            selection.ids.length > 1
                ? selectedBlocks(value, selection.ids)
                : [];
        const block = findBlock(value, cursorBlockId);
        const copied = picked.length > 0 ? picked : block ? [block] : [];

        if (copied.length === 0) {
            return false;
        }

        const json = blocksToClipboard(copied);

        if (data) {
            data.setData(BLOCKS_MIME, json);
            data.setData('text/plain', json);
        } else {
            void navigator.clipboard?.writeText(json);
        }

        tree.announce(
            copied.length > 1
                ? t(
                      '{count, plural, one {# block copied.} other {# blocks copied.}}',
                      {
                          count: copied.length,
                      },
                  )
                : t('{block} block copied.', {
                      block: blockLabel(copied[0].type),
                  }),
        );

        return true;
    };

    const pasteText = (text: string): boolean => {
        const pasted = blocksFromClipboard(text);

        if (!pasted || pasted.length === 0) {
            return false;
        }

        insertFromPanel(pasted);
        tree.announce(
            t('{count, plural, one {Block pasted.} other {# blocks pasted.}}', {
                count: pasted.length,
            }),
        );

        return true;
    };
    const defaultPanel = useContext(EditorPanelDefaultContext);
    const [panel, setPanel] = useState<'add' | 'outline' | null>(
        () => defaultPanel,
    );
    const [code, setCode] = useState<CodeModeState | null>(null);

    // Visual to Code writes the blocks as code; Code to Visual reads them back or shows the line with a problem.
    const switchMode = async (mode: 'visual' | 'code') => {
        if (mode === 'code') {
            setPanel(null);
            setCode(startCode(value));

            return;
        }

        if (!code || code.busy) {
            return;
        }

        setCode({ ...code, busy: true });
        const result = await finishCode(code);

        if ('error' in result) {
            setCode({ ...code, busy: false, error: result.error });

            return;
        }

        onChange(result.blocks);
        setCode(null);
    };
    const panelId = useId();

    // Panel items go after the selected block when they may go there, otherwise at the end of the page.
    const insertFromPanel = (added: Block[]) => {
        const place = cursorBlockId ? locateBlock(value, cursorBlockId) : null;
        const payload = {
            kind: 'insert',
            key: 'panel',
            blocks: added,
        } as const;
        const next =
            place && tree.accepts(payload, place.parentId)
                ? insertBlocks(value, place.parentId, place.index + 1, added)
                : insertBlocks(value, null, value.length, added);
        onChange(next);

        if (added[0]) {
            setCursorBlockId(added[0].id);
            onSelectBlock?.(added[0].id);
        }
    };

    const treeWithClipboard = {
        ...tree,
        copyBlock(id: string) {
            const block = findBlock(value, id);

            if (block) {
                void navigator.clipboard
                    ?.writeText(blocksToClipboard([block]))
                    .then(() => tree.announce(t('Block copied.')));
            }
        },
        pasteAfter(id: string) {
            setCursorBlockId(id);
            void navigator.clipboard?.readText().then((text) => {
                const place = locateBlock(value, id);
                const pasted = blocksFromClipboard(text);

                if (!place || !pasted) {
                    tree.announce(t('There are no copied blocks to paste.'));

                    return;
                }

                onChange(
                    insertBlocks(
                        value,
                        place.parentId,
                        place.index + 1,
                        pasted,
                    ),
                );
                tree.announce(t('Blocks pasted.'));
            });
        },
    };

    // Runs the registered keyboard shortcuts, like Alt+Shift+ArrowUp to move a block.
    const runShortcut = (event: React.KeyboardEvent) => {
        const shortcut = builder.shortcuts
            .all()
            .find(
                (item) =>
                    (!item.feature || featureEnabled(editor, item.feature)) &&
                    matchesShortcut(event, item.keys),
            );

        if (
            shortcut &&
            !event.defaultPrevented &&
            (shortcut.inFields || !inTextField(event.target))
        ) {
            event.preventDefault();
            shortcut.run({
                blocks: value,
                selectedId: cursorBlockId,
                setBlocks: onChange,
                move: tree.move,
                undo: undoChange,
                redo: redoChange,
            });
        }
    };

    // Header buttons add the block under the block that has the cursor.
    const insertFromToolbar = (definition: BlockTypeDefinition) => {
        const block = definition.create();
        onChange(insertBlockAfter(value, cursorBlockId, block));
        setCursorBlockId(block.id);
        setFocusRequestId(
            TEXT_BLOCK_TYPES.includes(block.type) ? block.id : null,
        );
    };

    // A pattern adds its blocks, with new ids, under the block that has the cursor.
    const insertPattern = (pattern: BlockPattern) => {
        const added = patternToBlocks(pattern);

        if (added.length === 0) {
            return;
        }

        onChange(insertBlocksAfter(value, cursorBlockId, added));
        setCursorBlockId(added[added.length - 1].id);
        setFocusRequestId(null);
    };

    const [device] = useEditorDevice();
    const styleProperties = builder.styleProperties.all();
    const presetDesigns = useSyncExternalStore(
        stylePresets.subscribe,
        stylePresets.designs,
        stylePresets.designs,
    );
    const canvasCss = useMemo(
        () =>
            canvasStyleSheet(value, {
                options: styleOptionsFrom(styleProperties),
                device,
                customCssMax: editor?.customCssMax ?? DEFAULT_CUSTOM_CSS_MAX,
                presets: presetDesigns,
                holdsChildren: (block) =>
                    Boolean(builder.blocks.get(block.type)?.acceptsChildren) ||
                    (block.children?.length ?? 0) > 0,
            }),
        [value, device, editor?.customCssMax, styleProperties, presetDesigns],
    );
    const deviceWidth = builder.breakpoints.get(device)?.maxWidth ?? null;

    const [rawSelection, setSelection] =
        useState<BlockSelection>(EMPTY_SELECTION);
    const selection = cleanSelection(value, rawSelection);
    const many = selection.ids.length > 1;

    // Tells screen readers how many blocks are picked.
    const changeSelection = (next: BlockSelection) => {
        setSelection(next);

        if (next.ids.length > 1) {
            tree.announce(
                t(
                    '{count, plural, one {# block selected.} other {# blocks selected.}}',
                    {
                        count: next.ids.length,
                    },
                ),
            );
        } else if (selection.ids.length > 1) {
            tree.announce(t('Selection cleared.'));
        }
    };

    const selectionTools: SelectionTools = {
        ids: many ? selection.ids : [],
        pick: (id, mode) =>
            changeSelection(applyPick(value, selection, id, mode)),
        extend: (id, direction) =>
            changeSelection(extendSelection(value, selection, id, direction)),
    };

    const selectionActions = (): SelectionActions => {
        const ids = selection.ids;
        const picked = selectedBlocks(value, ids);
        const place = locateBlock(value, ids[0] ?? '');
        const groupType = findBlockType(GROUP_BLOCK_TYPE, types);
        const parent = parentType(value, place?.parentId ?? null);
        const canGroup =
            groupType !== undefined &&
            canDrop(parent, GROUP_BLOCK_TYPE) &&
            picked.every((block) => canDrop(GROUP_BLOCK_TYPE, block.type));
        const moved = (direction: 'up' | 'down') => {
            const next = moveSelection(value, ids, direction);

            return next === value
                ? undefined
                : () => {
                      onChange(next);
                      tree.announce(
                          direction === 'up'
                              ? t(
                                    '{count, plural, one {# block moved up.} other {# blocks moved up.}}',
                                    { count: ids.length },
                                )
                              : t(
                                    '{count, plural, one {# block moved down.} other {# blocks moved down.}}',
                                    { count: ids.length },
                                ),
                      );
                  };
        };

        return {
            moveUp: moved('up'),
            moveDown: moved('down'),
            save: library.canSave()
                ? () =>
                      libraryDialog.open(picked, (item) => {
                          if (item.kind === 'synced') {
                              const holder = syncedBlockFor(item, newBlockId());
                              onChange(
                                  groupSelection(value, ids, holder).map(
                                      function strip(block: Block): Block {
                                          return block.id === holder.id
                                              ? { ...block, children: [] }
                                              : {
                                                    ...block,
                                                    children: (
                                                        block.children ?? []
                                                    ).map(strip),
                                                };
                                      },
                                  ),
                              );
                              setSelection(EMPTY_SELECTION);
                          }
                      })
                : undefined,
            duplicate() {
                const result = duplicateSelection(value, ids, cloneBlock);
                onChange(result.blocks);
                setSelection({
                    anchor: result.ids[0],
                    head: result.ids[result.ids.length - 1],
                    ids: result.ids,
                });
                tree.announce(
                    t(
                        '{count, plural, one {# block duplicated.} other {# blocks duplicated.}}',
                        {
                            count: ids.length,
                        },
                    ),
                );
            },
            copy() {
                void navigator.clipboard
                    ?.writeText(blocksToClipboard(picked))
                    .then(() =>
                        tree.announce(
                            t(
                                '{count, plural, one {# block copied.} other {# blocks copied.}}',
                                {
                                    count: ids.length,
                                },
                            ),
                        ),
                    );
            },
            group:
                canGroup && groupType
                    ? () => {
                          const section = groupType.create();
                          onChange(groupSelection(value, ids, section));
                          setSelection(EMPTY_SELECTION);
                          setCursorBlockId(section.id);
                          onSelectBlock?.(section.id);
                          tree.announce(
                              t(
                                  '{count, plural, one {# block grouped in a section.} other {# blocks grouped in a section.}}',
                                  { count: ids.length },
                              ),
                          );
                      }
                    : undefined,
            remove() {
                onChange(removeSelection(value, ids));
                setSelection(EMPTY_SELECTION);
                tree.announce(
                    t(
                        '{count, plural, one {# block deleted.} other {# blocks deleted.}}',
                        {
                            count: ids.length,
                        },
                    ),
                );
            },
            clear: () => changeSelection(EMPTY_SELECTION),
        };
    };

    // Keys for picked blocks, used when the focus is not in a text field.
    const runSelectionKeys = (event: React.KeyboardEvent): boolean => {
        if (code || inTextField(event.target)) {
            return false;
        }

        const row = (event.target as HTMLElement).closest?.('[data-block-id]');
        const id = row?.getAttribute('data-block-id') ?? cursorBlockId;

        const onlyShift =
            event.shiftKey && !event.altKey && !event.metaKey && !event.ctrlKey;

        if (
            onlyShift &&
            id &&
            (event.key === 'ArrowUp' || event.key === 'ArrowDown')
        ) {
            selectionTools.extend(id, event.key === 'ArrowUp' ? 'up' : 'down');

            return true;
        }

        if (!many) {
            return false;
        }

        if (event.key === 'Escape') {
            selectionActions().clear();

            return true;
        }

        if (event.key === 'Delete' || event.key === 'Backspace') {
            selectionActions().remove();

            return true;
        }

        return false;
    };

    // Groups of the editor toolbar. When the canvas is narrow, groups with a higher hideOrder move to the More menu first,
    // so Add blocks and Outline stay in reach.
    const pluginButtons = builder.toolbarButtons
        .all()
        .filter(
            (button) =>
                !button.feature || featureEnabled(editor, button.feature),
        );
    const toolbarGroups: ToolbarGroup[] = [];

    if (history) {
        toolbarGroups.push({
            key: 'history',
            hideOrder: 1,
            label: t('History'),
            node: (
                <>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="size-8 shrink-0 p-0"
                        aria-label={t('Undo')}
                        title={t('Undo')}
                        disabled={log.past.length === 0 || code !== null}
                        onClick={undoChange}
                    >
                        <Undo2 />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="size-8 shrink-0 p-0"
                        aria-label={t('Redo')}
                        title={t('Redo')}
                        disabled={log.future.length === 0 || code !== null}
                        onClick={redoChange}
                    >
                        <Redo2 />
                    </Button>
                    <ToolbarSeparator />
                </>
            ),
        });
    }

    if (featureEnabled(editor, 'code_editor')) {
        toolbarGroups.push({
            key: 'mode',
            hideOrder: 2,
            node: (
                <ModeSwitch
                    mode={code ? 'code' : 'visual'}
                    busy={code?.busy ?? false}
                    onChange={(mode) => void switchMode(mode)}
                />
            ),
        });
    }

    if (!code) {
        toolbarGroups.push({
            key: 'panels',
            hideOrder: 0,
            node: (
                <>
                    {featureEnabled(editor, 'add_panel') && (
                        <PanelToggle
                            label={t('Add blocks')}
                            icon={Plus}
                            pressed={panel === 'add'}
                            controls={panelId}
                            onClick={() =>
                                setPanel(panel === 'add' ? null : 'add')
                            }
                        />
                    )}
                    <Button
                        type="button"
                        variant={zoomedOut ? 'secondary' : 'ghost'}
                        size="sm"
                        aria-pressed={zoomedOut}
                        aria-label={t('Zoom out')}
                        title={t('Zoom out')}
                        onClick={() => setZoomedOut(!zoomedOut)}
                        className="h-8 shrink-0 px-2"
                        data-test="zoom-out"
                    >
                        <ZoomOut />
                        <span className={TOOLBAR_LABEL}>{t('Zoom out')}</span>
                    </Button>
                    {featureEnabled(editor, 'list_view') && (
                        <PanelToggle
                            label={t('Outline')}
                            icon={ListTree}
                            pressed={panel === 'outline'}
                            controls={panelId}
                            onClick={() =>
                                setPanel(panel === 'outline' ? null : 'outline')
                            }
                        />
                    )}
                    {pluginButtons.map((button) => {
                        const Icon = button.icon;

                        return (
                            <Button
                                key={button.name}
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 shrink-0 px-2"
                                aria-label={t(button.label)}
                                title={t(button.label)}
                                onClick={() =>
                                    button.run({
                                        blocks: value,
                                        selectedId: cursorBlockId,
                                        setBlocks: onChange,
                                    })
                                }
                            >
                                <Icon />
                                <span className={TOOLBAR_LABEL}>
                                    {t(button.label)}
                                </span>
                            </Button>
                        );
                    })}
                </>
            ),
        });
        toolbarGroups.push({
            key: 'marks',
            hideOrder: 3,
            label: t('Text style'),
            node: (
                <>
                    <ToolbarSeparator />
                    <MarkToolbar editor={activeEditor} />
                </>
            ),
        });
        toolbarGroups.push({
            key: 'insert',
            hideOrder: 4,
            label: t('Blocks'),
            node: (
                <>
                    <ToolbarSeparator />
                    <InsertToolbar types={types} onInsert={insertFromToolbar} />
                </>
            ),
        });

        if (patterns.length > 0) {
            toolbarGroups.push({
                key: 'patterns',
                hideOrder: 5,
                node: (
                    <>
                        <ToolbarSeparator />
                        <PatternPicker
                            patterns={patterns}
                            onInsert={insertPattern}
                        />
                    </>
                ),
            });
        }
    }

    return (
        <TreeContext.Provider value={treeWithClipboard}>
            <SelectBlockContext.Provider value={onSelectBlock ?? null}>
                <SelectionContext.Provider value={selectionTools}>
                    <ActiveEditorContext.Provider value={reportEditor}>
                        <ShortcodeDefinitionsContext.Provider
                            value={pluginBlocks}
                        >
                            <div
                                className="min-w-0 rounded-lg border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-950"
                                data-test="block-editor"
                                onKeyDown={(event) => {
                                    if (runSelectionKeys(event)) {
                                        event.preventDefault();

                                        return;
                                    }

                                    runShortcut(event);
                                }}
                                onCopy={(event) => {
                                    if (
                                        !code &&
                                        !inTextField(event.target) &&
                                        copySelected(event.clipboardData)
                                    ) {
                                        event.preventDefault();
                                    }
                                }}
                                onPaste={(event) => {
                                    const text =
                                        event.clipboardData.getData(
                                            BLOCKS_MIME,
                                        ) ||
                                        event.clipboardData.getData(
                                            'text/plain',
                                        );

                                    if (
                                        !code &&
                                        !inTextField(event.target) &&
                                        pasteText(text)
                                    ) {
                                        event.preventDefault();
                                    }
                                }}
                            >
                                <OverflowToolbar
                                    toolbarRef={toolbarRef}
                                    label={t('Editor')}
                                    groups={toolbarGroups}
                                    className="sticky top-0 z-20 rounded-t-lg border-b border-zinc-200 bg-white/95 px-2 py-1.5 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95"
                                />
                                <LibraryDialogHost />
                                {!code && (
                                    <BlockBreadcrumb
                                        blocks={value}
                                        selectedId={cursorBlockId}
                                        onPick={(id) => {
                                            setCursorBlockId(id);
                                            onSelectBlock?.(id);
                                            document
                                                .querySelector(
                                                    `[data-block-id="${CSS.escape(id)}"]`,
                                                )
                                                ?.scrollIntoView({
                                                    block: 'nearest',
                                                });
                                        }}
                                    />
                                )}
                                {many && (
                                    <SelectionBar
                                        count={selection.ids.length}
                                        actions={selectionActions()}
                                    />
                                )}
                                <EditorSidePanel
                                    id={panelId}
                                    title={
                                        panel === 'outline'
                                            ? t('Outline')
                                            : t('Add blocks')
                                    }
                                    description={
                                        panel === 'outline'
                                            ? t(
                                                  'Every block of the page as a tree. Drag to move.',
                                              )
                                            : t(
                                                  'Drag or click blocks, sections and patterns to add them.',
                                              )
                                    }
                                    open={panel !== null}
                                    onClose={() => setPanel(null)}
                                >
                                    {featureEnabled(editor, 'add_panel') &&
                                        featureEnabled(editor, 'list_view') && (
                                            <PanelSwitch
                                                panel={panel}
                                                onChange={setPanel}
                                            />
                                        )}
                                    {panel === 'add' ? (
                                        <AddPanel
                                            types={types}
                                            patterns={patterns}
                                            editor={editor}
                                            onInsert={insertFromPanel}
                                        />
                                    ) : panel === 'outline' ? (
                                        <div className="min-h-0 flex-1 overflow-y-auto">
                                            <OutlineView
                                                selectedId={cursorBlockId}
                                                selectedIds={selectionTools.ids}
                                                onPick={selectionTools.pick}
                                                onSelect={(id) => {
                                                    setCursorBlockId(id);
                                                    onSelectBlock?.(id);
                                                }}
                                            />
                                        </div>
                                    ) : null}
                                </EditorSidePanel>
                                {canvasCss !== '' && (
                                    <style data-verso-canvas-styles>
                                        {canvasCss}
                                    </style>
                                )}
                                <p aria-live="polite" className="sr-only">
                                    {tree.message}
                                </p>
                                <div
                                    className={cn(
                                        'mx-auto px-2 py-4 transition-[max-width] duration-200 motion-reduce:transition-none sm:px-4',
                                        zoomedOut && 'verso-canvas-zoomed',
                                    )}
                                    data-zoomed={zoomedOut ? '' : undefined}
                                    data-device={device}
                                    style={
                                        deviceWidth
                                            ? { maxWidth: `${deviceWidth}px` }
                                            : undefined
                                    }
                                >
                                    {code ? (
                                        <CodeMode
                                            state={code}
                                            onChange={setCode}
                                        />
                                    ) : (
                                        <BlockList
                                            value={value}
                                            onChange={onChange}
                                            types={types}
                                            onBlockFocus={setCursorBlockId}
                                            focusRequestId={focusRequestId}
                                        />
                                    )}
                                </div>
                            </div>
                        </ShortcodeDefinitionsContext.Provider>
                    </ActiveEditorContext.Provider>
                </SelectionContext.Provider>
            </SelectBlockContext.Provider>
        </TreeContext.Provider>
    );
}

// Visual and Code buttons; the pressed one is the current view.
function ModeSwitch({
    mode,
    busy,
    onChange,
}: {
    mode: 'visual' | 'code';
    busy: boolean;
    onChange: (mode: 'visual' | 'code') => void;
}) {
    return (
        <div
            role="group"
            aria-label={t('Editor view')}
            className="flex shrink-0 rounded-md bg-muted p-0.5"
        >
            {(['visual', 'code'] as const).map((item) => (
                <button
                    key={item}
                    type="button"
                    aria-pressed={mode === item}
                    disabled={busy}
                    onClick={() => mode !== item && onChange(item)}
                    className="rounded px-2.5 py-1 text-sm text-muted-foreground transition-colors hover:text-foreground aria-pressed:bg-background aria-pressed:text-foreground aria-pressed:shadow-xs"
                >
                    {item === 'visual' ? t('Visual') : t('Code')}
                </button>
            ))}
        </div>
    );
}

// Add blocks and Outline buttons on top of the side panel, to switch between the two.
function PanelSwitch({
    panel,
    onChange,
}: {
    panel: 'add' | 'outline' | null;
    onChange: (panel: 'add' | 'outline') => void;
}) {
    const items = [
        { value: 'add', label: t('Add blocks') },
        { value: 'outline', label: t('Outline') },
    ] as const;

    return (
        <div
            role="group"
            aria-label={t('Panel')}
            className="grid shrink-0 grid-cols-2 rounded-md bg-muted p-0.5"
        >
            {items.map((item) => (
                <button
                    key={item.value}
                    type="button"
                    aria-pressed={panel === item.value}
                    onClick={() => onChange(item.value)}
                    className="min-h-8 truncate rounded px-2 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none aria-pressed:bg-background aria-pressed:text-foreground aria-pressed:shadow-xs"
                >
                    {item.label}
                </button>
            ))}
        </div>
    );
}

// A toolbar button that opens or closes a side panel.
function PanelToggle({
    label,
    icon: Icon,
    pressed,
    controls,
    onClick,
}: {
    label: string;
    icon: LucideIcon;
    pressed: boolean;
    controls: string;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            variant={pressed ? 'secondary' : 'ghost'}
            size="sm"
            aria-pressed={pressed}
            aria-controls={controls}
            onClick={onClick}
            title={label}
            className="h-8 shrink-0 px-2"
        >
            <Icon />
            <span className={TOOLBAR_LABEL}>{label}</span>
        </Button>
    );
}

// A thin vertical line between groups of the editor toolbar.
function ToolbarSeparator({ className }: { className?: string }) {
    return (
        <span
            role="separator"
            aria-orientation="vertical"
            className={cn(
                'mx-1 h-5 w-0 shrink-0 border-s border-zinc-200 dark:border-zinc-800',
                className,
            )}
        />
    );
}

// A dropdown with every block type, used to append a block inside a column.
function AddBlockMenu({
    types,
    onAdd,
    label,
}: {
    types: BlockTypeDefinition[];
    onAdd: (definition: BlockTypeDefinition) => void;
    label?: string;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <Plus />
                    {label ?? t('Add block')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="max-h-96 w-60">
                {types.map((definition) => {
                    const Icon = definition.icon;

                    return (
                        <DropdownMenuItem
                            key={definition.type}
                            onSelect={() => onAdd(definition)}
                        >
                            <Icon />
                            {t(definition.label)}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

// A list of blocks with drag to reorder, the slash menu and keyboard flow between text blocks.
function BlockList({
    value,
    onChange,
    types,
    onBlockFocus,
    focusRequestId = null,
    parentId = null,
}: NestedBlockEditorProps & {
    parentId?: string | null;
    onBlockFocus?: (id: string) => void;
    focusRequestId?: string | null;
}) {
    const tree = useTree();
    const drag = useSyncExternalStore(
        dragState.subscribe,
        dragState.get,
        dragState.get,
    );
    const reportEditor = useContext(ActiveEditorContext);
    const selectBlock = useContext(SelectBlockContext);
    const picking = useContext(SelectionContext);
    const [focusId, setFocusId] = useState<string | null>(null);
    const [slashIndex, setSlashIndex] = useState(0);
    const [dismissedSlash, setDismissedSlash] = useState<string | null>(null);

    const replace = (index: number, block: Block) =>
        onChange(value.map((item, i) => (i === index ? block : item)));

    const insertAt = (index: number, block: Block) => {
        const next = [...value];
        next.splice(index, 0, block);
        onChange(next);

        if (TEXT_BLOCK_TYPES.includes(block.type)) {
            setFocusId(block.id);
        }
    };

    const remove = (index: number) => {
        onChange(value.filter((_, i) => i !== index));
        const previous = value
            .slice(0, index)
            .reverse()
            .find((block) => TEXT_BLOCK_TYPES.includes(block.type));
        setFocusId(previous?.id ?? null);
    };

    const move = (from: number, to: number) =>
        onChange(moveItem(value, from, to));

    const chooseFromSlash = (
        index: number,
        definition: BlockTypeDefinition,
    ) => {
        const block = definition.create();
        replace(index, block);
        setSlashIndex(0);
        setFocusId(TEXT_BLOCK_TYPES.includes(block.type) ? block.id : null);
    };

    // Shows the drop marker for a spot in this list, when the dragged blocks may go there.
    const pointAt = (event: DragEvent, spot: DropSpot) => {
        const payload = dragState.get().payload;

        if (!payload || !tree || !tree.accepts(payload, parentId)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const place =
            payload.kind === 'move'
                ? locateBlock(tree.blocks, payload.id)
                : null;
        const noMove =
            place !== null &&
            place.parentId === parentId &&
            (spot.index === place.index || spot.index === place.index + 1);
        dragState.point(noMove ? null : spot);
    };

    const dropHere = (event: DragEvent) => {
        const { payload, spot } = dragState.get();

        if (!payload || !tree) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (spot) {
            tree.drop(payload, spot);
        }

        dragState.end();
    };

    if (value.length === 0 && parentId !== null) {
        const active =
            drag.spot?.parentId === parentId && drag.spot.rowId === null;

        return (
            <div
                onDragOver={(event) =>
                    pointAt(event, {
                        parentId,
                        index: 0,
                        rowId: null,
                        edge: 'inside',
                    })
                }
                onDrop={dropHere}
                className={cn(
                    'flex min-h-12 items-center justify-center rounded-md border border-dashed px-2 text-center text-xs text-muted-foreground',
                    active && 'border-primary bg-primary/5 text-foreground',
                )}
            >
                {drag.payload ? t('Drop here') : t('Empty. Drag blocks here.')}
            </div>
        );
    }

    if (value.length === 0) {
        return (
            <button
                onDragOver={(event) =>
                    pointAt(event, {
                        parentId,
                        index: 0,
                        rowId: null,
                        edge: 'inside',
                    })
                }
                onDrop={dropHere}
                type="button"
                onClick={() => insertAt(0, BLOCK_TYPES[0].create())}
                className="flex min-h-10 w-full items-center rounded-md px-2 text-start text-muted-foreground hover:bg-zinc-50 dark:hover:bg-zinc-900"
            >
                {t('Start writing, or type / to choose a block')}
            </button>
        );
    }

    return (
        <div
            data-canvas-children={parentId !== null ? '' : undefined}
            className={cn(
                'space-y-1',
                parentId !== null &&
                    canvasClass(parentId) + CANVAS_CHILDREN_SUFFIX,
            )}
        >
            {value.map((block, index) => {
                const query = slashQuery(block);
                const slashOpen =
                    query !== null && dismissedSlash !== `${block.id}:${query}`;
                const matches = slashOpen ? searchBlockTypes(query, types) : [];
                const definition =
                    findBlockType(block.type, types) ??
                    builder.blocks.get(block.type);
                const isText = TEXT_BLOCK_TYPES.includes(block.type);

                const keys = {
                    onEnter: () => {
                        if (slashOpen) {
                            if (matches[slashIndex]) {
                                chooseFromSlash(index, matches[slashIndex]);
                            }

                            return true;
                        }

                        insertAt(index + 1, BLOCK_TYPES[0].create());

                        return true;
                    },
                    onBackspaceEmpty: () => {
                        if (value.length === 1 && block.type === 'paragraph') {
                            return false;
                        }

                        remove(index);

                        return true;
                    },
                    onArrowUp: () => {
                        if (!slashOpen) {
                            return false;
                        }

                        setSlashIndex((current) =>
                            current <= 0 ? matches.length - 1 : current - 1,
                        );

                        return true;
                    },
                    onArrowDown: () => {
                        if (!slashOpen) {
                            return false;
                        }

                        setSlashIndex((current) =>
                            current >= matches.length - 1 ? 0 : current + 1,
                        );

                        return true;
                    },
                    onEscape: () => {
                        if (!slashOpen) {
                            return false;
                        }

                        setDismissedSlash(`${block.id}:${query}`);

                        return true;
                    },
                };

                const TextFields = builder.blocks.get(block.type)?.textEdit;
                const OtherFields = builder.blocks.get(block.type)?.edit;

                return (
                    <div
                        key={block.id}
                        data-block-id={block.id}
                        onFocusCapture={() => {
                            onBlockFocus?.(block.id);
                            selectBlock?.(block.id);
                        }}
                        onPointerDownCapture={(event) => {
                            onBlockFocus?.(block.id);
                            selectBlock?.(block.id);

                            // Only the deepest block under the pointer is picked.
                            const target = event.target as HTMLElement;

                            if (
                                target.closest('[data-block-id]') !==
                                event.currentTarget
                            ) {
                                return;
                            }

                            const mode = pickMode(event);
                            picking?.pick(block.id, mode);

                            if (mode !== 'one') {
                                event.preventDefault();
                            }
                        }}
                        data-selected={
                            picking?.ids.includes(block.id) ? '' : undefined
                        }
                        onDragOver={(event) => {
                            const box =
                                event.currentTarget.getBoundingClientRect();
                            const edge = edgeFromPointer(
                                event.clientY,
                                box,
                                false,
                            );
                            pointAt(event, {
                                parentId,
                                index: index + (edge === 'after' ? 1 : 0),
                                rowId: block.id,
                                edge,
                            });
                        }}
                        onDrop={dropHere}
                        className={cn(
                            'group relative flex gap-1 rounded-md py-1 data-selected:bg-primary/5 data-selected:ring-2 data-selected:ring-primary/60',
                            drag.payload?.kind === 'move' &&
                                drag.payload.id === block.id &&
                                'opacity-40',
                            drag.spot?.rowId === block.id &&
                                (drag.spot.edge === 'after'
                                    ? 'shadow-[0_2px_0_0_var(--color-blue-600)]'
                                    : 'shadow-[0_-2px_0_0_var(--color-blue-600)]'),
                        )}
                    >
                        <div className="flex shrink-0 flex-col items-center gap-0.5 opacity-100 transition-opacity lg:opacity-0 lg:group-focus-within:opacity-100 lg:group-hover:opacity-100">
                            <button
                                type="button"
                                draggable
                                data-drag-handle
                                aria-label={t('Drag to move {block} block', {
                                    block: blockLabel(block.type),
                                })}
                                onDragStart={(event) => {
                                    event.stopPropagation();
                                    event.dataTransfer.effectAllowed = 'move';
                                    event.dataTransfer.setData(
                                        DRAG_MIME,
                                        block.id,
                                    );
                                    dragState.start({
                                        kind: 'move',
                                        id: block.id,
                                        type: block.type,
                                    });
                                }}
                                onDragEnd={() => dragState.end()}
                                className="flex size-7 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-zinc-100 hover:text-zinc-700 active:cursor-grabbing dark:hover:bg-zinc-800"
                            >
                                <GripVertical className="size-4" />
                            </button>
                            <BlockMenu
                                label={blockLabel(block.type)}
                                isFirst={index === 0}
                                isLast={index === value.length - 1}
                                onMoveUp={() => move(index, index - 1)}
                                onMoveDown={() => move(index, index + 1)}
                                onMoveInto={
                                    tree?.canMove(block.id, 'into')
                                        ? () => tree.move(block.id, 'into')
                                        : undefined
                                }
                                onMoveOut={
                                    tree?.canMove(block.id, 'out')
                                        ? () => tree.move(block.id, 'out')
                                        : undefined
                                }
                                onCopy={() => tree?.copyBlock?.(block.id)}
                                onPaste={() => tree?.pasteAfter?.(block.id)}
                                onDuplicate={() =>
                                    insertAt(index + 1, cloneBlock(block))
                                }
                                onSave={
                                    library.canSave() && block.type !== 'synced'
                                        ? () =>
                                              libraryDialog.open(
                                                  [block],
                                                  (item) => {
                                                      if (
                                                          item.kind === 'synced'
                                                      ) {
                                                          replace(
                                                              index,
                                                              syncedBlockFor(
                                                                  item,
                                                                  block.id,
                                                              ),
                                                          );
                                                      }
                                                  },
                                              )
                                        : undefined
                                }
                                onRemove={() => remove(index)}
                            />
                        </div>
                        <div
                            className={cn(
                                'relative min-w-0 flex-1',
                                hasCanvasStyles(block) && canvasClass(block.id),
                            )}
                        >
                            {isText && TextFields ? (
                                <div className={TEXT_MEASURE}>
                                    <TextFields
                                        block={block}
                                        onChange={(next) => {
                                            replace(index, next);

                                            if (slashQuery(next) !== query) {
                                                setSlashIndex(0);
                                            }
                                        }}
                                        autoFocus={
                                            focusId === block.id ||
                                            focusRequestId === block.id
                                        }
                                        onFocus={(editor) =>
                                            reportEditor?.(editor)
                                        }
                                        {...keys}
                                    />
                                </div>
                            ) : (
                                <BlockCard
                                    definition={definition}
                                    type={block.type}
                                >
                                    {block.type === 'columns' ? (
                                        <ColumnsBlockFields
                                            block={block}
                                            onChange={(next) =>
                                                replace(index, next)
                                            }
                                            types={types}
                                        />
                                    ) : definition?.fields ? (
                                        <>
                                            <PluginBlockFields
                                                block={block}
                                                fields={definition.fields}
                                                onChange={(next) =>
                                                    replace(index, next)
                                                }
                                            />
                                            {definition.acceptsChildren && (
                                                <ChildBlocksFields
                                                    block={block}
                                                    onChange={(next) =>
                                                        replace(index, next)
                                                    }
                                                    types={types}
                                                />
                                            )}
                                        </>
                                    ) : OtherFields ? (
                                        <>
                                            <OtherFields
                                                block={block}
                                                onChange={(next) =>
                                                    replace(index, next)
                                                }
                                            />
                                            {builder.blocks.get(block.type)
                                                ?.acceptsChildren && (
                                                <ChildBlocksFields
                                                    block={block}
                                                    onChange={(next) =>
                                                        replace(index, next)
                                                    }
                                                    types={types}
                                                />
                                            )}
                                        </>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            {t(
                                                'This block type can not be edited here.',
                                            )}
                                        </p>
                                    )}
                                </BlockCard>
                            )}
                            {slashOpen && (
                                <SlashMenu
                                    items={matches}
                                    activeIndex={Math.min(
                                        slashIndex,
                                        matches.length - 1,
                                    )}
                                    onHover={setSlashIndex}
                                    onSelect={(item) =>
                                        chooseFromSlash(index, item)
                                    }
                                />
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

// A titled frame around blocks that are not plain text.
function BlockCard({
    definition,
    type,
    children,
}: {
    definition: BlockTypeDefinition | undefined;
    type: string;
    children: React.ReactNode;
}) {
    const Icon = definition?.icon;

    return (
        <div className="rounded-lg border border-zinc-200 p-2.5 @3xl/canvas:p-3 dark:border-zinc-800">
            <div className="mb-2 flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {Icon && <Icon className="size-3.5" />}
                {definition ? t(definition.label) : type}
            </div>
            {children}
        </div>
    );
}

// Move, duplicate and delete actions for one block. Also works with the keyboard.
function BlockMenu({
    label,
    isFirst,
    isLast,
    onMoveUp,
    onMoveDown,
    onMoveInto,
    onMoveOut,
    onCopy,
    onPaste,
    onDuplicate,
    onSave,
    onRemove,
}: {
    label: string;
    onSave?: () => void;
    isFirst: boolean;
    isLast: boolean;
    onMoveUp: () => void;
    onMoveDown: () => void;
    onMoveInto?: () => void;
    onMoveOut?: () => void;
    onCopy?: () => void;
    onPaste?: () => void;
    onDuplicate: () => void;
    onRemove: () => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={t('{block} block options', { block: label })}
                    className="flex size-7 items-center justify-center rounded text-muted-foreground hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800"
                >
                    <MoreHorizontal className="size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuItem disabled={isFirst} onSelect={onMoveUp}>
                    <ArrowUp /> {t('Move up')}
                </DropdownMenuItem>
                <DropdownMenuItem disabled={isLast} onSelect={onMoveDown}>
                    <ArrowDown /> {t('Move down')}
                </DropdownMenuItem>
                <DropdownMenuItem disabled={!onMoveInto} onSelect={onMoveInto}>
                    <ArrowRight /> {t(MOVE_LABELS.into)}
                </DropdownMenuItem>
                <DropdownMenuItem disabled={!onMoveOut} onSelect={onMoveOut}>
                    <ArrowLeft /> {t(MOVE_LABELS.out)}
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={onDuplicate}>
                    <Copy /> {t('Duplicate')}
                </DropdownMenuItem>
                {onCopy && (
                    <DropdownMenuItem onSelect={onCopy}>
                        <ClipboardCopy /> {t('Copy block')}
                    </DropdownMenuItem>
                )}
                {onPaste && (
                    <DropdownMenuItem onSelect={onPaste}>
                        <ClipboardPaste /> {t('Paste after this block')}
                    </DropdownMenuItem>
                )}
                {onSave && (
                    <DropdownMenuItem onSelect={onSave}>
                        <LibraryBig /> {t('Save to the library')}
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={onRemove}>
                    <Trash2 /> {t('Delete')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

// The inner blocks of a plugin block that holds children, like a members only box.
function ChildBlocksFields({
    block,
    onChange,
    types = [],
}: BlockFieldProps & { types?: BlockTypeDefinition[] }) {
    // Blocks offered inside follow the drop rules, so plugins and themes can change them.
    const innerTypes = types.filter((type) => canDrop(block.type, type.type));
    const children = block.children ?? [];

    return (
        <div className="mt-2 grid gap-2 rounded-md border border-dashed border-zinc-200 p-1.5 dark:border-zinc-800">
            <BlockList
                parentId={block.id}
                value={children}
                types={innerTypes}
                onChange={(next) => onChange({ ...block, children: next })}
            />
            <div>
                <AddBlockMenu
                    label={t('Add inside')}
                    types={innerTypes}
                    onAdd={(definition) =>
                        onChange({
                            ...block,
                            children: [...children, definition.create()],
                        })
                    }
                />
            </div>
        </div>
    );
}

// Columns side by side. Each column holds its own list of blocks.
function ColumnsBlockFields({
    block,
    onChange,
    types = [],
}: BlockFieldProps & { types?: BlockTypeDefinition[] }) {
    const columns = (block.children ?? []).filter(
        (child) => child.type === 'column',
    );
    const innerTypes = types.filter((type) => type.type !== 'columns');

    const setColumns = (next: Block[]) =>
        onChange({ ...block, children: next });

    return (
        <div className="grid gap-2">
            <div
                className="grid gap-3 md:grid-cols-[repeat(var(--columns),minmax(0,1fr))]"
                style={
                    {
                        '--columns': Math.max(1, columns.length),
                    } as React.CSSProperties
                }
            >
                {columns.map((column, index) => (
                    <div
                        key={column.id}
                        className="min-w-0 rounded-md border border-dashed border-zinc-200 p-2 dark:border-zinc-800"
                    >
                        <BlockList
                            parentId={column.id}
                            value={column.children ?? []}
                            types={innerTypes}
                            onChange={(children) =>
                                setColumns(
                                    columns.map((item, i) =>
                                        i === index
                                            ? { ...item, children }
                                            : item,
                                    ),
                                )
                            }
                        />
                        <div className="mt-2">
                            <AddBlockMenu
                                label={t('Add')}
                                types={innerTypes}
                                onAdd={(definition) =>
                                    setColumns(
                                        columns.map((item, i) =>
                                            i === index
                                                ? {
                                                      ...item,
                                                      children: [
                                                          ...(item.children ??
                                                              []),
                                                          definition.create(),
                                                      ],
                                                  }
                                                : item,
                                        ),
                                    )
                                }
                            />
                        </div>
                    </div>
                ))}
            </div>
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={columns.length >= MAX_COLUMNS}
                    onClick={() => setColumns([...columns, createColumn()])}
                >
                    <Plus /> {t('Column')}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={columns.length <= 1}
                    onClick={() => setColumns(columns.slice(0, -1))}
                >
                    {t('Remove last column')}
                </Button>
            </div>
        </div>
    );
}

export default BlockEditor;

// The one "Save to the library" dialog of the editor, opened from block menus and the selection bar.
function LibraryDialogHost() {
    const request = useSyncExternalStore(
        libraryDialog.subscribe,
        libraryDialog.read,
        libraryDialog.read,
    );

    return (
        <SaveToLibraryDialog
            blocks={request?.blocks ?? null}
            onClose={libraryDialog.close}
            onSaved={(item) => request?.onSaved(item)}
        />
    );
}
