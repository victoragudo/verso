import { act, fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { builder } from '@/builder';
import type { EditorManifest } from '@/builder';
import { applyManifest } from '@/builder/core';
import { resetBuilder, setupBuilder } from '@/builder/setup';
import { styleClipboard } from '@/builder/styles';
import { BlockInspector } from '@/components/verso/editor/block-inspector';
import type { Block } from '@/types';
import { pickOption } from '@/tests/pick-option';

const manifest: EditorManifest = {
    version: 1,
    categories: [],
    styleProperties: [
        {
            path: 'spacing.padding.top',
            kind: 'length',
            label: 'Padding top',
            group: 'spacing',
            control: 'length',
            options: [],
        },
        {
            path: 'color.background',
            kind: 'color',
            label: 'Background',
            group: 'color',
            control: 'color',
            options: [],
        },
        {
            path: 'layout.justify',
            kind: 'justify',
            label: 'Justify',
            group: 'layout',
            control: 'select',
            options: ['start', 'center', 'between'],
        },
        {
            path: 'visibility.hidden',
            kind: 'hidden',
            label: 'Hidden',
            group: 'visibility',
            control: 'toggle',
            options: [],
        },
    ],
    breakpoints: [
        { key: 'base', maxWidth: null },
        { key: 'tablet', maxWidth: 1024 },
        { key: 'mobile', maxWidth: 640 },
    ],
    features: ['styles_panel'],
    allowedBlocks: null,
    limits: { colors: null, fonts: null, spacing: null },
};

const heading: Block = {
    id: 'b_title',
    type: 'heading',
    attrs: { level: 2 },
    content: 'Our menu',
    children: [],
};

function open(group: string) {
    fireEvent.click(screen.getByText(group));
}

beforeEach(() => {
    resetBuilder();
    setupBuilder({});
    builder.styleProperties.clear();
    applyManifest(builder, manifest);
    styleClipboard.copy({});
});

describe('BlockInspector', () => {
    it('asks people to pick a block when none is selected', () => {
        render(
            <BlockInspector
                block={null}
                onChange={vi.fn()}
                editor={manifest}
            />,
        );

        expect(
            screen.getByText(/Click a block in the page/),
        ).toBeInTheDocument();
    });

    it('hides the Styles panel when the feature is off', () => {
        render(
            <BlockInspector
                block={heading}
                onChange={vi.fn()}
                editor={{ ...manifest, features: [] }}
            />,
        );

        expect(
            document.querySelector('[data-test="styles-panel"]'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText('This block has no extra settings.'),
        ).toBeInTheDocument();
    });

    it('saves a valid size and ignores a size the server would refuse', () => {
        const onChange = vi.fn();
        render(
            <BlockInspector
                block={heading}
                onChange={onChange}
                editor={manifest}
            />,
        );
        open('Spacing');
        const input = screen.getByLabelText('Padding top');

        fireEvent.change(input, { target: { value: '12 apples' } });
        fireEvent.blur(input);
        expect(onChange).not.toHaveBeenCalled();
        expect(
            screen.getByText(/Use a size like 16px, 1.5rem or 50%/),
        ).toBeInTheDocument();
        expect(input).toHaveAttribute('aria-invalid', 'true');

        fireEvent.change(input, { target: { value: '48px' } });
        expect(onChange).toHaveBeenLastCalledWith({
            ...heading,
            attrs: {
                level: 2,
                design: { spacing: { padding: { top: '48px' } } },
            },
        });
    });

    it('adds the picked unit to a typed number and keeps the number when the unit changes', async () => {
        const onChange = vi.fn();
        const { rerender } = render(
            <BlockInspector
                block={heading}
                onChange={onChange}
                editor={manifest}
            />,
        );
        open('Spacing');
        fireEvent.change(screen.getByLabelText('Padding top'), {
            target: { value: '3' },
        });

        expect(onChange).toHaveBeenLastCalledWith({
            ...heading,
            attrs: {
                level: 2,
                design: { spacing: { padding: { top: '3px' } } },
            },
        });

        const saved = onChange.mock.lastCall![0] as Block;
        rerender(
            <BlockInspector
                block={saved}
                onChange={onChange}
                editor={manifest}
            />,
        );
        await userEvent.click(
            screen.getByRole('combobox', { name: 'Padding top unit' }),
        );
        await userEvent.type(
            screen.getByRole('combobox', { name: 'Search units' }),
            'dvh{Enter}',
        );

        expect(onChange).toHaveBeenLastCalledWith({
            ...heading,
            attrs: {
                level: 2,
                design: { spacing: { padding: { top: '3dvh' } } },
            },
        });
    });

    it('limits the units to the ones the theme allows', async () => {
        render(
            <BlockInspector
                block={heading}
                onChange={vi.fn()}
                editor={{
                    ...manifest,
                    limits: { ...manifest.limits, units: ['rem', '%'] },
                }}
            />,
        );
        open('Spacing');
        await userEvent.click(
            screen.getByRole('combobox', { name: 'Padding top unit' }),
        );
        const units = within(screen.getByRole('listbox'))
            .getAllByRole('option')
            .map((option) => option.textContent);

        expect(units).toEqual([
            expect.stringContaining('rem'),
            expect.stringContaining('%'),
            expect.stringContaining('Custom'),
        ]);
    });

    it('stores a phone only value when the phone size is picked', async () => {
        const onChange = vi.fn();
        render(
            <BlockInspector
                block={heading}
                onChange={onChange}
                editor={manifest}
            />,
        );
        await userEvent.click(screen.getByRole('radio', { name: 'Phone' }));
        open('Visibility');
        await userEvent.click(
            screen.getByRole('checkbox', { name: 'Hide on this screen size' }),
        );

        expect(
            screen.getByText(/only apply to phone screens/),
        ).toBeInTheDocument();
        expect(onChange).toHaveBeenCalledWith({
            ...heading,
            attrs: {
                level: 2,
                design: { visibility: { hidden: { mobile: true } } },
            },
        });
    });

    it('offers theme colors as swatches when the theme limits colors', async () => {
        const onChange = vi.fn();
        render(
            <BlockInspector
                block={heading}
                onChange={onChange}
                editor={{
                    ...manifest,
                    limits: {
                        ...manifest.limits,
                        colors: ['#1f6f5c', '#f4efe6'],
                    },
                }}
            />,
        );
        open('Colors');
        await userEvent.click(screen.getByRole('button', { name: '#f4efe6' }));

        expect(onChange).toHaveBeenCalledWith({
            ...heading,
            attrs: { level: 2, design: { color: { background: '#f4efe6' } } },
        });
    });

    it('picks a keyword from a list with plain names', async () => {
        const onChange = vi.fn();
        render(
            <BlockInspector
                block={heading}
                onChange={onChange}
                editor={manifest}
            />,
        );
        open('Layout');
        await pickOption('Justify', 'Between');

        expect(onChange).toHaveBeenCalledWith({
            ...heading,
            attrs: { level: 2, design: { layout: { justify: 'between' } } },
        });
    });

    it('copies styles from one block and pastes them on another', async () => {
        const styled = {
            ...heading,
            attrs: { level: 2, design: { color: { background: '#1f6f5c' } } },
        };
        const paragraph: Block = {
            id: 'b_intro',
            type: 'paragraph',
            attrs: {},
            content: '<p>Open daily</p>',
            children: [],
        };
        const onChange = vi.fn();
        const { rerender } = render(
            <BlockInspector
                block={styled}
                onChange={onChange}
                editor={manifest}
            />,
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Copy styles' }),
        );
        rerender(
            <BlockInspector
                block={paragraph}
                onChange={onChange}
                editor={manifest}
            />,
        );
        await act(async () => {
            await userEvent.click(
                screen.getByRole('button', { name: 'Paste styles' }),
            );
        });

        expect(onChange).toHaveBeenCalledWith({
            ...paragraph,
            attrs: { design: { color: { background: '#1f6f5c' } } },
        });
    });
});
