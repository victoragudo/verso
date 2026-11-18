import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { LaunchChecklistCard } from '@/components/verso/easy/launch-checklist-card';
import {
    isComplete,
    nextStep,
    progressLabel,
} from '@/lib/easy/launch-checklist';
import type { LaunchChecklistData } from '@/lib/easy/launch-checklist';
import { router } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const checklist: LaunchChecklistData = {
    items: [
        {
            key: 'logo',
            label: 'Add your logo',
            description: 'Your logo shows at the top of every page.',
            href: '/admin/easy/style',
            action: 'Do it',
            method: 'get',
            done: true,
        },
        {
            key: 'contact',
            label: 'Add your contact details',
            description: 'So people can reach you.',
            href: '/admin/easy/details#contact',
            action: 'Do it',
            method: 'get',
            done: false,
        },
        {
            key: 'privacy',
            label: 'Add a privacy page',
            description: 'Tell visitors what you do with their data.',
            href: '/admin/easy/launch/privacy',
            action: 'Create a privacy page',
            method: 'post',
            done: false,
        },
        {
            key: 'go-live',
            label: 'Go live',
            description: 'Let search engines find your site.',
            href: '/admin/easy/launch/go-live',
            action: 'Go live',
            method: 'post',
            done: false,
        },
    ],
    done: 1,
    total: 4,
    dismissUrl: '/admin/easy/launch/dismiss',
};

const allDone: LaunchChecklistData = {
    ...checklist,
    items: checklist.items.map((item) => ({ ...item, done: true })),
    done: 4,
};

beforeEach(() => router.post.mockClear());

describe('launch checklist helpers', () => {
    it('counts the steps in plain words', () => {
        expect(progressLabel(5, 8)).toBe('5 of 8 done');
        expect(progressLabel(8, 8)).toBe('All done. Your site is ready.');
        expect(progressLabel(0, 0)).toBe('0 of 0 done');
    });

    it('finds the next step and knows when all is done', () => {
        expect(nextStep(checklist.items)?.key).toBe('contact');
        expect(nextStep(allDone.items)).toBeNull();
        expect(isComplete(checklist)).toBe(false);
        expect(isComplete(allDone)).toBe(true);
        expect(isComplete({ done: 0, total: 0 })).toBe(false);
    });
});

describe('LaunchChecklistCard', () => {
    it('shows the progress bar and one row per step', () => {
        render(<LaunchChecklistCard data={checklist} />);

        const progress = screen.getByRole('progressbar', {
            name: '1 of 4 done',
        });
        expect(progress).toHaveAttribute('value', '1');
        expect(progress).toHaveAttribute('max', '4');

        const rows = screen.getAllByRole('listitem');
        expect(rows).toHaveLength(4);
        expect(rows[0]).toHaveTextContent('Add your logo (done)');
        expect(rows[1]).toHaveTextContent(
            'Add your contact details (not done yet)',
        );
    });

    it('links to the page that fixes a step, and lets people change a done one', () => {
        render(<LaunchChecklistCard data={checklist} />);

        expect(
            screen.getByRole('link', {
                name: 'Do it: Add your contact details',
            }),
        ).toHaveAttribute('href', '/admin/easy/details#contact');
        expect(
            screen.getByRole('link', { name: 'Change: Add your logo' }),
        ).toHaveAttribute('href', '/admin/easy/style');
    });

    it('posts the buttons of steps like "Go live"', async () => {
        render(<LaunchChecklistCard data={checklist} />);

        await userEvent.click(screen.getByRole('button', { name: 'Go live' }));

        expect(router.post).toHaveBeenCalledWith(
            '/admin/easy/launch/go-live',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('can only be hidden when every step is done', async () => {
        const { rerender } = render(<LaunchChecklistCard data={checklist} />);

        expect(
            screen.queryByRole('button', { name: 'Hide checklist' }),
        ).toBeNull();

        rerender(<LaunchChecklistCard data={allDone} />);

        const list = screen.getByRole('list', { name: 'Steps' });
        expect(within(list).queryAllByRole('button')).toHaveLength(0);
        expect(
            screen.getByText('All done. Your site is ready.'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Hide checklist' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/easy/launch/dismiss',
            {},
            expect.anything(),
        );
    });
});
