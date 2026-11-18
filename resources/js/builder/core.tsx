import { CodeXml, Columns2, LayoutTemplate, Repeat } from 'lucide-react';
import type { ComponentType } from 'react';
import {
    BLOCK_TYPES,
    cloneBlock,
    createColumn,
    newBlockId,
    pluginBlockTypes,
} from '@/components/verso/editor/block-types';
import {
    ElementBlockFields,
    setElementTags,
} from '@/components/verso/editor/blocks/element-block';
import { EmbedBlockFields } from '@/components/verso/editor/blocks/embed-block';
import {
    GalleryBlockFields,
    ImageBlockFields,
    VideoBlockFields,
} from '@/components/verso/editor/blocks/media-blocks';
import type { BlockFieldProps } from '@/components/verso/editor/blocks/shared';
import {
    ButtonBlockFields,
    CodeBlockFields,
    HtmlBlockFields,
    SeparatorBlockFields,
} from '@/components/verso/editor/blocks/simple-blocks';
import { SyncedBlockFields } from '@/components/verso/editor/blocks/synced-block';
import { TableBlockFields } from '@/components/verso/editor/blocks/table-block';
import { patternToBlocks } from '@/components/verso/editor/patterns';
import { StylesPanel } from '@/components/verso/editor/styles-panel';
import {
    HeadingBlockFields,
    ListBlockFields,
    ParagraphBlockFields,
    QuoteBlockFields,
    type TextBlockProps,
} from '@/components/verso/editor/text-blocks';
import { msg } from '@/lib/i18n';
import type { PluginBlockDefinition } from '@/types';
import { registerCoreSerializers } from './code';
import { setIconsUrl } from '@/components/verso/editor/blocks/field-inputs';
import { library } from './library';
import { stylePresets } from './presets';
import { readySections, sectionBlocks, setSections } from './sections';
import type { BuilderApi } from './index';
import type { BuilderBlock, EditorManifest, InsertItem } from './types';

// Group of each core block in the Add panel.
export const CORE_BLOCK_CATEGORIES: Record<string, string> = {
    paragraph: 'text',
    heading: 'text',
    list: 'text',
    quote: 'text',
    code: 'text',
    table: 'text',
    image: 'media',
    gallery: 'media',
    embed: 'media',
    video: 'media',
    columns: 'layout',
    column: 'layout',
    separator: 'layout',
    button: 'layout',
    section: 'layout',
    html: 'advanced',
    element: 'advanced',
};

// Blocks that get their own button in the editor toolbar.
export const CORE_PRIMARY_BLOCKS = [
    'paragraph',
    'heading',
    'list',
    'quote',
    'image',
    'gallery',
    'embed',
];

const TEXT_EDITORS: Record<string, (props: TextBlockProps) => React.ReactNode> =
    {
        paragraph: ParagraphBlockFields,
        heading: HeadingBlockFields,
        list: ListBlockFields,
        quote: QuoteBlockFields,
    };

const FIELD_EDITORS: Record<string, ComponentType<BlockFieldProps>> = {
    code: CodeBlockFields,
    image: ImageBlockFields,
    gallery: GalleryBlockFields,
    embed: EmbedBlockFields,
    video: VideoBlockFields,
    table: TableBlockFields,
    button: ButtonBlockFields,
    html: HtmlBlockFields,
    separator: SeparatorBlockFields,
};

// Default categories until the server sends its list.
const CORE_CATEGORIES = [
    { name: 'text', label: msg('Text'), order: 10 },
    { name: 'media', label: msg('Media'), order: 20 },
    { name: 'interactive', label: msg('Interactive'), order: 25 },
    { name: 'layout', label: msg('Layout'), order: 30 },
    { name: 'site', label: msg('Site'), order: 40 },
    { name: 'dynamic', label: msg('Page data'), order: 45 },
    { name: 'advanced', label: msg('Advanced'), order: 50 },
    { name: 'plugins', label: msg('From plugins'), order: 60 },
];

// Plain names of the screen sizes.
export const BREAKPOINT_LABELS: Record<string, string> = {
    base: msg('Computer'),
    tablet: msg('Tablet'),
    mobile: msg('Phone'),
};

// Same rules as the PHP blocks: columns only hold columns, a column holds anything but columns,
// containers hold anything but a single column.
export function coreDropRule(
    api: BuilderApi,
): (parent: string, child: string) => boolean | null {
    return (parent, child) => {
        if (parent === 'root') {
            return child !== 'column';
        }

        if (parent === 'columns') {
            return child === 'column';
        }

        if (child === 'column') {
            return false;
        }

        if (parent === 'column') {
            return child !== 'columns';
        }

        return api.blocks.get(parent)?.acceptsChildren ? true : null;
    };
}

