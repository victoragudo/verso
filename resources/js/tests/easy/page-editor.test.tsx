import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { builder } from '@/builder';
import type { FrameBridgeOptions } from '@/lib/easy/frame-bridge';
import type { PageEditorContext } from '@/lib/easy/page-editor-registry';
import PageEditor from '@/pages/admin/easy/page-editor';
import type { PageEditorEntry } from '@/pages/admin/easy/page-editor';
import { router } from '@/tests/mocks/inertia';
import type { Block } from '@/types';
import { bakeryBlocks, coreSections, croissantPhoto } from './page-fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const toast = vi.fn();
vi.mock('sonner', () => ({ toast: (...args: unknown[]) => toast(...args) }));

type FrameProps = {
    html: string | null;
    width: number | null;
    bridge: Omit<FrameBridgeOptions, 'focus'>;
    takeFocus: () => unknown;
};
let frame: FrameProps;

vi.mock('@/components/verso/easy/editor-frame', () => ({
    EditorFrame: (props: FrameProps) => {
        frame = props;

        return (
            <iframe
                title="page"
                data-test="page-frame"
                style={{ width: props.width ? `${props.width}px` : '100%' }}
            />
        );
    },
}));

const pickedMedia = {
    ...croissantPhoto,
    filename: 'croissants.jpg',
    mime_type: 'image/jpeg',
    size: 2048,
    caption: null,
};
vi.mock('@/components/verso/media/media-picker', () => ({
    MediaPicker: ({
        open,
        onSelect,
    }: {
        open: boolean;
        onSelect: (media: typeof pickedMedia) => void;
    }) =>
        open ? (
            <button type="button" onClick={() => onSelect(pickedMedia)}>
                Pick croissants
            </button>
        ) : null,
}));

const entry: PageEditorEntry = {
    id: 12,
    title: 'Harbor Lane Bakery',
    type: 'page',
    typeLabel: 'Page',
    status: 'draft',
    url: null,
    backUrl: '/admin/entries/page',
    advancedUrl: '/admin/entries/page/12/edit',
    canPublish: true,
};

const props = {
    entry,
    blocks: bakeryBlocks(),
    sections: coreSections,
    previewWidths: { desktop: null, tablet: 768, phone: 390 },
    renderDelay: 0,
    undoSteps: 50,
    undoSeconds: 8,
    autosaveDelay: 60_000,
};

const fetchMock = vi.fn();

function drawnHtml(blocks: Block[]) {
    return `<main>${blocks.map((block) => block.id).join(',')}</main>`;
}

async function renderEditor(
    overrides: Partial<Parameters<typeof PageEditor>[0]> = {},
) {
    render(<PageEditor {...props} {...overrides} />);
    await waitFor(() => expect(frame.html).not.toBeNull());
}

const lastDrawnIds = () => frame.bridge.sections.map((section) => section.id);
const announcer = () => document.querySelector('[data-test="announcer"]')!;

beforeEach(() => {
    window.localStorage.clear();
    toast.mockClear();
    vi.mocked(router.put).mockReset();
    vi.mocked(router.put).mockImplementation((_url, _data, options) =>
        options?.onFinish?.({} as never),
    );
    fetchMock.mockReset();
    fetchMock.mockImplementation(async (url: string, init?: RequestInit) => {
        if (url.includes('/sections/')) {
            return new Response(
                JSON.stringify({
                    blocks: [
                        {
                            id: 'b_faq00001',
                            type: 'section',
                            attrs: { label: 'Questions' },
                            content: '',
                            children: [],
                        },
                    ],
                }),
                { status: 200 },
            );
        }

        const { blocks } = JSON.parse(String(init?.body)) as {
            blocks: Block[];
        };

        return new Response(drawnHtml(blocks), { status: 200 });
    });
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    vi.unstubAllGlobals();
    builder.pageEditorButtons.clear();
    builder.pageSectionActions.clear();
});

// The save state of the top bar and its phone copy under it.
const saveStates = () =>
    ['save-state', 'save-state-phone'].map(
        (name) =>
            document.querySelector(`[data-test="${name}"]`)?.textContent ?? '',
    );

