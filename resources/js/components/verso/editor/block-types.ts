import {
    Code,
    Columns2,
    Film,
    LetterH,
    Images,
    Image as ImageIcon,
    List,
    type LucideIcon,
    Minus,
    MousePointerClick,
    Pilcrow,
    Puzzle,
    Quote,
    Table,
    FileCode,
    SquarePlay,
} from 'lucide-react';
import { msg, t } from '@/lib/i18n';
import type {
    Block,
    CoreBlockType,
    PluginBlockDefinition,
    BlockPattern,
    PluginBlockField,
} from '@/types';
import { patternToBlocks } from './patterns';

// What the editor knows about one block type: its label, icon and default value.
export type BlockTypeDefinition = {
    type: string;
    label: string;
    description: string;
    icon: LucideIcon;
    keywords: string[];
    create: () => Block;
    fields?: PluginBlockField[];
    // True for plugin blocks that hold other blocks as children.
    acceptsChildren?: boolean;
    // Only these block types may go inside, or null for any.
    childTypes?: string[] | null;
    // The block may only sit directly inside these block types.
    parents?: string[] | null;
};

// Text blocks are edited with Tiptap and use the inline marks toolbar.
export const TEXT_BLOCK_TYPES: string[] = [
    'paragraph',
    'heading',
    'list',
    'quote',
];

// Makes a short random id like "b_k2j3h4".
export function newBlockId(): string {
    return `b_${Math.random().toString(36).slice(2, 10)}`;
}

function block(
    type: CoreBlockType,
    attrs: Record<string, unknown> = {},
    content = '',
    children: Block[] = [],
): Block {
    return { id: newBlockId(), type, attrs, content, children };
}

export const BLOCK_TYPES: BlockTypeDefinition[] = [
    {
        type: 'paragraph',
        label: msg('Paragraph'),
        description: msg('Plain text with bold, italic and links.'),
        icon: Pilcrow,
        keywords: ['text', 'p'],
        create: () => block('paragraph', {}, '<p></p>'),
    },
    {
        type: 'heading',
        label: msg('Heading'),
        description: msg('A section title.'),
        icon: LetterH,
        keywords: ['title', 'h1', 'h2', 'h3'],
        create: () => block('heading', { level: 2 }),
    },
    {
        type: 'list',
        label: msg('List'),
        description: msg('A bulleted or numbered list.'),
        icon: List,
        keywords: ['bullet', 'numbered', 'ul', 'ol'],
        create: () =>
            block('list', { ordered: false }, '<ul><li><p></p></li></ul>'),
    },
    {
        type: 'quote',
        label: msg('Quote'),
        description: msg('Words from someone else.'),
        icon: Quote,
        keywords: ['blockquote', 'citation'],
        create: () => block('quote', { citation: '' }, '<p></p>'),
    },
    {
        type: 'code',
        label: msg('Code'),
        description: msg('Source code shown as it is.'),
        icon: Code,
        keywords: ['pre', 'snippet'],
        create: () => block('code', { language: '' }),
    },
    {
        type: 'image',
        label: msg('Image'),
        description: msg('One picture from the media library.'),
        icon: ImageIcon,
        keywords: ['photo', 'picture', 'img'],
        create: () =>
            block('image', {
                mediaId: null,
                src: '',
                alt: '',
                caption: '',
            }),
    },
    {
        type: 'gallery',
        label: msg('Gallery'),
        description: msg('A grid of pictures.'),
        icon: Images,
        keywords: ['photos', 'grid'],
        create: () =>
            block('gallery', { mediaIds: [], images: [], columns: 3 }),
    },
    {
        type: 'embed',
        label: msg('Embed'),
        description: msg('A YouTube or Vimeo video.'),
        icon: SquarePlay,
        keywords: ['youtube', 'vimeo', 'video', 'iframe'],
        create: () => block('embed', { url: '', caption: '' }),
    },
    {
        type: 'video',
        label: msg('Video'),
        description: msg('A video file from the media library.'),
        icon: Film,
        keywords: ['movie', 'mp4'],
        create: () => block('video', { mediaId: null, src: '', caption: '' }),
    },
    {
        type: 'columns',
        label: msg('Columns'),
        description: msg('Place blocks side by side.'),
        icon: Columns2,
        keywords: ['layout', 'grid', 'row'],
        create: () =>
            block('columns', {}, '', [createColumn(), createColumn()]),
    },
    {
        type: 'table',
        label: msg('Table'),
        description: msg('Rows and columns of data.'),
        icon: Table,
        keywords: ['grid', 'data'],
        create: () =>
            block('table', {
                header: true,
                rows: [
                    ['', ''],
                    ['', ''],
                ],
            }),
    },
    {
        type: 'button',
        label: msg('Button'),
        description: msg('A link that looks like a button.'),
        icon: MousePointerClick,
        keywords: ['link', 'cta', 'call to action'],
        create: () =>
            block('button', { label: msg(''), url: '', style: 'primary' }),
    },
    {
        type: 'separator',
        label: msg('Separator'),
        description: msg('A line between two parts.'),
        icon: Minus,
        keywords: ['hr', 'divider', 'line'],
        create: () => block('separator'),
    },
    {
        type: 'html',
        label: msg('HTML'),
        description: msg('Raw HTML. Only admins can save it.'),
        icon: FileCode,
        keywords: ['raw', 'custom', 'embed code'],
        create: () => block('html'),
    },
];

