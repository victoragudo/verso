import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { builder } from '@/builder';
import type { EditorManifest, StylePresetItem } from '@/builder';
import { canvasStyleSheet } from '@/builder/canvas-styles';
import { applyManifest } from '@/builder/core';
import { stylePresets } from '@/builder/presets';
import { resetBuilder, setupBuilder } from '@/builder/setup';
import {
    focalCss,
    gradientCss,
    gradientParts,
    gradientText,
    imageCss,
    zIndexCss,
} from '@/builder/style-values';
import { isValidStyleValue, mergeDesign } from '@/builder/styles';
import { BlockInspector } from '@/components/verso/editor/block-inspector';
import type { Block } from '@/types';
import { pickOption } from '@/tests/pick-option';

const card: StylePresetItem = {
    key: 'card',
    label: 'Card',
    design: { border: { radius: '12px' }, color: { background: '#f7f3ec' } },
    source: 'core',
    changed: false,
};

const manifest: EditorManifest = {
    version: 1,
    categories: [],
    styleProperties: [
        {
            path: 'color.gradient',
            kind: 'gradient',
            css: 'background-image',
            label: 'Gradient',
            group: 'color',
            control: 'gradient',
            options: [],
        },
        {
            path: 'background.image',
            kind: 'image',
            css: 'background-image',
            label: 'Picture',
            group: 'background',
            control: 'image',
            options: [],
        },
        {
            path: 'background.position',
            kind: 'focal',
            css: 'background-position',
            label: 'Focal point',
            group: 'background',
            control: 'focal',
            options: [],
        },
        {
            path: 'border.radius',
            kind: 'length',
            css: 'border-radius',
            label: 'Radius',
            group: 'border',
            control: 'length',
            options: [],
        },
        {
            path: 'position.zIndex',
            kind: 'zIndex',
            css: 'z-index',
            label: 'Stack order',
            group: 'position',
            control: 'number',
            options: [],
        },
    ],
    breakpoints: [{ key: 'base', maxWidth: null }],
    features: ['styles_panel'],
    allowedBlocks: null,
    limits: { colors: null, fonts: null, spacing: null },
    stylePresets: [card],
    canEditPresets: true,
};

const banner: Block = {
    id: 'b_banner',
    type: 'section',
    attrs: { preset: 'card', design: { border: { radius: '4px' } } },
    content: '',
    children: [],
};

beforeEach(() => {
    resetBuilder();
    setupBuilder({});
    builder.styleProperties.clear();
    applyManifest(builder, manifest);
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('style values', () => {
    it('reads and writes gradients the way the server does', () => {
        const text = gradientText({
            kind: 'linear',
            angle: 135,
            stops: [
                { color: '#1F6F5C', at: 0 },
                { color: 'var:color.accent', at: 100 },
            ],
        });

        expect(gradientCss(text)).toBe(
            'linear-gradient(135deg, #1f6f5c 0%, var(--verso-color-accent) 100%)',
        );
        expect(gradientParts(text)?.stops).toHaveLength(2);
        expect(gradientCss('linear-gradient(90deg, red, #fff)')).toBeNull();
        expect(isValidStyleValue('gradient', 'var:gradient.dawn')).toBe(true);
    });

    it('accepts only safe pictures, focal points and stack orders', () => {
        expect(imageCss('/storage/media/rye.webp')).toBe(
            'url("/storage/media/rye.webp")',
        );
        expect(imageCss('/storage/a.jpg") ; x')).toBeNull();
        expect(focalCss('10% 90%')).toBe('10% 90%');
        expect(focalCss('110% 90%')).toBeNull();
        expect(zIndexCss(5)).toBe('5');
        expect(zIndexCss(2.5)).toBeNull();
    });

    it('puts block styles on top of preset styles', () => {
        expect(
            mergeDesign(
                {
                    spacing: {
                        padding: { top: { base: '2rem', mobile: '1rem' } },
                    },
                },
                { spacing: { padding: { top: { base: '3rem' } } } },
            ),
        ).toEqual({
            spacing: { padding: { top: { base: '3rem', mobile: '1rem' } } },
        });
    });
});

describe('canvas presets', () => {
    it('shows the preset styles of a block in the canvas', () => {
        const css = canvasStyleSheet([banner], {
            options: {
                'border.radius': ['border-radius', 'length'],
                'color.background': ['background-color', 'color'],
            },
            device: 'base',
            customCssMax: 2000,
            presets: stylePresets.designs(),
        });

        expect(css).toContain('background-color:#f7f3ec');
        expect(css).toContain('border-radius:4px');
    });
});

describe('StylePresetsBar', () => {
    it('picks a preset for the block', async () => {
        const onChange = vi.fn();
        render(
            <BlockInspector
                block={{ ...banner, attrs: {} }}
                onChange={onChange}
                editor={manifest}
            />,
        );

        await pickOption('Style preset', card.label);

        expect(onChange).toHaveBeenLastCalledWith({
            ...banner,
            attrs: { preset: 'card' },
        });
    });

    it('saves the block styles as a new preset and uses it', async () => {
        const saved = {
            ...card,
            key: 'harbor-card',
            label: 'Harbor card',
            source: 'saved',
        };
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ preset: saved, presets: [card, saved] }),
        });
        vi.stubGlobal('fetch', fetchMock);
        const onChange = vi.fn();
        render(
            <BlockInspector
                block={banner}
                onChange={onChange}
                editor={manifest}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Save as new preset' }),
        );
        fireEvent.change(screen.getByLabelText('Preset name'), {
            target: { value: 'Harbor card' },
        });
        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        });

        await waitFor(() =>
            expect(onChange).toHaveBeenLastCalledWith({
                ...banner,
                attrs: { preset: 'harbor-card' },
            }),
        );
        const body = JSON.parse(fetchMock.mock.calls[0][1].body as string);
        expect(body.design).toEqual({
            border: { radius: '4px' },
            color: { background: '#f7f3ec' },
        });
        expect(stylePresets.find('harbor-card')?.label).toBe('Harbor card');
    });

    it('hides the save buttons for people who may not edit presets', () => {
        applyManifest(builder, { ...manifest, canEditPresets: false });
        render(
            <BlockInspector
                block={banner}
                onChange={vi.fn()}
                editor={manifest}
            />,
        );

        expect(
            screen.queryByRole('button', { name: 'Save as new preset' }),
        ).not.toBeInTheDocument();
    });
});

describe('new style controls', () => {
    it('adds a gradient from the Colors group', () => {
        const onChange = vi.fn();
        render(
            <BlockInspector
                block={{ ...banner, attrs: {} }}
                onChange={onChange}
                editor={manifest}
            />,
        );

        fireEvent.click(screen.getByText('Colors'));
        fireEvent.click(screen.getByRole('button', { name: 'Add a gradient' }));

        expect(onChange).toHaveBeenLastCalledWith({
            ...banner,
            attrs: {
                design: {
                    color: {
                        gradient:
                            'linear-gradient(180deg, #00000000 0%, #000000b3 100%)',
                    },
                },
            },
        });
    });

    it('types a focal point in percents', () => {
        const onChange = vi.fn();
        render(
            <BlockInspector
                block={{ ...banner, attrs: {} }}
                onChange={onChange}
                editor={manifest}
            />,
        );

        fireEvent.click(screen.getByText('Background picture'));
        fireEvent.change(screen.getByLabelText('From the top (%)'), {
            target: { value: '20' },
        });

        expect(onChange).toHaveBeenLastCalledWith({
            ...banner,
            attrs: { design: { background: { position: '50% 20%' } } },
        });
    });
});
