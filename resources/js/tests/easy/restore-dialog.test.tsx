import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { RestoreDialog } from '@/components/verso/easy/restore-dialog';
import { bakeryBlocks } from './page-fixtures';

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

afterEach(() => vi.unstubAllGlobals());

const versions = [
    {
        id: 52,
        savedAt: '2026-11-18T09:30:00Z',
        author: 'Marta Oliveira',
        title: 'Harbor Lane Bakery',
        summary: '3 sections',
        blocks: bakeryBlocks(),
    },
    {
        id: 41,
        savedAt: '2026-11-17T16:45:00Z',
        author: 'Rui Almeida',
        title: 'Harbor Lane Bakery',
        summary: '1 section',
        blocks: [bakeryBlocks()[0]],
    },
];

function stubFetch(versionsResponse: Response) {
    const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
        if (url.endsWith('/versions')) {
            return versionsResponse;
        }

        const { blocks } = JSON.parse(init?.body as string) as {
            blocks: unknown[];
        };

        return new Response(`<main>${blocks.length} sections</main>`);
    });
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

function renderDialog(onRestore = vi.fn()) {
    render(
        <RestoreDialog
            open
            versionsUrl="/admin/easy/pages/12/versions"
            frameUrl="/admin/easy/pages/12/frame"
            onRestore={onRestore}
            onClose={vi.fn()}
        />,
    );

    return onRestore;
}

describe('Restore this page', () => {
    it('lists earlier versions, previews the picked one and restores it', async () => {
        const fetchMock = stubFetch(new Response(JSON.stringify({ versions })));
        const onRestore = renderDialog();

        expect(
            await screen.findByText('Marta Oliveira, 3 sections'),
        ).toBeInTheDocument();
        await waitFor(() =>
            expect(
                document
                    .querySelector('[data-test="restore-preview"]')
                    ?.getAttribute('srcdoc'),
            ).toBe('<main>3 sections</main>'),
        );

        await userEvent.click(
            screen.getByRole('radio', { name: /Rui Almeida, 1 section/ }),
        );
        await waitFor(() =>
            expect(
                document
                    .querySelector('[data-test="restore-preview"]')
                    ?.getAttribute('srcdoc'),
            ).toBe('<main>1 sections</main>'),
        );
        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/easy/pages/12/frame',
            expect.objectContaining({ method: 'POST' }),
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Restore this version' }),
        );
        expect(onRestore).toHaveBeenCalledWith(versions[1]);
    });

    it('says when there are no earlier versions', async () => {
        stubFetch(new Response(JSON.stringify({ versions: [] })));
        renderDialog();

        expect(
            await screen.findByText(/There are no earlier versions yet/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Restore this version' }),
        ).toBeDisabled();
    });

    it('shows a plain message when the versions cannot be loaded', async () => {
        stubFetch(new Response('', { status: 419 }));
        renderDialog();

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Your session ended. Sign in again in a new tab, then come back here and press Restore this page.',
        );
    });
});
