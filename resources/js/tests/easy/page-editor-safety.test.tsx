import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeAll,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';
import { builder } from '@/builder';
import type { FrameBridgeOptions } from '@/lib/easy/frame-bridge';
import { readDeviceDraft, writeDeviceDraft } from '@/lib/easy/page-drafts';
import PageEditor from '@/pages/admin/easy/page-editor';
import type { PageEditorEntry } from '@/pages/admin/easy/page-editor';
import { router } from '@/tests/mocks/inertia';
import type { ServerDraft } from '@/lib/easy/page-requests';
import type { Block, SitePreviewWidths } from '@/types';
import { bakeryBlocks, coreSections } from './page-fixtures';

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
    onUndo: () => void;
    onRedo: () => void;
    onSave?: () => void;
};
let frame: FrameProps;

vi.mock('@/components/verso/easy/editor-frame', () => ({
    EditorFrame: (props: FrameProps) => {
        frame = props;

        return <iframe title="page" data-test="page-frame" />;
    },
}));

vi.mock('@/components/verso/media/media-picker', () => ({
    MediaPicker: () => null,
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
    isFrontPage: false,
    updatedAt: '2026-11-18T09:00:00Z',
};

const props: {
    entry: PageEditorEntry;
    blocks: Block[];
    draft?: ServerDraft | null;
    sections: typeof coreSections;
    previewWidths: SitePreviewWidths;
    renderDelay: number;
    undoSteps: number;
    undoSeconds: number;
    autosaveDelay: number;
} = {
    entry,
    blocks: bakeryBlocks(),
    sections: coreSections,
    previewWidths: { desktop: null, tablet: 768, phone: 390 },
    renderDelay: 0,
    undoSteps: 50,
    undoSeconds: 8,
    autosaveDelay: 60_000,
};

const withHeading = (text: string): Block[] => {
    const blocks = bakeryBlocks();
    blocks[0].children![0].content = text;

    return blocks;
};

const fetchMock = vi.fn();
const ids = () => frame.bridge.sections.map((section) => section.id);
const heading = () => frame.html?.match(/<h>(.*?)<\/h>/)?.[1];

async function renderEditor(overrides: Partial<typeof props> = {}) {
    render(<PageEditor {...props} {...overrides} />);
    await waitFor(() => expect(frame.html).not.toBeNull());
}

// jsdom has no modal dialogs, so showModal and close only toggle the open attribute.
beforeAll(() => {
    HTMLDialogElement.prototype.showModal = function showModal(
        this: HTMLDialogElement,
    ) {
        this.setAttribute('open', '');
    };
    HTMLDialogElement.prototype.close = function close(
        this: HTMLDialogElement,
    ) {
        this.removeAttribute('open');
        this.dispatchEvent(new Event('close'));
    };
});

beforeEach(() => {
    window.localStorage.clear();
    toast.mockClear();
    vi.mocked(router.put).mockReset();
    vi.mocked(router.put).mockImplementation((_url, _data, options) =>
        options?.onFinish?.({} as never),
    );
    fetchMock.mockReset();
    fetchMock.mockImplementation(async (url: string, init?: RequestInit) => {
        if (url.endsWith('/draft')) {
            return init?.method === 'DELETE'
                ? new Response('{"cleared":true}', { status: 200 })
                : new Response(
                      JSON.stringify({
                          ...JSON.parse(String(init?.body)),
                          savedAt: '2026-11-18T10:00:00Z',
                      }),
                      { status: 200 },
                  );
        }

        if (url.endsWith('/versions')) {
            return new Response(
                JSON.stringify({
                    versions: [
                        {
                            id: 41,
                            savedAt: '2026-11-17T16:45:00Z',
                            author: 'Marta Oliveira',
                            title: 'Harbor Lane Bakery',
                            summary: '1 section',
                            blocks: [bakeryBlocks()[0]],
                        },
                    ],
                }),
                { status: 200 },
            );
        }

        const { blocks } = JSON.parse(String(init?.body)) as {
            blocks: Block[];
        };

        return new Response(
            `<main><h>${blocks[0]?.children?.[0]?.content ?? ''}</h>${blocks.map((block) => block.id).join(',')}</main>`,
            { status: 200 },
        );
    });
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    vi.unstubAllGlobals();
    builder.pageEditorButtons.clear();
    builder.pageSectionActions.clear();
});

describe('page editor safety nets', () => {
    it('offers unsaved changes from an earlier visit and keeps editing them', async () => {
        const user = userEvent.setup();
        await renderEditor({
            draft: {
                blocks: withHeading('Sourdough every Saturday'),
                savedAt: '2026-11-18T10:00:00Z',
            },
        });

        const banner = screen.getByRole('region', { name: 'Unsaved changes' });
        expect(banner).toHaveTextContent('You have unsaved changes from');

        await user.click(
            within(banner).getByRole('button', { name: 'Keep editing them' }),
        );
        await waitFor(() => expect(heading()).toBe('Sourdough every Saturday'));
        expect(
            screen.queryByRole('region', { name: 'Unsaved changes' }),
        ).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Undo' }));
        await waitFor(() =>
            expect(heading()).toBe('Fresh bread on Harbour Lane'),
        );
    });

    it('throws unsaved changes away on the server and on this device', async () => {
        const user = userEvent.setup();
        writeDeviceDraft(
            12,
            withHeading('Closed on Monday'),
            '2026-11-18T11:00:00Z',
        );
        await renderEditor();

        await user.click(
            screen.getByRole('button', { name: 'Throw them away' }),
        );

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/easy/pages/12/draft',
            expect.objectContaining({ method: 'DELETE' }),
        );
        expect(readDeviceDraft(12)).toBeNull();
        expect(heading()).toBe('Fresh bread on Harbour Lane');
    });

    it('saves changes as a draft on its own and says so', async () => {
        await renderEditor({ autosaveDelay: 5 });

        act(() =>
            frame.bridge.onText('b_title001', 'content', 'Rye on Sundays'),
        );

        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith(
                '/admin/easy/pages/12/draft',
                expect.objectContaining({ method: 'PUT' }),
            ),
        );
        await waitFor(() =>
            expect(
                document.querySelector('[data-test="save-state"]'),
            ).toHaveTextContent('All changes saved as a draft'),
        );
        expect(
            document.querySelector('[data-test="autosave-status"]'),
        ).toHaveTextContent('All changes saved as a draft');
        expect(
            document.querySelector('[data-test="save-state-phone"]'),
        ).toHaveTextContent('All changes saved as a draft');
    });

    it('undoes, redoes and saves with keys pressed inside the frame', async () => {
        await renderEditor();

        act(() => frame.bridge.onSectionAction('b_hero0001', 'duplicate'));
        await waitFor(() => expect(ids()).toHaveLength(4));

        act(() => frame.onUndo());
        await waitFor(() => expect(ids()).toHaveLength(3));
        act(() => frame.onRedo());
        await waitFor(() => expect(ids()).toHaveLength(4));

        act(() => frame.onSave?.());
        expect(router.put).toHaveBeenCalledWith(
            '/admin/easy/pages/12',
            expect.objectContaining({ publish: false }),
            expect.anything(),
        );
    });

    it('starts over from the last saved version after asking, and that can be undone', async () => {
        const user = userEvent.setup();
        await renderEditor();

        act(() =>
            frame.bridge.onText('b_title001', 'content', 'Rye on Sundays'),
        );
        await waitFor(() => expect(heading()).toBe('Rye on Sundays'));

        await user.click(screen.getByRole('button', { name: 'More actions' }));
        await user.click(
            await screen.findByRole('menuitem', { name: 'Start over' }),
        );
        const dialog = screen.getByRole('dialog', { name: 'Start over?' });
        await user.click(
            within(dialog).getByRole('button', { name: 'Start over' }),
        );
        await waitFor(() =>
            expect(heading()).toBe('Fresh bread on Harbour Lane'),
        );

        await user.click(screen.getByRole('button', { name: 'Undo' }));
        await waitFor(() => expect(heading()).toBe('Rye on Sundays'));
    });

    it('restores an earlier version into the editor as one undo step', async () => {
        const user = userEvent.setup();
        await renderEditor();

        await user.click(screen.getByRole('button', { name: 'More actions' }));
        await user.click(
            await screen.findByRole('menuitem', { name: 'Restore this page' }),
        );
        const dialog = screen.getByRole('dialog', {
            name: 'Restore this page',
        });

        expect(
            await within(dialog).findByText('Marta Oliveira, 1 section'),
        ).toBeInTheDocument();
        await waitFor(() =>
            expect(
                dialog.querySelector('[data-test="restore-preview"]'),
            ).not.toBeNull(),
        );

        await user.click(
            within(dialog).getByRole('button', {
                name: 'Restore this version',
            }),
        );
        await waitFor(() => expect(ids()).toEqual(['b_hero0001']));
        expect(toast).toHaveBeenCalledWith(
            expect.stringContaining('Press Save to keep it.'),
        );

        await user.click(screen.getByRole('button', { name: 'Undo' }));
        await waitFor(() => expect(ids()).toHaveLength(3));
    });

    it('asks before publishing an empty page', async () => {
        const user = userEvent.setup();
        await renderEditor({ blocks: [] });

        await user.click(screen.getByRole('button', { name: 'Publish' }));
        expect(router.put).not.toHaveBeenCalled();

        const dialog = screen.getByRole('dialog', {
            name: 'Publish an empty page?',
        });
        await user.click(
            within(dialog).getByRole('button', { name: 'Publish anyway' }),
        );
        expect(router.put).toHaveBeenCalledWith(
            '/admin/easy/pages/12',
            { blocks: [], publish: true, confirmEmpty: true },
            expect.anything(),
        );
    });

    it('warns before the last section of the home page is deleted', async () => {
        const user = userEvent.setup();
        await renderEditor({
            entry: { ...entry, isFrontPage: true },
            blocks: [bakeryBlocks()[0]],
        });

        act(() => frame.bridge.onSectionAction('b_hero0001', 'delete'));
        const dialog = screen.getByRole('dialog', {
            name: 'Empty your home page?',
        });
        expect(ids()).toEqual(['b_hero0001']);

        await user.click(
            within(dialog).getByRole('button', { name: 'Delete the section' }),
        );
        await waitFor(() => expect(ids()).toEqual([]));
    });

    it('shows a plain message when the session ended and keeps the work on this device', async () => {
        const user = userEvent.setup();
        vi.mocked(router.put).mockImplementationOnce((_url, _data, options) => {
            (
                options as { onHttpException?: (r: { status: number }) => void }
            ).onHttpException?.({ status: 419 });
            options?.onFinish?.({} as never);
        });
        await renderEditor();

        act(() =>
            frame.bridge.onText('b_title001', 'content', 'Rye on Sundays'),
        );
        await waitFor(() => expect(heading()).toBe('Rye on Sundays'));
        await user.click(screen.getByRole('button', { name: 'Save draft' }));

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Your session ended. Sign in again in a new tab, then come back here and press Save.',
        );
        expect(readDeviceDraft(12)?.blocks[0].children?.[0]?.content).toBe(
            'Rye on Sundays',
        );
    });

    it('labels the preview with the device and its width', async () => {
        const user = userEvent.setup();
        await renderEditor();

        expect(
            document.querySelector('[data-test="device-label"]'),
        ).toHaveTextContent('Computer, full width');
        await user.click(screen.getByRole('radio', { name: 'Phone' }));
        expect(
            document.querySelector('[data-test="device-label"]'),
        ).toHaveTextContent('Phone, 390 pixels wide');
        expect(frame.width).toBe(390);
    });
});
