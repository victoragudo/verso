import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { builder, canDrop } from '@/builder';
import type { EditorManifest } from '@/builder';
import { registerPluginBlocks } from '@/builder/core';
import {
    discoverModules,
    moduleFolder,
    resetBuilder,
    setupBuilder,
} from '@/builder/setup';
import { BlockInspector } from '@/components/verso/editor/block-inspector';
import * as exampleModule from '../../../../plugins/builder-example/resources/js/builder';

const manifest: EditorManifest = {
    version: 1,
    categories: [],
    styleProperties: [],
    breakpoints: [],
    features: [],
    allowedBlocks: null,
    limits: { colors: null, fonts: null, spacing: null },
};

beforeEach(() => {
    resetBuilder();
});

describe('module discovery', () => {
    it('finds the builder file of the example plugin', () => {
        expect(Object.keys(discoverModules())).toContain(
            '/plugins/builder-example/resources/js/builder.tsx',
        );
        expect(
            moduleFolder('/plugins/builder-example/resources/js/builder.tsx'),
        ).toBe('plugins/builder-example');
        expect(moduleFolder('/themes/ashgrove/resources/js/builder.ts')).toBe(
            'themes/ashgrove',
        );
    });

    it('only runs modules of enabled plugins', () => {
        setupBuilder(
            {
                '/plugins/builder-example/resources/js/builder.tsx':
                    exampleModule,
            },
            [],
        );

        expect(builder.inspectorPanels.has('builder-example/notice-tips')).toBe(
            false,
        );
    });
});

describe('builder example plugin', () => {
    beforeEach(() => {
        setupBuilder(
            {
                '/plugins/builder-example/resources/js/builder.tsx':
                    exampleModule,
            },
            ['plugins/builder-example'],
        );
        registerPluginBlocks(builder, [
            {
                name: 'example-notice',
                label: 'Notice',
                description: 'A colored box.',
                fields: [],
                children: false,
                category: 'examples',
            },
        ]);
    });

    it('puts the server block in its category', () => {
        expect(builder.blocks.get('example-notice')?.category).toBe('examples');
    });

    it('adds a drop rule that keeps notices out of columns', () => {
        expect(canDrop('column', 'example-notice')).toBe(false);
        expect(canDrop('root', 'example-notice')).toBe(true);
        expect(canDrop('column', 'paragraph')).toBe(true);
    });

    it('shows its tips panel for notices only', () => {
        const { rerender } = render(
            <BlockInspector
                block={{
                    id: 'b_n',
                    type: 'example-notice',
                    attrs: { tone: 'warning' },
                    content: '',
                    children: [],
                }}
                onChange={() => {}}
                editor={manifest}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Notice tips' }),
        ).toBeInTheDocument();
        expect(screen.getByText(/Warnings catch the eye/)).toBeInTheDocument();
        rerender(
            <BlockInspector
                block={{
                    id: 'b_p',
                    type: 'paragraph',
                    attrs: {},
                    content: '<p>Hi</p>',
                    children: [],
                }}
                onChange={() => {}}
                editor={manifest}
            />,
        );
        expect(
            screen.queryByRole('heading', { name: 'Notice tips' }),
        ).not.toBeInTheDocument();
    });
});

describe('toolbar buttons', () => {
    it('shows registered toolbar buttons and runs them with the document', async () => {
        const { BlockEditor } =
            await import('@/components/verso/editor/block-editor');
        const { Sparkles } = await import('lucide-react');
        const userEvent = (await import('@testing-library/user-event')).default;
        setupBuilder({}, []);
        const seen: number[] = [];
        builder.toolbarButtons.register({
            name: 'bookings/count',
            label: 'Count blocks',
            icon: Sparkles,
            run: ({ blocks }) => seen.push(blocks.length),
        });
        render(
            <BlockEditor
                value={[
                    {
                        id: 'b_1',
                        type: 'separator',
                        attrs: {},
                        content: '',
                        children: [],
                    },
                ]}
                onChange={() => {}}
                editor={manifest}
            />,
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Count blocks' }),
        );

        expect(seen).toEqual([1]);
    });
});
