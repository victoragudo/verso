import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import GuidedStart from '@/pages/admin/easy/start';
import { router, setPageProps } from '@/tests/mocks/inertia';
import { blog, restaurant, shop } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

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
    setPageProps({ errors: {} });
    router.post.mockClear();
});

const starters = [blog, restaurant, shop];

describe('Guided start page', () => {
    it('asks what the site is for and lists every starter as a choice', () => {
        render(
            <GuidedStart starters={starters} current={null} hasPages={false} />,
        );

        expect(
            screen.getByRole('heading', { name: 'What is your site for?' }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('radio')).toHaveLength(3);
        expect(
            screen.getByRole('button', { name: 'Build my site' }),
        ).toBeDisabled();
        expect(
            screen.getByText('Pick one kind of site above.'),
        ).toBeInTheDocument();
    });

    it('builds the chosen site right away on an empty site', async () => {
        render(
            <GuidedStart starters={starters} current={null} hasPages={false} />,
        );

        await userEvent.click(
            screen.getByRole('radio', { name: 'Restaurant or cafe' }),
        );

        expect(
            screen.getByText('You picked: Restaurant or cafe.'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Build my site' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/easy/start',
            { starter: 'restaurant' },
            expect.any(Object),
        );
    });

    it('asks first when the site already has pages', async () => {
        render(<GuidedStart starters={starters} current={null} hasPages />);

        await userEvent.click(
            screen.getByRole('radio', { name: 'Online shop' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Build my site' }),
        );

        const dialog = screen.getByRole('dialog', {
            name: 'Add the Online shop site?',
        });

        expect(dialog).toHaveAccessibleDescription(
            'This adds new pages and replaces your main menu. Your existing pages stay.',
        );
        expect(router.post).not.toHaveBeenCalled();

        await userEvent.click(
            screen.getByRole('button', { name: 'Add the pages' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/easy/start',
            { starter: 'shop' },
            expect.any(Object),
        );
        expect(dialog).not.toHaveAttribute('open');
    });

    it('keeps the site as it is when the question is cancelled', async () => {
        render(<GuidedStart starters={starters} current="blog" hasPages />);

        expect(screen.getByRole('radio', { name: 'Blog' })).toBeChecked();

        await userEvent.click(
            screen.getByRole('button', { name: 'Build my site' }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(router.post).not.toHaveBeenCalled();
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('announces the work while the site is built', async () => {
        router.post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options: { onStart?: () => void },
            ) => {
                options.onStart?.();
            },
        );
        render(
            <GuidedStart starters={starters} current={null} hasPages={false} />,
        );

        await userEvent.click(screen.getByRole('radio', { name: 'Blog' }));
        await act(async () => {
            await userEvent.click(
                screen.getByRole('button', { name: 'Build my site' }),
            );
        });

        expect(
            screen.getByText('Building your site. This takes a few seconds.'),
        ).toHaveAttribute('aria-live', 'polite');
        expect(
            screen.getByRole('button', { name: 'Building your site' }),
        ).toBeDisabled();
        expect(screen.getByRole('radio', { name: 'Blog' })).toBeDisabled();

        router.post.mockReset();
    });

    it('shows the server error next to the choices', () => {
        setPageProps({
            errors: { starter: 'Pick one of the sites on this page.' },
        });
        render(
            <GuidedStart starters={starters} current={null} hasPages={false} />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Pick one of the sites on this page.',
        );
        expect(
            screen.getByRole('group', { name: 'Kind of site' }),
        ).toHaveAccessibleDescription('Pick one of the sites on this page.');
    });

    it('shows a plain message when the internet is gone', async () => {
        router.post.mockImplementationOnce((_url, _data, options) => {
            options?.onNetworkError?.(new Error('Failed to fetch'));
            options?.onFinish?.({});
        });
        render(
            <GuidedStart starters={starters} current={null} hasPages={false} />,
        );

        await userEvent.click(
            screen.getByRole('radio', { name: 'Restaurant or cafe' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Build my site' }),
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'You seem to be offline. Check your internet connection, then press Build my site.',
        );
    });
});
