import type { Editor } from '@tiptap/react';
import type { ComponentType, Dispatch, SetStateAction } from 'react';
import type { RowAction } from '@/components/verso/ui';
import type { Block, ContentTypeInfo, Entry } from '@/types';

// The slot names, the same values as the PHP AdminSlot enum.
export type SlotName =
    | 'entry-sidebar'
    | 'entry-publish'
    | 'entry-toolbar'
    | 'entries-columns'
    | 'entries-row-actions'
    | 'dashboard-widgets'
    | 'dashboard-top'
    | 'settings-tabs'
    | 'profile-sections'
    | 'page-editor';

// One item a plugin added to a slot, as shared by the server in versoSlots.
export type SlotItem = {
    key: string;
    component: string;
    label: string | null;
    order: number;
    props: Record<string, unknown>;
};

export type SharedSlots = Partial<Record<SlotName, SlotItem[]>>;

// What the entry editor gives to its slot components.
export type EntryEditorContext = {
    entry: Entry | null;
    contentType: ContentTypeInfo;
    title: string;
    setTitle: (title: string) => void;
    excerpt: string;
    setExcerpt: (excerpt: string) => void;
    blocks: Block[];
    setBlocks: Dispatch<SetStateAction<Block[]>>;
    // Sets new blocks and remounts the block editor so it shows them.
    replaceBlocks: (blocks: Block[]) => void;
    meta: Record<string, unknown>;
    setMeta: (key: string, value: unknown) => void;
    updateMeta: (key: string, update: (current: unknown) => unknown) => void;
    activeEditor: Editor | null;
};

// One row of the entries table: the server row data plus any data from the AdminEntryRowFilter.
export type EntryRowData = {
    id: number;
    title: string;
    status: string;
    url: string;
    [key: string]: unknown;
};

export type EntryRowContext = {
    row: EntryRowData;
    contentType: ContentTypeInfo;
};

export type EmptyContext = Record<string, never>;

export type SlotContexts = {
    'entry-sidebar': EntryEditorContext;
    'entry-publish': EntryEditorContext;
    'entry-toolbar': EntryEditorContext;
    'entries-columns': EntryRowContext;
    'entries-row-actions': EntryRowContext;
    'dashboard-widgets': EmptyContext;
    'dashboard-top': EmptyContext;
    'settings-tabs': EmptyContext;
    'profile-sections': EmptyContext;
    'page-editor': EmptyContext;
};

// The props every slot component gets.
export type SlotComponentProps<C = unknown> = {
    item: SlotItem;
    props: Record<string, unknown>;
    context: C;
};

export type SlotComponent<C = unknown> = ComponentType<SlotComponentProps<C>>;

export type SlotComponentOptions = {
    // Values this component adds to the entry meta when the editor opens.
    initialMeta?: (
        meta: Record<string, unknown>,
        props: Record<string, unknown>,
    ) => Record<string, unknown>;
};

// Builds the menu items of one entries table row.
export type RowActionBuilder = (
    context: EntryRowContext,
    props: Record<string, unknown>,
    item: SlotItem,
) => RowAction | RowAction[] | null;
