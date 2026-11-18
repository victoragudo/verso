import { describe, expect, it } from 'vitest';
import {
    BLOCK_TYPES,
    cloneBlock,
    createColumn,
    findBlockType,
    moveItem,
    newBlockId,
    plainText,
    pluginBlockTypes,
    searchBlockTypes,
    TEXT_BLOCK_TYPES,
} from '@/components/verso/editor/block-types';
import type { Block, PluginBlockDefinition, PluginBlockField } from '@/types';

function field(overrides: Partial<PluginBlockField>): PluginBlockField {
    return {
        name: 'title',
        label: 'Title',
        type: 'text',
        default: null,
        options: [],
        help: '',
        required: false,
        min: null,
        max: null,
        ...overrides,
    };
}

const pricingTable: PluginBlockDefinition = {
    name: 'pricing-table',
    label: 'Pricing table',
    description: 'Plans and prices side by side.',
    children: false,
    fields: [
        field({ name: 'currency', label: 'Currency', default: 'EUR' }),
        field({ name: 'plans', label: 'Plans', type: 'number', default: 3 }),
        field({
            name: 'highlight',
            label: 'Highlight the middle plan',
            type: 'toggle',
            default: false,
        }),
        field({ name: 'footnote', label: 'Footnote', type: 'textarea' }),
    ],
};

const typeNames = (types: { type: string }[]) => types.map((t) => t.type);

describe('newBlockId', () => {
    it('makes ids with the b_ prefix', () => {
        expect(newBlockId()).toMatch(/^b_[a-z0-9]{1,8}$/);
    });

    it('makes a different id every time', () => {
        const ids = new Set(Array.from({ length: 200 }, () => newBlockId()));

        expect(ids.size).toBe(200);
    });
});

describe('BLOCK_TYPES', () => {
    it('lists every core block except column', () => {
        expect(typeNames(BLOCK_TYPES)).toEqual([
            'paragraph',
            'heading',
            'list',
            'quote',
            'code',
            'image',
            'gallery',
            'embed',
            'video',
            'columns',
            'table',
            'button',
            'separator',
            'html',
        ]);
    });

    it.each(BLOCK_TYPES.map((definition) => [definition.type, definition]))(
        'creates a valid empty %s block',
        (type, definition) => {
            const block = definition.create();

            expect(block.type).toBe(type);
            expect(block.id).toMatch(/^b_/);
            expect(block.attrs).toBeTypeOf('object');
            expect(Array.isArray(block.children)).toBe(true);
            expect(typeof block.content).toBe('string');
        },
    );

    it('gives each new block its own id', () => {
        const paragraph = BLOCK_TYPES[0];

        expect(paragraph.create().id).not.toBe(paragraph.create().id);
    });

    it('starts a paragraph with an empty p tag', () => {
        expect(findBlockType('paragraph')?.create().content).toBe('<p></p>');
    });

    it('starts a heading at level 2', () => {
        expect(findBlockType('heading')?.create().attrs).toEqual({ level: 2 });
    });

    it('starts a list as a bulleted list with one item', () => {
        const list = findBlockType('list')!.create();

        expect(list.attrs).toEqual({ ordered: false });
        expect(list.content).toBe('<ul><li><p></p></li></ul>');
    });

    it('starts a table with a header and a two by two grid', () => {
        expect(findBlockType('table')?.create().attrs).toEqual({
            header: true,
            rows: [
                ['', ''],
                ['', ''],
            ],
        });
    });

    it('starts a columns block with two columns that hold a paragraph', () => {
        const columns = findBlockType('columns')!.create();

        expect(columns.children).toHaveLength(2);
        columns.children!.forEach((column) => {
            expect(column.type).toBe('column');
            expect(column.children?.[0].type).toBe('paragraph');
        });
    });

    it('starts a button with the primary style', () => {
        expect(findBlockType('button')?.create().attrs).toEqual({
            label: '',
            url: '',
            style: 'primary',
        });
    });

    it('marks only the Tiptap blocks as text blocks', () => {
        expect(TEXT_BLOCK_TYPES).toEqual([
            'paragraph',
            'heading',
            'list',
            'quote',
        ]);
    });
});

