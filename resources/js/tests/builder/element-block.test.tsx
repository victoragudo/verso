import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { builder, editorBlockTypes } from '@/builder';
import type { EditorManifest } from '@/builder';
import { applyManifest } from '@/builder/core';
import { resetBuilder, setupBuilder } from '@/builder/setup';
import {
    attributeWarning,
    ElementBlockFields,
    getElementTags,
} from '@/components/verso/editor/blocks/element-block';
import type { Block } from '@/types';
import { pickOption } from '@/tests/pick-option';

const manifest: EditorManifest = {
    version: 1,
    categories: [],
    styleProperties: [],
    breakpoints: [],
    features: ['element_block'],
    allowedBlocks: null,
    elementTags: [
        { value: 'div', label: 'Box (div)' },
        { value: 'nav', label: 'Navigation (nav)' },
        { value: 'a', label: 'Link (a)' },
    ],
    limits: { colors: null, fonts: null, spacing: null },
};

const nav: Block = {
    id: 'b_nav',
    type: 'element',
    attrs: { tag: 'nav', attributes: { 'aria-label': 'Footer links' } },
    content: '',
    children: [],
};

beforeEach(() => {
    resetBuilder();
    setupBuilder({});
    applyManifest(builder, manifest);
});

describe('element block registration', () => {
    it('is an advanced container block that needs its feature', () => {
        const element = builder.blocks.get('element')!;

        expect(element.category).toBe('advanced');
        expect(element.acceptsChildren).toBe(true);
        expect(element.create().attrs).toEqual({ tag: 'div' });
        expect(editorBlockTypes(manifest).map((block) => block.type)).toContain(
            'element',
        );
        expect(
            editorBlockTypes({ ...manifest, features: [] }).map(
                (block) => block.type,
            ),
        ).not.toContain('element');
    });

    it('takes the tag list from the server', () => {
        expect(getElementTags().map((tag) => tag.value)).toEqual([
            'div',
            'nav',
            'a',
        ]);
    });
});

describe('attributeWarning', () => {
    it.each([
        ['div', 'id', null],
        ['div', 'data-season', null],
        ['nav', 'aria-label', null],
        ['a', 'href', null],
        ['div', 'href', '"href" can not be used on <div>.'],
        [
            'div',
            'onclick',
            '"onclick" is not allowed here. Use the HTML block for scripts and inline styles.',
        ],
        [
            'div',
            'style',
            '"style" is not allowed here. Use the HTML block for scripts and inline styles.',
        ],
        ['div', '', null],
    ])('<%s %s> gives %j', (tag, name, warning) => {
        expect(attributeWarning(tag, name)).toBe(warning);
    });
});

describe('ElementBlockFields', () => {
    it('changes the tag and the text', async () => {
        const onChange = vi.fn();
        render(<ElementBlockFields block={nav} onChange={onChange} />);
        await pickOption('Element', 'Link (a)');

        expect(onChange).toHaveBeenLastCalledWith({
            ...nav,
            attrs: { ...nav.attrs, tag: 'a' },
        });
        fireEvent.change(screen.getByLabelText('Text (optional)'), {
            target: { value: 'Visit us' },
        });
        expect(onChange).toHaveBeenLastCalledWith({
            ...nav,
            content: 'Visit us',
        });
    });

    it('adds, edits and removes attributes', async () => {
        const onChange = vi.fn();
        render(<ElementBlockFields block={nav} onChange={onChange} />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Add attribute' }),
        );

        expect(onChange).toHaveBeenLastCalledWith({
            ...nav,
            attrs: {
                tag: 'nav',
                attributes: { 'aria-label': 'Footer links', id: '' },
            },
        });
        fireEvent.change(screen.getByLabelText('Attribute 1 value'), {
            target: { value: 'Main links' },
        });
        expect(onChange).toHaveBeenLastCalledWith({
            ...nav,
            attrs: { tag: 'nav', attributes: { 'aria-label': 'Main links' } },
        });
        await userEvent.click(
            screen.getByRole('button', { name: 'Remove attribute aria-label' }),
        );
        expect(onChange).toHaveBeenLastCalledWith({
            ...nav,
            attrs: { tag: 'nav', attributes: {} },
        });
    });

    it('warns about attributes the server refuses', () => {
        render(
            <ElementBlockFields
                block={{
                    ...nav,
                    attrs: { tag: 'nav', attributes: { onmouseover: 'x' } },
                }}
                onChange={vi.fn()}
            />,
        );

        expect(
            screen.getByText(/Use the HTML block for scripts/),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Attribute 1 name')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
    });
});
