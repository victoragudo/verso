import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { EditorManifest } from '@/builder';
import { editorDevice } from '@/builder/device';
import { resetBuilder } from '@/builder/setup';
import {
    CONTENT_WIDTH_KEY,
    resetContentWidth,
} from '@/components/verso/content/content-width';
import { EditorLayout } from '@/components/verso/content/editor-layout';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import type { Block, PluginBlockDefinition } from '@/types';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const manifest: EditorManifest = {
    version: 1,
    categories: [],
    styleProperties: [
        {
            path: 'spacing.padding.top',
            css: 'padding-top',
            kind: 'length',
            label: 'Padding top',
            group: 'spacing',
            control: 'length',
            options: [],
        },
        {
            path: 'color.background',
            css: 'background-color',
            kind: 'color',
            label: 'Background',
            group: 'color',
            control: 'color',
            options: [],
        },
    ],
    breakpoints: [
        { key: 'base', maxWidth: null },
        { key: 'tablet', maxWidth: 1024 },
        { key: 'mobile', maxWidth: 640 },
    ],
    features: ['add_panel', 'list_view'],
    allowedBlocks: null,
    customCssMax: 2000,
    limits: { colors: null, fonts: null, spacing: null },
};

const sectionBlock: PluginBlockDefinition[] = [
    {
        name: 'section',
        label: 'Section',
        description: 'A box.',
        fields: [],
        children: true,
    },
];

function line(id: string, content: string): Block {
    return {
        id,
        type: 'paragraph',
        attrs: {},
        content: `<p>${content}</p>`,
        children: [],
    };
}

// Opening hours of a bakery with a styled hero section on top.
const start: Block[] = [
    {
        id: 'b_hero',
        type: 'section',
        attrs: {
            design: {
                spacing: { padding: { top: { base: '6rem', mobile: '2rem' } } },
                color: { background: '#F4EFE6' },
            },
        },
        children: [line('b_welcome', 'Welcome to Harbor Bakery')],
    },
    line('b_mon', 'Monday to Friday: 7:00 to 19:00'),
    line('b_sat', 'Saturday: 8:00 to 14:00'),
    line('b_sun', 'Sunday: closed'),
];

let latest: Block[] = [];

function Harness({ initial = start }: { initial?: Block[] }) {
    const [blocks, setBlocks] = useState(initial);
    latest = blocks;

    return (
        <BlockEditor
            value={blocks}
            onChange={setBlocks}
            editor={manifest}
            pluginBlocks={sectionBlock}
        />
    );
}

function row(id: string): HTMLElement {
    return document.querySelector<HTMLElement>(`[data-block-id="${id}"]`)!;
}

function handle(id: string): HTMLElement {
    return row(id).querySelector<HTMLElement>('[data-drag-handle]')!;
}

function canvasCss(): string {
    return (
        document.querySelector('style[data-verso-canvas-styles]')
            ?.textContent ?? ''
    );
}

function mockWideScreen(wide: boolean) {
    vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => ({
        matches: wide && query.includes('min-width'),
        media: query,
        onchange: null,
        addListener: () => {},
        removeListener: () => {},
        addEventListener: () => {},
        removeEventListener: () => {},
        dispatchEvent: () => false,
    }));
}

beforeEach(() => {
    vi.restoreAllMocks();
    resetBuilder();
    resetContentWidth();
    window.localStorage.clear();
    latest = [];
});

describe('live styles in the canvas', () => {
    it('prints the block styles of the computer size and tags the block', () => {
        render(<Harness />);

        expect(canvasCss()).toBe(
            '.vs-e-b_hero{padding-top:6rem;background-color:#f4efe6}',
        );
        expect(row('b_hero').querySelector('.vs-e-b_hero')).not.toBeNull();
    });

    it('previews the phone values and the phone width when the device changes', () => {
        render(<Harness />);

        act(() => editorDevice.set('mobile'));

        expect(canvasCss()).toContain('padding-top:2rem');
        expect(
            document.querySelector<HTMLElement>('[data-device="mobile"]')?.style
                .maxWidth,
        ).toBe('640px');
    });

    it('updates the styles as soon as a value changes', () => {
        const { rerender } = render(
            <BlockEditor
                value={start}
                onChange={() => {}}
                editor={manifest}
                pluginBlocks={sectionBlock}
            />,
        );
        const changed = [
            {
                ...start[0],
                attrs: { design: { color: { background: '#14532D' } } },
            },
            ...start.slice(1),
        ];

        rerender(
            <BlockEditor
                value={changed}
                onChange={() => {}}
                editor={manifest}
                pluginBlocks={sectionBlock}
            />,
        );

        expect(canvasCss()).toBe('.vs-e-b_hero{background-color:#14532d}');
    });
});

