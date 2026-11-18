import { beforeEach, describe, expect, it } from 'vitest';
import { builder } from '@/builder';
import { resetBuilder, setupBuilder } from '@/builder/setup';
import { pluginBlockTypes } from '@/components/verso/editor/block-types';
import { splitBlockTypes } from '@/components/verso/editor/insert-toolbar';

let BLOCK_TYPES = builder.blocks.all();

beforeEach(() => {
    resetBuilder();
    setupBuilder({});
    BLOCK_TYPES = builder.blocks.all();
});

describe('splitBlockTypes', () => {
    const recipeCard = pluginBlockTypes([
        {
            name: 'recipe-card',
            label: 'Recipe card',
            description: 'Ingredients, steps and baking time.',
            children: false,
            fields: [],
        },
    ]);

    it('keeps the common blocks as toolbar buttons in their order', () => {
        const { primary } = splitBlockTypes(BLOCK_TYPES);

        expect(primary.map((type) => type.type)).toEqual([
            'paragraph',
            'heading',
            'list',
            'quote',
            'image',
            'gallery',
            'embed',
        ]);
    });

    it('moves the other core blocks to the menu', () => {
        const { core } = splitBlockTypes(BLOCK_TYPES);
        const names = core.map((type) => type.type);

        expect(names).toContain('separator');
        expect(names).toContain('table');
        expect(names).not.toContain('paragraph');
    });

    it('lists plugin blocks apart from core blocks', () => {
        const { core, plugins } = splitBlockTypes([
            ...BLOCK_TYPES,
            ...recipeCard,
        ]);

        expect(plugins.map((type) => type.type)).toEqual(['recipe-card']);
        expect(core.map((type) => type.type)).not.toContain('recipe-card');
    });
});