// Adds every core item through the public API, like a plugin would.
export function registerCore(api: BuilderApi): void {
    CORE_CATEGORIES.forEach((category) => api.categories.register(category));

    BLOCK_TYPES.forEach((definition, index) =>
        api.blocks.register({
            ...definition,
            category: CORE_BLOCK_CATEGORIES[definition.type] ?? 'advanced',
            order: index,
            primary: CORE_PRIMARY_BLOCKS.includes(definition.type),
            edit: FIELD_EDITORS[definition.type],
            textEdit: TEXT_EDITORS[definition.type],
        }),
    );

    api.blocks.register({
        type: 'element',
        label: msg('HTML element'),
        description: msg(
            'Any safe HTML element, like a nav, details or span, with its own attributes.',
        ),
        icon: CodeXml,
        keywords: ['div', 'span', 'tag', 'html', 'nav', 'details', 'custom'],
        create: () => ({
            id: newBlockId(),
            type: 'element',
            attrs: { tag: 'div' },
            content: '',
            children: [],
        }),
        category: 'advanced',
        order: BLOCK_TYPES.length,
        acceptsChildren: true,
        feature: 'element_block',
        edit: ElementBlockFields,
    });

    // A single column only lives inside a columns block, so it is hidden from insert lists.
    api.blocks.register({
        type: 'column',
        label: msg('Column'),
        description: msg('One column of a columns block.'),
        icon: Columns2,
        keywords: [],
        create: createColumn,
        category: 'layout',
        order: BLOCK_TYPES.length,
        hidden: true,
    });

    api.dropRules.register({
        name: 'core/blocks',
        order: 0,
        allows: coreDropRule(api),
    });

    // Blocks that name their parents or their children (tabs only take tabs, a tab only sits in tabs).
    // The same rule as App\Verso\Editor\Builder\ParentDropRule and the block childTypes on the server.
    api.dropRules.register({
        name: 'core/parents',
        order: 10,
        allows: (parent, child) => {
            const parents = api.blocks.get(child)?.parents;

            if (parents) {
                return parents.includes(parent);
            }

            const childTypes = api.blocks.get(parent)?.childTypes;

            return childTypes ? childTypes.includes(child) : null;
        },
    });

    api.insertSources.register({
        name: 'core/blocks',
        label: msg('Blocks'),
        order: 10,
        items: ({ blocks }) =>
            blocks
                // Blocks that only live inside one parent (a tab) are added from that parent.
                .filter((block) => !block.hidden && !block.parents)
                .map((block): InsertItem => ({
                    key: `block:${block.type}`,
                    label: block.label,
                    description: block.description,
                    icon: block.icon,
                    category: block.category ?? 'plugins',
                    keywords: block.keywords,
                    create: () => [block.create()],
                })),
    });

    api.insertSources.register({
        name: 'core/patterns',
        label: msg('Patterns'),
        order: 20,
        items: ({ patterns }) =>
            patterns.map((pattern): InsertItem => ({
                key: `pattern:${pattern.name}`,
                label: pattern.title,
                description: pattern.description,
                icon: LayoutTemplate,
                category: pattern.category,
                keywords: pattern.keywords,
                create: () => patternToBlocks(pattern),
            })),
    });

    // Ready sections of Easy mode (testimonials, prices, team, FAQ...), built on the server with fresh ids.
    api.insertSources.register({
        name: 'core/sections',
        label: msg('Sections'),
        order: 15,
        items: () =>
            readySections().map((section): InsertItem => ({
                key: `section:${section.key}`,
                label: section.label,
                description: section.description,
                icon: LayoutTemplate,
                category: section.category,
                keywords: section.keywords,
                create: () => [],
                load: () => sectionBlocks(section.key),
            })),
    });

    // Saved synced blocks and patterns of the design library.
    api.insertSources.register({
        name: 'core/library',
        label: msg('Library'),
        order: 25,
        items: () =>
            library.all().map((item): InsertItem => ({
                key: `library:${item.id}`,
                label: item.title,
                description:
                    item.kind === 'synced'
                        ? msg('Synced block: the same on every page.')
                        : msg('Pattern: the page gets its own copy.'),
                icon: item.kind === 'synced' ? Repeat : LayoutTemplate,
                category: item.category || 'library',
                keywords: item.keywords,
                create: () =>
                    item.kind === 'synced'
                        ? [
                              {
                                  id: newBlockId(),
                                  type: 'synced',
                                  attrs: { ref: item.id },
                                  content: '',
                                  children: [],
                              },
                          ]
                        : item.blocks.map(cloneBlock),
            })),
    });

    // The synced block is added from the Library tab and edited in the library.
    api.blocks.register({
        type: 'synced',
        label: msg('Synced block'),
        description: msg(
            'Blocks from the library that look the same everywhere they are used.',
        ),
        icon: Repeat,
        keywords: ['reusable', 'global', 'library'],
        create: () => ({
            id: newBlockId(),
            type: 'synced',
            attrs: {},
            content: '',
            children: [],
        }),
        category: 'advanced',
        order: BLOCK_TYPES.length + 1,
        hidden: true,
        edit: SyncedBlockFields,
    });

    api.inspectorPanels.register({
        name: 'core/styles',
        title: msg('Styles'),
        order: 20,
        feature: 'styles_panel',
        appliesTo: () => true,
        component: StylesPanel,
    });

    // Keyboard moves for the selected block, the same as the drag actions.
    (
        [
            ['up', 'ArrowUp', msg('Move the block up')],
            ['down', 'ArrowDown', msg('Move the block down')],
            ['into', 'ArrowRight', msg('Move the block into the block above')],
            ['out', 'ArrowLeft', msg('Move the block out of its group')],
        ] as const
    ).forEach(([direction, key, label]) =>
        api.shortcuts.register({
            name: `core/move-${direction}`,
            keys: `Alt+Shift+${key}`,
            label,
            run: ({ selectedId, move }) => {
                if (selectedId) {
                    move(selectedId, direction);
                }
            },
        }),
    );

    api.shortcuts.register({
        name: 'core/undo',
        keys: 'Mod+z',
        label: msg('Undo'),
        run: ({ undo }) => undo(),
    });
    api.shortcuts.register({
        name: 'core/redo',
        keys: 'Mod+Shift+z',
        label: msg('Redo'),
        run: ({ redo }) => redo(),
    });
    api.shortcuts.register({
        name: 'core/redo-y',
        keys: 'Mod+y',
        label: msg('Redo'),
        run: ({ redo }) => redo(),
    });

    registerCoreSerializers(api);

    Object.entries(BREAKPOINT_LABELS).forEach(([name, label], order) =>
        api.breakpoints.register({ name, label, maxWidth: null, order }),
    );
}