describe('createColumn', () => {
    it('makes a column with one empty paragraph', () => {
        const column = createColumn();

        expect(column.type).toBe('column');
        expect(column.children).toHaveLength(1);
        expect(column.children?.[0]).toMatchObject({
            type: 'paragraph',
            content: '<p></p>',
        });
    });
});

describe('findBlockType', () => {
    it('finds core types', () => {
        expect(findBlockType('quote')?.label).toBe('Quote');
    });

    it('finds plugin types in the given list', () => {
        const types = [...BLOCK_TYPES, ...pluginBlockTypes([pricingTable])];

        expect(findBlockType('pricing-table', types)?.label).toBe(
            'Pricing table',
        );
    });

    it('falls back to core types when the list does not have them', () => {
        const types = pluginBlockTypes([pricingTable]);

        expect(findBlockType('image', types)?.label).toBe('Image');
    });

    it('returns undefined for unknown types', () => {
        expect(findBlockType('countdown-timer')).toBeUndefined();
    });
});

describe('pluginBlockTypes', () => {
    it('turns a plugin definition into an editor type', () => {
        const [definition] = pluginBlockTypes([pricingTable]);

        expect(definition.type).toBe('pricing-table');
        expect(definition.label).toBe('Pricing table');
        expect(definition.description).toBe('Plans and prices side by side.');
        expect(definition.fields).toBe(pricingTable.fields);
        expect(definition.keywords).toEqual(['plugin', 'pricing table']);
    });

    it('uses a default description when the plugin gives none', () => {
        const [definition] = pluginBlockTypes([
            { ...pricingTable, description: '' },
        ]);

        expect(definition.description).toBe('A block added by a plugin.');
    });

    it('creates blocks with the field defaults and skips null defaults', () => {
        const block = pluginBlockTypes([pricingTable])[0].create();

        expect(block).toMatchObject({
            type: 'pricing-table',
            content: '',
            children: [],
            attrs: { currency: 'EUR', plans: 3, highlight: false },
        });
        expect(block.attrs).not.toHaveProperty('footnote');
    });

    it('keeps false and zero defaults', () => {
        const block = pluginBlockTypes([
            {
                name: 'countdown',
                label: 'Countdown',
                description: '',
                children: false,
                fields: [
                    field({ name: 'days', type: 'number', default: 0 }),
                    field({ name: 'loop', type: 'toggle', default: false }),
                ],
            },
        ])[0].create();

        expect(block.attrs).toEqual({ days: 0, loop: false });
    });

    it('returns an empty list when no plugin adds blocks', () => {
        expect(pluginBlockTypes([])).toEqual([]);
    });
});

describe('searchBlockTypes', () => {
    it('returns every type for an empty query', () => {
        expect(searchBlockTypes('')).toBe(BLOCK_TYPES);
        expect(searchBlockTypes('   ')).toBe(BLOCK_TYPES);
    });

    it('matches labels without caring about case', () => {
        expect(typeNames(searchBlockTypes('IMA'))).toEqual(['image']);
    });

    it('matches the start of a word in the middle of a label', () => {
        expect(typeNames(searchBlockTypes('gal'))).toEqual(['gallery']);
    });

    it('matches keywords', () => {
        expect(typeNames(searchBlockTypes('youtube'))).toEqual(['embed']);
        expect(typeNames(searchBlockTypes('divider'))).toEqual(['separator']);
        expect(typeNames(searchBlockTypes('cta'))).toEqual(['button']);
    });

    it('matches keywords with spaces', () => {
        expect(typeNames(searchBlockTypes('call to'))).toEqual(['button']);
    });

    it('returns every type that matches a shared keyword', () => {
        expect(typeNames(searchBlockTypes('grid'))).toEqual([
            'gallery',
            'columns',
            'table',
        ]);
    });

    it('ignores spaces around the query', () => {
        expect(typeNames(searchBlockTypes('  quote '))).toEqual(['quote']);
    });

    it('returns nothing when no type matches', () => {
        expect(searchBlockTypes('spreadsheet')).toEqual([]);
    });

    it('finds plugin blocks by name and by the plugin keyword', () => {
        const types = [...BLOCK_TYPES, ...pluginBlockTypes([pricingTable])];

        expect(typeNames(searchBlockTypes('pricing', types))).toEqual([
            'pricing-table',
        ]);
        expect(typeNames(searchBlockTypes('plugin', types))).toEqual([
            'pricing-table',
        ]);
    });

    it('matches upper case keywords from plugin names', () => {
        const types = pluginBlockTypes([
            {
                name: 'faq-accordion',
                label: 'FAQ accordion',
                description: '',
                children: false,
                fields: [],
            },
        ]);

        expect(typeNames(searchBlockTypes('FAQ', types))).toEqual([
            'faq-accordion',
        ]);
    });
});

