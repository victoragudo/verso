import { vi } from 'vitest';
import type {
    EntryEditorContext,
    EntryRowContext,
    SlotItem,
} from '@/components/verso/slots';
import type { ContentTypeInfo } from '@/types';

export const postType = {
    name: 'post',
    label: 'Post',
    pluralLabel: 'Posts',
    icon: 'posts',
    hierarchical: false,
} as unknown as ContentTypeInfo;

// A slot item as the server shares it.
export function slotItem(overrides: Partial<SlotItem> = {}): SlotItem {
    return {
        key: 'reading-time',
        component: 'reading-time.badge',
        label: 'Reading time',
        order: 100,
        props: {},
        ...overrides,
    };
}

export function rowContext(
    row: Partial<EntryRowContext['row']> = {},
): EntryRowContext {
    return {
        row: {
            id: 42,
            title: 'Winter Menu at Harbor Lane',
            status: 'published',
            url: 'https://harborlane.example/blog/winter-menu',
            ...row,
        },
        contentType: postType,
    };
}

// An entry editor context with spies, for slot components of the editor.
export function editorContext(
    overrides: Partial<EntryEditorContext> = {},
): EntryEditorContext {
    return {
        entry: null,
        contentType: postType,
        title: 'Winter Menu at Harbor Lane',
        setTitle: vi.fn(),
        excerpt: 'Soups, stews and fresh sourdough.',
        setExcerpt: vi.fn(),
        blocks: [],
        setBlocks: vi.fn(),
        replaceBlocks: vi.fn(),
        meta: {},
        setMeta: vi.fn(),
        updateMeta: vi.fn(),
        activeEditor: null,
        ...overrides,
    };
}
