import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { isVersoIconName, versoIcons } from '@/components/verso/icons';
import { QueueNotice } from '@/components/verso/newsletter/queue-notice';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const stuckQueue = {
    title: 'Campaigns will not be sent: no queue worker is running',
    message:
        'The queue connection is "database", so emails wait for a worker. Run "php artisan queue:work".',
    lastSeen: null,
};

describe('QueueNotice', () => {
    it('renders nothing when the queue is healthy', () => {
        const { container } = render(<QueueNotice notice={null} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('shows the title, the advice and that no heartbeat was ever seen', () => {
        render(<QueueNotice notice={stuckQueue} />);

        const notice = screen.getByRole('status');

        expect(notice).toHaveTextContent(stuckQueue.title);
        expect(notice).toHaveTextContent('php artisan queue:work');
        expect(notice).toHaveTextContent('Last worker heartbeat: never');
    });

    it('shows the time of the last heartbeat in the site time zone', () => {
        render(
            <QueueNotice
                notice={{ ...stuckQueue, lastSeen: '2026-11-17T08:30:00+00:00' }}
            />,
        );

        const time = screen.getByRole('status').querySelector('time');

        expect(time).not.toBeNull();
        expect(time).toHaveAttribute('dateTime', '2026-11-17T08:30:00+00:00');
        expect(screen.getByRole('status')).not.toHaveTextContent('never');
    });

    it('draws the warning icon from the Verso set', () => {
        render(<QueueNotice notice={stuckQueue} />);

        const icon = screen.getByRole('status').querySelector('svg');

        expect(icon).toHaveAttribute('viewBox', '0 0 20 20');
        expect(icon).toHaveAttribute('stroke-width', '1.5');
        expect(icon).toHaveAttribute('aria-hidden', 'true');
    });
});

describe('warning icon', () => {
    it('is part of the Verso icon set', () => {
        expect(isVersoIconName('warning')).toBe(true);
        expect(versoIcons.warning).toBeTypeOf('function');
    });
});
