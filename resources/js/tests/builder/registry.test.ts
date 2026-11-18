import { Sparkles } from 'lucide-react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    builder,
    canDrop,
    createRegistry,
    editorBlockTypes,
    featureEnabled,
} from '@/builder';
import type { EditorManifest } from '@/builder';
import { applyManifest, registerPluginBlocks } from '@/builder/core';
import { resetBuilder, setupBuilder } from '@/builder/setup';

const manifest: EditorManifest = {
    version: 1,
    categories: [
        { key: 'text', label: 'Text', order: 10 },
        { key: 'commerce', label: 'Shop', order: 15 },
    ],
    styleProperties: [
        {
            path: 'spacing.padding.top',
            kind: 'length',
            label: 'Padding top',
            group: 'spacing',
            control: 'length',
            options: [],
        },
    ],
    breakpoints: [
        { key: 'base', maxWidth: null },
        { key: 'tablet', maxWidth: 1024 },
        { key: 'mobile', maxWidth: 640 },
    ],
    features: ['add_panel', 'code_editor'],
    allowedBlocks: ['paragraph', 'heading', 'image'],
    limits: { colors: ['#1f6f5c'], fonts: null, spacing: null },
};

beforeEach(() => {
    resetBuilder();
    setupBuilder({});
});

describe('createRegistry', () => {
    type Item = { name: string; order?: number };
    const make = () =>
        createRegistry<Item>(
            (item) => item.name,
            (item) => item.order ?? 50,
        );

    it('sorts by order, then by the order items were added', () => {
        const registry = make();
        registry.register({ name: 'gallery' });
        registry.register({ name: 'hero', order: 10 });
        registry.register({ name: 'faq' });

        expect(registry.all().map((item) => item.name)).toEqual([
            'hero',
            'gallery',
            'faq',
        ]);
    });

    it('replaces an item with the same key in its old place', () => {
        const registry = make();
        registry.register({ name: 'pricing' });
        registry.register({ name: 'team' });
        registry.register({ name: 'pricing', order: 50 });

        expect(registry.all().map((item) => item.name)).toEqual([
            'pricing',
            'team',
        ]);
    });

    it('returns a function that removes only the item it added', () => {
        const registry = make();
        const remove = registry.register({ name: 'map' });
        const replacement = { name: 'map', order: 5 };
        registry.register(replacement);
        remove();

        expect(registry.get('map')).toBe(replacement);
    });

    it('tells listeners about changes and keeps the same list until then', () => {
        const registry = make();
        const listener = vi.fn();
        const stop = registry.subscribe(listener);
        registry.register({ name: 'countdown' });
        const first = registry.all();

        expect(registry.all()).toBe(first);
        registry.unregister('countdown');
        expect(listener).toHaveBeenCalledTimes(2);
        stop();
        registry.register({ name: 'timeline' });
        expect(listener).toHaveBeenCalledTimes(2);
    });
});

describe('core registration', () => {
    it('registers the core blocks with categories and toolbar buttons', () => {
        expect(builder.blocks.get('heading')?.category).toBe('text');
        expect(builder.blocks.get('image')?.category).toBe('media');
        expect(builder.blocks.get('columns')?.category).toBe('layout');
        expect(builder.blocks.get('html')?.category).toBe('advanced');
        expect(builder.blocks.get('paragraph')?.primary).toBe(true);
        expect(builder.blocks.get('table')?.primary).toBe(false);
        expect(builder.blocks.get('column')?.hidden).toBe(true);
        expect(builder.blocks.get('image')?.edit).toBeTypeOf('function');
        expect(builder.blocks.get('heading')?.textEdit).toBeTypeOf('function');
    });

    it('registers the insert sources for blocks and patterns', () => {
        const sources = builder.insertSources.all();
        const context = {
            blocks: builder.blocks.all(),
            patterns: [
                {
                    name: 'harbor/hero',
                    title: 'Harbor hero',
                    category: 'Headers',
                    description: 'A big title with a button.',
                    keywords: ['hero'],
                    blocks: [
                        {
                            type: 'heading',
                            attrs: { level: 1 },
                            content: 'Fresh fish every morning',
                        },
                    ],
                },
            ],
        };

        expect(sources.map((source) => source.name)).toEqual([
            'core/blocks',
            'core/sections',
            'core/patterns',
            'core/library',
        ]);
        expect(sources[0].items(context).map((item) => item.key)).not.toContain(
            'block:column',
        );
        const [pattern] = sources[2].items(context);
        expect(pattern.label).toBe('Harbor hero');
        expect(pattern.create()[0].content).toBe('Fresh fish every morning');
    });

    it('runs plugin and theme modules after core, in path order', () => {
        resetBuilder();
        const calls: string[] = [];
        setupBuilder({
            '/themes/harbor/resources/js/builder.ts': {
                default: () => calls.push('theme'),
            },
            '/plugins/bookings/resources/js/builder.ts': {
                default: (api) => {
                    calls.push(`plugin:${api.blocks.has('paragraph')}`);
                },
            },
        });

        expect(calls).toEqual(['plugin:true', 'theme']);
    });

    it('skips a broken module and keeps the others', () => {
        resetBuilder();
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});
        setupBuilder({
            '/plugins/broken/resources/js/builder.ts': {
                default: () => {
                    throw new Error('Missing dependency');
                },
            },
            '/plugins/shop/resources/js/builder.ts': {
                default: (api) =>
                    api.categories.register({
                        name: 'commerce',
                        label: 'Shop',
                    }),
            },
        });

        expect(builder.categories.has('commerce')).toBe(true);
        expect(error).toHaveBeenCalledOnce();
        error.mockRestore();
    });
});