describe('picking several blocks', () => {
    it('selects a range with Shift+click and announces the count', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        render(<Harness />);

        fireEvent.pointerDown(handle('b_mon'));
        fireEvent.pointerDown(handle('b_sun'), { shiftKey: true });

        expect(screen.getByText('3 blocks selected')).toBeInTheDocument();
        expect(row('b_sat')).toHaveAttribute('data-selected');

        await act(async () => vi.advanceTimersByTime(200));

        expect(
            document.querySelector('[aria-live="polite"]'),
        ).toHaveTextContent('3 blocks selected.');
        vi.useRealTimers();
    });

    it('toggles blocks with Cmd or Ctrl+click', () => {
        render(<Harness />);

        fireEvent.pointerDown(handle('b_mon'));
        fireEvent.pointerDown(handle('b_sun'), { metaKey: true });
        fireEvent.pointerDown(handle('b_hero'), { ctrlKey: true });

        expect(screen.getByText('3 blocks selected')).toBeInTheDocument();
        expect(row('b_sat')).not.toHaveAttribute('data-selected');
    });

    it('extends the selection with Shift+ArrowDown from a drag handle', () => {
        render(<Harness />);

        fireEvent.pointerDown(handle('b_mon'));
        fireEvent.keyDown(handle('b_mon'), {
            key: 'ArrowDown',
            shiftKey: true,
        });
        fireEvent.keyDown(handle('b_mon'), {
            key: 'ArrowDown',
            shiftKey: true,
        });

        expect(screen.getByText('3 blocks selected')).toBeInTheDocument();
    });

    it('groups picked blocks into a section', async () => {
        render(<Harness />);

        fireEvent.pointerDown(handle('b_mon'));
        fireEvent.pointerDown(handle('b_sun'), { shiftKey: true });
        await userEvent.click(
            screen.getByRole('button', { name: 'Group in a section' }),
        );

        expect(latest.map((block) => block.type)).toEqual([
            'section',
            'section',
        ]);
        expect(latest[1].children?.map((block) => block.id)).toEqual([
            'b_mon',
            'b_sat',
            'b_sun',
        ]);
        expect(
            document.querySelector('[data-test="selection-bar"]'),
        ).toBeNull();
    });

    it('moves, duplicates and deletes picked blocks together', async () => {
        render(<Harness />);

        fireEvent.pointerDown(handle('b_sat'));
        fireEvent.pointerDown(handle('b_sun'), { shiftKey: true });
        await userEvent.click(screen.getByRole('button', { name: 'Move up' }));

        expect(latest.map((block) => block.id)).toEqual([
            'b_hero',
            'b_sat',
            'b_sun',
            'b_mon',
        ]);

        await userEvent.click(
            screen.getByRole('button', { name: 'Duplicate' }),
        );

        expect(latest).toHaveLength(6);

        await userEvent.click(screen.getByRole('button', { name: 'Delete' }));

        expect(latest.map((block) => block.id)).toEqual([
            'b_hero',
            'b_sat',
            'b_sun',
            'b_mon',
        ]);
    });

    it('clears the selection with Escape', () => {
        render(<Harness />);

        fireEvent.pointerDown(handle('b_mon'));
        fireEvent.pointerDown(handle('b_sat'), { shiftKey: true });
        fireEvent.keyDown(handle('b_sat'), { key: 'Escape' });

        expect(screen.queryByText('2 blocks selected')).toBeNull();
    });

    it('shows the picked blocks in the outline', async () => {
        render(<Harness />);

        fireEvent.pointerDown(handle('b_mon'));
        fireEvent.pointerDown(handle('b_sat'), { shiftKey: true });
        await userEvent.click(screen.getByRole('button', { name: 'Outline' }));

        const current = screen
            .getAllByRole('button')
            .filter((button) => button.getAttribute('aria-current') === 'true');

        expect(current).toHaveLength(2);
    });
});

describe('side panel placement', () => {
    function Layout() {
        const [blocks, setBlocks] = useState(start);

        return (
            <EditorLayout
                backHref="/admin/entries/page"
                backLabel="Back to pages"
                status={null}
                actions={null}
                menu={null}
                sidebar={null}
                sidebarOpen={false}
                wide
                onSidebarOpenChange={() => {}}
            >
                <BlockEditor
                    value={blocks}
                    onChange={setBlocks}
                    editor={manifest}
                    pluginBlocks={sectionBlock}
                />
            </EditorLayout>
        );
    }

    it('opens Add blocks as a column next to the canvas on wide screens', async () => {
        mockWideScreen(true);
        render(<Layout />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Add blocks' }),
        );

        const slot = document.querySelector('[data-test="editor-panel-slot"]');
        const panel = screen.getByRole('complementary', { name: 'Add blocks' });

        expect(slot?.contains(panel)).toBe(true);
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('opens the Outline as a sheet with a backdrop on small screens', async () => {
        mockWideScreen(false);
        render(<Layout />);

        await userEvent.click(screen.getByRole('button', { name: 'Outline' }));

        expect(
            screen.getByRole('dialog', { name: 'Outline' }),
        ).toBeInTheDocument();
        expect(
            document.querySelector('[data-test="editor-panel-slot"]')?.children,
        ).toHaveLength(0);
    });

    it('remembers the content width choice and survives blocked storage', async () => {
        render(<Layout />);
        const main = document.getElementById('editor-canvas')!;

        expect(main).toHaveAttribute('data-content-width', 'full');

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(
            screen.getByRole('menuitemradio', { name: 'Reading width' }),
        );

        expect(main).toHaveAttribute('data-content-width', 'reading');
        expect(window.localStorage.getItem(CONTENT_WIDTH_KEY)).toBe('reading');

        resetContentWidth();
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('Storage is blocked');
        });

        expect(() => render(<Layout />)).not.toThrow();
    });
});