// A new empty column for a columns block.
export function createColumn(): Block {
    return block('column', {}, '', [block('paragraph', {}, '<p></p>')]);
}

export function findBlockType(
    type: string,
    types: BlockTypeDefinition[] = BLOCK_TYPES,
): BlockTypeDefinition | undefined {
    return (
        types.find((definition) => definition.type === type) ??
        BLOCK_TYPES.find((definition) => definition.type === type)
    );
}

// The starting attrs of a plugin block, taken from the field defaults.
function pluginDefaults(fields: PluginBlockField[]): Record<string, unknown> {
    return Object.fromEntries(
        fields
            .filter(
                (field) =>
                    field.default !== null && field.default !== undefined,
            )
            .map((field) => [field.name, field.default]),
    );
}

// Turns the plugin blocks sent by the server into editor block types.
export function pluginBlockTypes(
    definitions: PluginBlockDefinition[],
): BlockTypeDefinition[] {
    return definitions.map((definition) => ({
        type: definition.name,
        label: definition.label,
        description:
            definition.description || msg('A block added by a plugin.'),
        icon: Puzzle,
        keywords: ['plugin', definition.name.replace(/-/g, ' ')],
        fields: definition.fields,
        acceptsChildren: definition.children,
        childTypes: definition.childTypes ?? null,
        parents: definition.parents ?? null,
        create: () => ({
            id: newBlockId(),
            type: definition.name,
            attrs: pluginDefaults(definition.fields),
            content: '',
            children: patternToBlocks({
                name: definition.name,
                title: definition.label,
                blocks: definition.starter ?? [],
            } as BlockPattern),
        }),
    }));
}

// Block types matching a search, used by the slash menu.
export function searchBlockTypes(
    query: string,
    types: BlockTypeDefinition[] = BLOCK_TYPES,
): BlockTypeDefinition[] {
    const needle = query.trim().toLowerCase();

    if (needle === '') {
        return types;
    }

    return types.filter(
        (definition) =>
            definition.label.toLowerCase().includes(needle) ||
            t(definition.label).toLowerCase().includes(needle) ||
            definition.type.includes(needle) ||
            definition.keywords.some((keyword) => keyword.includes(needle)),
    );
}

// Moves one item of a list to a new position.
export function moveItem<T>(items: T[], from: number, to: number): T[] {
    if (from === to || from < 0 || from >= items.length) {
        return items;
    }

    const next = [...items];
    const [moved] = next.splice(from, 1);
    next.splice(Math.max(0, Math.min(to, next.length)), 0, moved);

    return next;
}

// Puts a block right after the block with the given id, or at the end when that id is not found.
export function insertBlockAfter(
    blocks: Block[],
    afterId: string | null,
    block: Block,
): Block[] {
    const index =
        afterId === null ? -1 : blocks.findIndex((item) => item.id === afterId);
    const next = [...blocks];
    next.splice(index === -1 ? blocks.length : index + 1, 0, block);

    return next;
}

// A deep copy of a block with new ids, used by "Duplicate".
export function cloneBlock(source: Block): Block {
    return {
        ...source,
        id: newBlockId(),
        attrs: structuredClone(source.attrs),
        children: (source.children ?? []).map(cloneBlock),
    };
}

// The first text of some HTML, used to detect a slash command.
export function plainText(html: string | undefined): string {
    if (!html) {
        return '';
    }

    return html
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
}