// Adds the blocks the server knows from plugins and site blocks. Blocks a module already registered are kept.
export function registerPluginBlocks(
    api: BuilderApi,
    definitions: PluginBlockDefinition[],
): void {
    pluginBlockTypes(definitions).forEach((definition) => {
        if (!api.blocks.has(definition.type)) {
            api.blocks.register({
                ...definition,
                category:
                    CORE_BLOCK_CATEGORIES[definition.type] ??
                    definitions.find((item) => item.name === definition.type)
                        ?.category ??
                    'plugins',
                order: definition.type in CORE_BLOCK_CATEGORIES ? 100 : 1000,
            } satisfies BuilderBlock);
        }
    });
}

// Takes the categories, style options and screen sizes from the server.
export function applyManifest(api: BuilderApi, manifest: EditorManifest): void {
    setElementTags(manifest.elementTags ?? []);
    stylePresets.set(manifest.stylePresets ?? []);
    stylePresets.setEditable(manifest.canEditPresets === true);
    setIconsUrl(manifest.iconsUrl);
    setSections(manifest.sections ?? [], manifest.sectionUrl ?? null);
    library.set(
        manifest.library?.items ?? [],
        manifest.library
            ? {
                  canSave: manifest.library.canSave,
                  storeUrl: manifest.library.storeUrl,
                  editUrl: manifest.library.editUrl,
              }
            : null,
    );
    manifest.categories.forEach((category) =>
        api.categories.register({
            name: category.key,
            label: category.label,
            order: category.order,
        }),
    );
    manifest.styleProperties.forEach((property, order) =>
        api.styleProperties.register({ ...property, order }),
    );
    manifest.breakpoints.forEach((breakpoint, order) =>
        api.breakpoints.register({
            name: breakpoint.key,
            label: BREAKPOINT_LABELS[breakpoint.key] ?? breakpoint.key,
            maxWidth: breakpoint.maxWidth,
            order,
        }),
    );
}
