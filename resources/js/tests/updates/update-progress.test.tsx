import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { UpdateProgress } from '@/components/verso/updates/update-progress';
import { steps, updateRun } from './fixtures';

describe('UpdateProgress', () => {
    it('shows the versions, the status and every step', () => {
        render(<UpdateProgress update={updateRun()} />);

        expect(screen.getByText('Verso 1.1.4 to 1.2.0')).toBeInTheDocument();
        expect(screen.getAllByRole('listitem')).toHaveLength(13);
        expect(
            screen.getByText('Download the package finished.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('progressbar')).toHaveAttribute(
            'aria-valuenow',
            '23',
        );
    });

    it('marks the running step', () => {
        render(<UpdateProgress update={updateRun()} />);

        const running = screen
            .getByText('Unpack the package')
            .closest('li') as HTMLElement;

        expect(within(running).getByText('Running')).toBeInTheDocument();
    });

    it('fills the bar when the run is completed', () => {
        render(
            <UpdateProgress
                update={updateRun({
                    status: 'completed',
                    status_label: 'Completed',
                    finished: true,
                    steps: steps(13, 'done'),
                })}
            />,
        );

        expect(screen.getByRole('progressbar')).toHaveAttribute(
            'aria-valuenow',
            '100',
        );
        expect(screen.getByText('Completed')).toBeInTheDocument();
    });

    it('shows the error and the rollback step of a rolled back run', () => {
        render(
            <UpdateProgress
                update={updateRun({
                    status: 'rolled_back',
                    status_label: 'Rolled back',
                    finished: true,
                    error: 'php artisan migrate failed: SQLSTATE[42S21]',
                    steps: [
                        ...steps(8, 'failed'),
                        {
                            key: 'rollback',
                            label: 'Roll back',
                            status: 'done',
                            message:
                                'Restored the core files and the database. The site runs Verso 1.1.4 again.',
                            at: '2026-11-20T10:16:00Z',
                        },
                    ],
                })}
            />,
        );

        expect(
            screen.getByText('php artisan migrate failed: SQLSTATE[42S21]'),
        ).toBeInTheDocument();
        expect(screen.getByText('Roll back')).toBeInTheDocument();
        expect(screen.getByText('Failed')).toBeInTheDocument();
        expect(screen.getByText('Rolled back')).toBeInTheDocument();
    });
});