describe('canDrop', () => {
    it('follows the core block rules', () => {
        expect(canDrop('columns', 'column')).toBe(true);
        expect(canDrop('columns', 'paragraph')).toBe(false);
        expect(canDrop('column', 'image')).toBe(true);
        expect(canDrop('column', 'columns')).toBe(false);
        expect(canDrop('paragraph', 'image')).toBe(false);
    });

    it('lets plugin containers hold blocks but not a single column', () => {
        registerPluginBlocks(builder, [
            {
                name: 'accordion',
                label: 'Accordion',
                description: 'Questions that open.',
                children: true,
                fields: [],
            },
        ]);

        expect(canDrop('accordion', 'paragraph')).toBe(true);
        expect(canDrop('accordion', 'column')).toBe(false);
    });

    it('asks later rules first', () => {
        builder.dropRules.register({
            name: 'harbor/no-video-in-columns',
            allows: (parent, child) =>
                parent === 'column' && child === 'video' ? false : null,
        });

        expect(canDrop('column', 'video')).toBe(false);
        expect(canDrop('column', 'image')).toBe(true);
    });
});

describe('manifest', () => {
    it('applies categories, style options and screen sizes', () => {
        applyManifest(builder, manifest);

        expect(builder.categories.get('commerce')?.label).toBe('Shop');
        expect(builder.styleProperties.get('spacing.padding.top')?.label).toBe(
            'Padding top',
        );
        expect(
            builder.breakpoints
                .all()
                .map((breakpoint) => [breakpoint.label, breakpoint.maxWidth]),
        ).toEqual([
            ['Computer', null],
            ['Tablet', 1024],
            ['Phone', 640],
        ]);
    });

    it('only offers the allowed blocks', () => {
        expect(editorBlockTypes(manifest).map((block) => block.type)).toEqual([
            'paragraph',
            'heading',
            'image',
        ]);
        expect(
            editorBlockTypes(undefined).map((block) => block.type),
        ).not.toContain('column');
    });

    it('checks features, with simple defaults when no manifest is sent', () => {
        expect(featureEnabled(manifest, 'code_editor')).toBe(true);
        expect(featureEnabled(manifest, 'custom_js')).toBe(false);
        expect(featureEnabled(undefined, 'add_panel')).toBe(true);
        expect(featureEnabled(undefined, 'code_editor')).toBe(false);
    });

    it('keeps a block a module registered over the server plugin block', () => {
        builder.blocks.register({
            type: 'booking-form',
            label: 'Booking form',
            description: 'Let guests book a table.',
            icon: Sparkles,
            keywords: ['reservation'],
            category: 'commerce',
            create: () => ({
                id: 'b_booking',
                type: 'booking-form',
                attrs: {},
                content: '',
                children: [],
            }),
        });
        registerPluginBlocks(builder, [
            {
                name: 'booking-form',
                label: 'Booking form (server)',
                description: '',
                children: false,
                fields: [],
            },
        ]);

        expect(builder.blocks.get('booking-form')?.category).toBe('commerce');
    });
});
