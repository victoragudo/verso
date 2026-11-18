import type { LucideIcon } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import type { BlockTypeDefinition } from '@/components/verso/editor/block-types';
import type { BlockFieldProps } from '@/components/verso/editor/blocks/shared';
import type { TextBlockProps } from '@/components/verso/editor/text-blocks';
import type { CssValueRule } from '@/lib/css-value';
import type { Block } from '@/types';

// Version of the builder contract. It changes only when a change breaks plugins.
export const BUILDER_API_VERSION = 1;

// A block type in the editor. "category" groups it in the Add panel.
export type BuilderBlock = BlockTypeDefinition & {
    category?: string;
    order?: number;
    // Gets its own button in the editor toolbar.
    primary?: boolean;
    // Fields shown for the block in the page.
    edit?: ComponentType<BlockFieldProps>;
    // Rich text editor for text blocks (paragraph, heading...).
    textEdit?: (props: TextBlockProps) => ReactNode;
    // Hidden from insert lists, like a single column.
    hidden?: boolean;
    // Editor feature needed to add the block, like "element_block".
    feature?: string;
};

export type BuilderCategory = { name: string; label: string; order?: number };

// One option of the Styles panel. Path is where the value lives in attrs.design.
export type BuilderStyleProperty = {
    path: string;
    kind: string;
    // CSS property the option sets, like "padding-top". Needed for the live styles in the canvas.
    css?: string;
    label: string;
    group: string;
    control: string;
    options: string[];
    // Units, keywords and plain numbers a size kind (length, angle, time) accepts. Null for other kinds.
    value?: CssValueRule | null;
    order?: number;
};

export type InspectorPanelProps = {
    block: Block;
    onChange: (block: Block) => void;
    // Screen size being edited: "base" (every screen), "tablet" or "mobile".
    device: string;
    // Design limits of the theme, null when a list is free.
    limits: EditorManifest['limits'];
};

// A panel in the "Block" tab of the editor sidebar.
export type BuilderInspectorPanel = {
    name: string;
    title: string;
    order?: number;
    // Editor feature the user needs, like "styles_panel".
    feature?: string;
    appliesTo: (block: Block) => boolean;
    component: ComponentType<InspectorPanelProps>;
};

export type ToolbarContext = {
    blocks: Block[];
    selectedId: string | null;
    setBlocks: (blocks: Block[]) => void;
};

// A button in the editor top bar.
export type BuilderToolbarButton = {
    name: string;
    label: string;
    icon: LucideIcon;
    order?: number;
    feature?: string;
    run: (context: ToolbarContext) => void;
};

// Something people can add from the Add panel: a block, a pattern, a plugin item.
export type InsertItem = {
    key: string;
    label: string;
    description: string;
    icon: LucideIcon;
    category: string;
    keywords: string[];
    create: () => Block[];
    // Items built on the server: the Add panel waits for these blocks instead of create().
    load?: () => Promise<Block[]>;
};

export type InsertSourceContext = {
    blocks: BuilderBlock[];
    patterns: import('@/types').BlockPattern[];
};

// A tab of the Add panel and the items it lists.
export type BuilderInsertSource = {
    name: string;
    label: string;
    order?: number;
    feature?: string;
    items: (context: InsertSourceContext) => InsertItem[];
};

// Says if a block may go inside another block. Null lets the next rule decide.
export type BuilderDropRule = {
    name: string;
    order?: number;
    allows: (parent: string, child: string) => boolean | null;
};

// Turns one block into code and back, for the Code view.
export type BuilderCodeSerializer = {
    name: string;
    serialize: (block: Block) => string;
    parse: (inner: string, attrs: Record<string, unknown>) => Block;
};

export type ShortcutContext = ToolbarContext & {
    move: (id: string, direction: 'up' | 'down' | 'into' | 'out') => void;
    undo: () => void;
    redo: () => void;
};

// A keyboard shortcut, like "Mod+Shift+D". "Mod" is Ctrl, or Cmd on a Mac.
export type BuilderShortcut = {
    name: string;
    keys: string;
    label: string;
    feature?: string;
    // Also runs while typing in a text field. Off by default, so fields keep their own keys.
    inFields?: boolean;
    run: (context: ShortcutContext) => void;
};

// A screen size used by responsive styles and the preview.
export type BuilderBreakpoint = {
    name: string;
    label: string;
    maxWidth: number | null;
    order?: number;
};

// The "editor" prop the server sends to every editor page.
export type EditorManifest = {
    version: number;
    categories: { key: string; label: string; order: number }[];
    styleProperties: Omit<BuilderStyleProperty, 'order'>[];
    breakpoints: { key: string; maxWidth: number | null }[];
    features: string[];
    allowedBlocks: string[] | null;
    // How many items the "Recently used" list of the Add panel keeps.
    recentLimit?: number;
    // Most undo steps kept, and how close (ms) typing changes must be to count as one step.
    undoSteps?: number;
    undoMergeMs?: number;
    // Longest custom CSS one block can hold, in characters.
    customCssMax?: number;
    // Folders whose builder modules may run, like "plugins/builder-example". Others are skipped.
    modules?: string[];
    // Tags the HTML element block may use, with plain names.
    elementTags?: { value: string; label: string }[];
    // Style presets ("Card", "Hero") every block can use, and if this person may change them.
    stylePresets?: StylePresetItem[];
    canEditPresets?: boolean;
    // Address of the bundled icon set (JSON).
    iconsUrl?: string | null;
    // Ready sections of Easy mode and the address that builds one ("section-key" is replaced).
    sections?: ReadySection[];
    sectionUrl?: string | null;
    // Saved synced blocks and patterns, and if this person may save more.
    library?: {
        items: LibraryItem[];
        canSave: boolean;
        storeUrl: string;
        editUrl: string;
    };
    limits: {
        colors: string[] | null;
        fonts: string[] | null;
        spacing: string[] | null;
        // Units the theme allows in the unit picker, null for every unit.
        units?: string[] | null;
    };
};

// A style preset: a named set of block styles. "saved" presets were made in the editor.
export type StylePresetItem = {
    key: string;
    label: string;
    design: Record<string, unknown>;
    source: 'core' | 'theme' | 'saved';
    changed: boolean;
};

// A ready section people can add from the Add panel.
export type ReadySection = {
    key: string;
    label: string;
    description: string;
    category: string;
    keywords: string[];
};

// One item of the design library.
export type LibraryItem = {
    id: number;
    kind: 'synced' | 'pattern';
    title: string;
    category: string;
    keywords: string[];
    blocks: Block[];
    updatedAt: string | null;
};