describe('PageEditor', () => {
    it('draws the page from the blocks and shows the top bar', async () => {
        await renderEditor();

        expect(
            screen.getByRole('heading', { name: 'Harbor Lane Bakery' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Draft')).toBeInTheDocument();
        expect(saveStates()).toEqual([
            'All changes saved',
            'All changes saved',
        ]);
        expect(screen.getByRole('link', { name: 'Back' })).toHaveAttribute(
            'href',
            '/admin/entries/page',
        );
        expect(screen.getByRole('button', { name: 'Undo' })).toHaveAttribute(
            'aria-disabled',
            'true',
        );
        expect(
            screen.getByRole('button', { name: 'Publish' }),
        ).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/easy/pages/12/frame',
            expect.objectContaining({ method: 'POST' }),
        );
        expect(frame.html).toBe(drawnHtml(bakeryBlocks()));
        expect(frame.bridge.sections.map((section) => section.name)).toEqual([
            'Section 1: Fresh bread on Harbour Lane',
            'Section 2: What we bake',
            'Section 3: Gallery',
        ]);
    });

    it('moves a section, announces it and undoes it', async () => {
        const user = userEvent.setup();
        await renderEditor();

        act(() => frame.bridge.onSectionAction('b_serv0001', 'move-up'));
        await waitFor(() =>
            expect(lastDrawnIds()).toEqual([
                'b_serv0001',
                'b_hero0001',
                'b_gall0001',
            ]),
        );
        await waitFor(() =>
            expect(announcer()).toHaveTextContent('Section moved up'),
        );
        expect(frame.takeFocus()).toEqual({
            kind: 'action',
            sectionId: 'b_serv0001',
            action: 'move-up',
        });
        expect(saveStates()).toEqual(['Unsaved changes', 'Unsaved changes']);

        await user.click(screen.getByRole('button', { name: 'Undo' }));
        await waitFor(() =>
            expect(lastDrawnIds()).toEqual([
                'b_hero0001',
                'b_serv0001',
                'b_gall0001',
            ]),
        );
        expect(saveStates()).toEqual([
            'All changes saved',
            'All changes saved',
        ]);

        await user.click(screen.getByRole('button', { name: 'Redo' }));
        await waitFor(() => expect(lastDrawnIds()[0]).toBe('b_serv0001'));
    });

    it('warns about hard to read colors in a section and fixes them in one step', async () => {
        const colors = {
            'color.accent': '#2e5e4e',
            'color.background': '#f6f7f3',
            'color.text': '#1b2420',
        };
        const [hero, ...rest] = bakeryBlocks();
        const darkHero = {
            ...hero,
            attrs: {
                ...hero.attrs,
                design: { color: { background: 'var:color.accent' } },
            },
        };
        await renderEditor({ blocks: [darkHero, ...rest], colors });

        const drawnHero = () =>
            frame.bridge.sections.find(
                (section) => section.id === 'b_hero0001',
            );
        expect(drawnHero()?.warning).toBe(
            'Some text in this section is hard to read on its background.',
        );
        expect(
            frame.bridge.sections.filter((section) => section.warning),
        ).toHaveLength(1);

        act(() => frame.bridge.onSectionAction('b_hero0001', 'fix-colors'));
        await waitFor(() => expect(drawnHero()?.warning).toBeUndefined());
        await waitFor(() =>
            expect(announcer()).toHaveTextContent(
                'Colors fixed. The text in this section is easy to read now.',
            ),
        );
        expect(frame.takeFocus()).toEqual({
            kind: 'section',
            sectionId: 'b_hero0001',
        });

        const sent = JSON.parse(
            String(fetchMock.mock.calls.at(-1)?.[1]?.body),
        ) as { blocks: Block[] };
        expect(sent.blocks[0].attrs.design).toEqual({
            color: {
                background: 'var:color.accent',
                text: 'var:color.background',
            },
        });
    });

    it('undoes with the keyboard', async () => {
        const user = userEvent.setup();
        await renderEditor();

        act(() => frame.bridge.onSectionAction('b_hero0001', 'duplicate'));
        await waitFor(() => expect(lastDrawnIds()).toHaveLength(4));
        await user.keyboard('{Control>}z{/Control}');
        await waitFor(() => expect(lastDrawnIds()).toHaveLength(3));
    });

    it('hides a section and tells what it means', async () => {
        await renderEditor();

        act(() => frame.bridge.onSectionAction('b_gall0001', 'toggle-hidden'));
        await waitFor(() => expect(frame.bridge.sections[2].hidden).toBe(true));
        await waitFor(() =>
            expect(announcer()).toHaveTextContent(
                'Section hidden. Visitors will not see it.',
            ),
        );
    });

    it('deletes a section with an undo toast', async () => {
        await renderEditor();

        act(() => frame.bridge.onSectionAction('b_serv0001', 'delete'));
        await waitFor(() =>
            expect(lastDrawnIds()).toEqual(['b_hero0001', 'b_gall0001']),
        );
        expect(toast).toHaveBeenCalledWith(
            'Section deleted.',
            expect.objectContaining({ duration: 8000 }),
        );

        const options = toast.mock.calls[0][1] as {
            action: { label: string; onClick: () => void };
        };
        expect(options.action.label).toBe('Undo');
        act(() => options.action.onClick());
        await waitFor(() =>
            expect(lastDrawnIds()).toEqual([
                'b_hero0001',
                'b_serv0001',
                'b_gall0001',
            ]),
        );
    });

    it('changes text from the frame', async () => {
        await renderEditor();

        act(() =>
            frame.bridge.onText(
                'b_title001',
                'content',
                'Sourdough every morning',
            ),
        );
        await waitFor(() => {
            const body = JSON.parse(
                String(fetchMock.mock.calls.at(-1)?.[1]?.body),
            ) as { blocks: Block[] };
            expect(body.blocks[0].children?.[0].content).toBe(
                'Sourdough every morning',
            );
        });
    });

    it('adds a ready section where asked', async () => {
        const user = userEvent.setup();
        await renderEditor();

        act(() => frame.bridge.onAddSection(1));
        await user.click(
            await screen.findByRole('button', {
                name: /Questions and answers/,
            }),
        );

        await waitFor(() =>
            expect(lastDrawnIds()).toEqual([
                'b_hero0001',
                'b_faq00001',
                'b_serv0001',
                'b_gall0001',
            ]),
        );
        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/easy/sections/faq',
            expect.anything(),
        );
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(frame.takeFocus()).toEqual({
            kind: 'section',
            sectionId: 'b_faq00001',
        });
    });

    it('replaces a picture from the media library', async () => {
        const user = userEvent.setup();
        await renderEditor();

        act(() => frame.bridge.onImage({ blockId: 'b_image001' }));
        await user.click(
            await screen.findByRole('button', { name: 'Pick croissants' }),
        );

        await waitFor(() => {
            const body = JSON.parse(
                String(fetchMock.mock.calls.at(-1)?.[1]?.body),
            ) as { blocks: Block[] };
            expect(body.blocks[1].children?.[1].attrs).toMatchObject({
                mediaId: 21,
                src: '/storage/croissants.jpg',
            });
        });
        await waitFor(() =>
            expect(announcer()).toHaveTextContent('Picture replaced'),
        );
    });

    it('shows a plain message when the page cannot be drawn', async () => {
        fetchMock.mockResolvedValueOnce(
            new Response(
                JSON.stringify({
                    errors: { blocks: ['Block 2: the block type is unknown.'] },
                }),
                { status: 422 },
            ),
        );
        render(<PageEditor {...props} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Block 2: the block type is unknown.',
        );
    });

    it('saves a draft and publishes through the update route', async () => {
        const user = userEvent.setup();
        await renderEditor();

        await user.click(screen.getByRole('button', { name: 'Save draft' }));
        expect(router.put).toHaveBeenCalledWith(
            '/admin/easy/pages/12',
            { blocks: bakeryBlocks(), publish: false, confirmEmpty: false },
            expect.objectContaining({ preserveState: true }),
        );

        await user.click(screen.getByRole('button', { name: 'Publish' }));
        expect(router.put).toHaveBeenLastCalledWith(
            '/admin/easy/pages/12',
            { blocks: bakeryBlocks(), publish: true, confirmEmpty: false },
            expect.anything(),
        );
    });

    it('marks the page saved after a save and shows save errors', async () => {
        const user = userEvent.setup();
        vi.mocked(router.put).mockImplementationOnce((_url, _data, options) => {
            options?.onSuccess?.({} as never);
            options?.onFinish?.({} as never);
        });
        await renderEditor();

        act(() => frame.bridge.onSectionAction('b_serv0001', 'move-up'));
        await waitFor(() =>
            expect(saveStates()).toEqual([
                'Unsaved changes',
                'Unsaved changes',
            ]),
        );
        await user.click(screen.getByRole('button', { name: 'Save draft' }));
        expect(saveStates()).toEqual([
            'All changes saved',
            'All changes saved',
        ]);

        vi.mocked(router.put).mockImplementationOnce((_url, _data, options) => {
            options?.onError?.({
                blocks: 'Block 1: the id "b_x" is used twice.',
            });
            options?.onFinish?.({} as never);
        });
        await user.click(screen.getByRole('button', { name: 'Save draft' }));
        expect(screen.getByRole('alert')).toHaveTextContent(
            'Block 1: the id "b_x" is used twice.',
        );
    });

    it('offers "Save changes" only for a published page', async () => {
        await renderEditor({
            entry: { ...entry, status: 'published', url: '/', backUrl: '/' },
        });

        expect(
            screen.getByRole('button', { name: 'Save changes' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Publish' })).toBeNull();
        expect(
            screen.getByRole('link', { name: 'Back to site' }),
        ).toHaveAttribute('href', '/');
    });

    it('hides Publish from people who cannot publish', async () => {
        await renderEditor({ entry: { ...entry, canPublish: false } });

        expect(screen.queryByRole('button', { name: 'Publish' })).toBeNull();
    });

    it('switches the screen size of the frame', async () => {
        const user = userEvent.setup();
        await renderEditor();

        await user.click(screen.getByRole('radio', { name: 'Phone' }));
        expect(frame.width).toBe(390);
        await user.click(screen.getByRole('radio', { name: 'Tablet' }));
        expect(frame.width).toBe(768);
    });

    it('links to the advanced editor', async () => {
        const user = userEvent.setup();
        await renderEditor();

        await user.click(screen.getByRole('button', { name: 'More actions' }));
        expect(
            await screen.findByRole('menuitem', { name: 'Advanced editor' }),
        ).toHaveAttribute('href', '/admin/entries/page/12/edit');
    });

    it('shows top bar buttons and section actions from plugins', async () => {
        const user = userEvent.setup();
        const write = vi.fn();
        const rewrite = vi.fn();
        builder.pageEditorButtons.register({
            name: 'ai-write',
            label: 'Write with AI',
            run: write,
        });
        builder.pageSectionActions.register({
            name: 'ai-rewrite',
            label: 'Rewrite',
            shows: (section) => section.id !== 'b_gall0001',
            run: rewrite,
        });
        await renderEditor();

        await user.click(screen.getByRole('button', { name: 'Write with AI' }));
        expect(write).toHaveBeenCalledWith(
            expect.objectContaining({
                entry: { id: 12, title: 'Harbor Lane Bakery', type: 'page' },
                blocks: bakeryBlocks(),
            }),
        );

        expect(frame.bridge.actions.map((item) => item.name)).toContain(
            'ai-rewrite',
        );
        expect(frame.bridge.sections[2].skip).toEqual(['ai-rewrite']);

        act(() => frame.bridge.onSectionAction('b_hero0001', 'ai-rewrite'));
        const context = rewrite.mock.calls[0][0] as PageEditorContext & {
            section: Block;
        };
        expect(context.section.id).toBe('b_hero0001');

        act(() => context.change([], 'Page cleared'));
        await waitFor(() => expect(frame.bridge.sections).toEqual([]));
    });
    it('shows buttons and section actions that a plugin adds after the editor opened', async () => {
        await renderEditor();

        expect(
            screen.queryByRole('button', { name: 'AI helper' }),
        ).not.toBeInTheDocument();

        let removeButton = () => {};
        act(() => {
            removeButton = builder.pageEditorButtons.register({
                name: 'ai-helper',
                label: 'AI helper',
                run: vi.fn(),
            });
            builder.pageSectionActions.register({
                name: 'ai-section-text',
                label: 'Write this section with AI',
                run: vi.fn(),
            });
        });

        expect(
            screen.getByRole('button', { name: 'AI helper' }),
        ).toBeInTheDocument();
        expect(frame.bridge.actions.map((item) => item.name)).toContain(
            'ai-section-text',
        );

        act(() => removeButton());

        expect(
            screen.queryByRole('button', { name: 'AI helper' }),
        ).not.toBeInTheDocument();
    });
});