describe('moveItem', () => {
    const chapters = ['Intro', 'Setup', 'Deploy', 'Monitoring'];

    it('moves an item down', () => {
        expect(moveItem(chapters, 0, 2)).toEqual([
            'Setup',
            'Deploy',
            'Intro',
            'Monitoring',
        ]);
    });

    it('moves an item up', () => {
        expect(moveItem(chapters, 3, 1)).toEqual([
            'Intro',
            'Monitoring',
            'Setup',
            'Deploy',
        ]);
    });

    it('does not change the original list', () => {
        moveItem(chapters, 0, 3);

        expect(chapters).toEqual(['Intro', 'Setup', 'Deploy', 'Monitoring']);
    });

    it('returns the same list when nothing moves', () => {
        expect(moveItem(chapters, 2, 2)).toBe(chapters);
    });

    it('returns the same list for an index outside the list', () => {
        expect(moveItem(chapters, -1, 2)).toBe(chapters);
        expect(moveItem(chapters, 4, 0)).toBe(chapters);
    });

    it('keeps the target inside the list', () => {
        expect(moveItem(chapters, 1, 99)).toEqual([
            'Intro',
            'Deploy',
            'Monitoring',
            'Setup',
        ]);
        expect(moveItem(chapters, 2, -5)).toEqual([
            'Deploy',
            'Intro',
            'Setup',
            'Monitoring',
        ]);
    });
});

describe('cloneBlock', () => {
    const gallery: Block = {
        id: 'b_gallery1',
        type: 'gallery',
        attrs: {
            columns: 3,
            images: [{ mediaId: 41, src: '/img/harbor.jpg', alt: 'Harbor' }],
        },
        content: '',
        children: [],
    };

    it('gives the copy a new id', () => {
        const copy = cloneBlock(gallery);

        expect(copy.id).not.toBe(gallery.id);
        expect(copy.type).toBe('gallery');
    });

    it('copies attrs deeply so the copy can change alone', () => {
        const copy = cloneBlock(gallery);
        (copy.attrs.images as { alt: string }[])[0].alt = 'Lighthouse';

        expect((gallery.attrs.images as { alt: string }[])[0].alt).toBe(
            'Harbor',
        );
    });

    it('gives nested blocks new ids too', () => {
        const columns = findBlockType('columns')!.create();
        const copy = cloneBlock(columns);

        const ids = (block: Block): string[] => [
            block.id,
            ...(block.children ?? []).flatMap(ids),
        ];

        const original = new Set(ids(columns));

        ids(copy).forEach((id) => expect(original.has(id)).toBe(false));
        expect(ids(copy)).toHaveLength(original.size);
    });

    it('keeps the content', () => {
        const quote: Block = {
            id: 'b_quote1',
            type: 'quote',
            attrs: { citation: 'Grace Hopper' },
            content:
                '<p>The most dangerous phrase is "we have always done it this way".</p>',
        };

        expect(cloneBlock(quote)).toMatchObject({
            content: quote.content,
            attrs: { citation: 'Grace Hopper' },
            children: [],
        });
    });
});

describe('plainText', () => {
    it('returns an empty string for missing content', () => {
        expect(plainText(undefined)).toBe('');
        expect(plainText('')).toBe('');
    });

    it('removes tags', () => {
        expect(plainText('<p>Fresh <strong>sourdough</strong> daily</p>')).toBe(
            'Fresh sourdough daily',
        );
    });

    it('decodes the common entities', () => {
        expect(plainText('<p>Salt &amp; pepper&nbsp;&lt;3&gt;</p>')).toBe(
            'Salt & pepper <3>',
        );
    });

    it('keeps a slash command readable', () => {
        expect(plainText('<p>/ima</p>')).toBe('/ima');
    });

    it('does not decode an entity twice', () => {
        expect(plainText('<p>&amp;lt;b&amp;gt;</p>')).toBe('&lt;b&gt;');
    });
});
