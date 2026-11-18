import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { HelpPanel } from '@/components/verso/easy/help-panel';
import { clearHelpTopics } from '@/lib/easy/help-topics';
import { resetPageProps, setPageProps } from '@/tests/mocks/inertia';
import { helpTopics } from './help-fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

function serveTopics() {
    const fetchMock = vi
        .fn()
        .mockResolvedValue(
            new Response(JSON.stringify({ topics: helpTopics })),
        );
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

beforeEach(() => {
    clearHelpTopics();
    setPageProps({ routeName: 'admin.site.menus.index', simpleMode: true });
});

afterEach(() => {
    resetPageProps();
    vi.unstubAllGlobals();
});

async function topicItems(): Promise<HTMLElement[]> {
    const list = await screen.findByRole('list', { name: 'Help topics' });

    return Array.from(list.querySelectorAll<HTMLElement>(':scope > li'));
}

async function topicNames() {
    return (await topicItems()).map(
        (item) => item.querySelector('summary span span')?.textContent,
    );
}

describe('HelpPanel', () => {
    it('opens on the end side, which the sheet mirrors in RTL', () => {
        serveTopics();
        render(<HelpPanel open onOpenChange={() => undefined} />);

        expect(screen.getByRole('dialog', { name: 'Help' })).toHaveAttribute(
            'data-side',
            'right',
        );
    });

    it('loads the topics when it opens and shows the ones for this page first', async () => {
        const fetchMock = serveTopics();
        const { rerender } = render(
            <HelpPanel open={false} onOpenChange={() => undefined} />,
        );

        expect(fetchMock).not.toHaveBeenCalled();

        rerender(<HelpPanel open onOpenChange={() => undefined} />);

        expect(
            screen.getByRole('dialog', { name: 'Help' }),
        ).toBeInTheDocument();
        expect(await topicNames()).toEqual([
            'Change the menu',
            'Choose a ready made site',
            'Change a page',
            'Write a blog post',
            'Upload photos',
        ]);

        const [first] = await topicItems();
        expect(within(first).getByText('This page')).toBeInTheDocument();
        expect(first.querySelector('details')).toHaveAttribute('open');
        expect(screen.getAllByText('This page')).toHaveLength(1);
    });

    it('shows numbered steps, a drawing and the video link of a topic', async () => {
        serveTopics();
        render(<HelpPanel open onOpenChange={() => undefined} />);

        const [first] = await topicItems();

        expect(
            within(first).getByText('Press "Save items".'),
        ).toBeInTheDocument();
        expect(
            first.querySelector('svg[data-illustration="menu"]'),
        ).not.toBeNull();
        expect(
            within(first).getByRole('link', {
                name: 'Watch a short video (opens in a new tab)',
            }),
        ).toHaveAttribute('href', 'https://videos.harborlane.example/menu.mp4');
    });

    it('draws a calm default picture for topics with an unknown drawing', async () => {
        serveTopics();
        render(<HelpPanel open onOpenChange={() => undefined} />);

        const items = await topicItems();

        expect(
            items[4].querySelector('svg[data-illustration="generic"]'),
        ).not.toBeNull();
    });

    it('searches the topics and tells screen reader users what it found', async () => {
        serveTopics();
        render(<HelpPanel open onOpenChange={() => undefined} />);
        await screen.findByRole('list', { name: 'Help topics' });

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search help' }),
            'fotos',
        );

        expect(await topicNames()).toEqual(['Upload photos']);
        expect(screen.getByText('1 topic found.')).toBeInTheDocument();

        await userEvent.clear(screen.getByRole('searchbox'));
        await userEvent.type(screen.getByRole('searchbox'), 'croissant');

        expect(screen.getByText(/Nothing found/)).toBeInTheDocument();
        expect(
            screen.getByText('No help found for “croissant”.'),
        ).toBeInTheDocument();
    });

    it('offers to try again when help cannot load', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(new Response('', { status: 503 }))
            .mockResolvedValue(
                new Response(JSON.stringify({ topics: helpTopics })),
            );
        vi.stubGlobal('fetch', fetchMock);
        render(<HelpPanel open onOpenChange={() => undefined} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Help could not be loaded.',
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Try again' }),
        );

        expect(await topicNames()).toHaveLength(5);
    });

    it('has the simple mode switch', async () => {
        serveTopics();
        render(<HelpPanel open onOpenChange={() => undefined} />);

        expect(
            screen.getByRole('switch', { name: 'Simple mode' }),
        ).toBeChecked();
    });
});
