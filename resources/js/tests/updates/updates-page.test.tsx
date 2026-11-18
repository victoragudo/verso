import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UpdatesIndex from '@/pages/admin/updates/index';
import { release, steps, updateRun } from './fixtures';

const formPost = vi.fn();
const formPut = vi.fn();
const pollStop = vi.fn();
const usePoll = vi.fn(() => ({ stop: pollStop, start: vi.fn() }));

// A small useForm stand-in that keeps data in React state.
function useForm<T extends Record<string, unknown>>(initial: T) {
    const [data, setDataState] = useState(initial);

    return {
        data,
        errors: {} as Partial<Record<keyof T, string>>,
        processing: false,
        isDirty: JSON.stringify(data) !== JSON.stringify(initial),
        setData: (key: keyof T, value: unknown) =>
            setDataState((current) => ({ ...current, [key]: value })),
        post: (url: string) => formPost(url, data),
        put: (url: string) => formPut(url, data),
    };
}

vi.mock('@inertiajs/react', async () => ({
    ...(await import('@/tests/mocks/inertia')).inertiaMock,
    useForm,
    usePoll: (...args: unknown[]) => usePoll(...(args as [])),
}));

type PageProps = Parameters<typeof UpdatesIndex>[0];

function pageProps(overrides: Partial<PageProps> = {}): PageProps {
    return {
        check: {
            configured: true,
            currentVersion: '1.1.4',
            latest: release(),
            updateAvailable: true,
            checkedAt: '2026-11-20T04:17:00Z',
            error: null,
        },
        settings: { channel: 'stable', automatic: 'off' },
        channels: [
            {
                value: 'stable',
                label: 'Stable',
                description: 'Only final releases.',
            },
            {
                value: 'beta',
                label: 'Beta',
                description: 'Final releases and test versions.',
            },
        ],
        automaticOptions: [
            {
                value: 'off',
                label: 'Off',
                description: 'Updates are only installed when you start them.',
            },
            {
                value: 'security',
                label: 'Security fixes only',
                description: 'Patch releases marked as security fixes.',
            },
            {
                value: 'minor',
                label: 'Minor and security updates',
                description: 'New minor and patch releases.',
            },
        ],
        databaseBackupProblem: null,
        signatureRequired: false,
        activeUpdate: null,
        updates: [],
        pollInterval: 1500,
        ...overrides,
    };
}

beforeEach(() => {
    formPost.mockClear();
    formPut.mockClear();
    usePoll.mockClear();
});

describe('Updates page', () => {
    it('shows the running and newest versions with the changelog', () => {
        render(<UpdatesIndex {...pageProps()} />);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Updates' }),
        ).toBeInTheDocument();
        expect(screen.getByText('1.1.4')).toBeInTheDocument();
        expect(screen.getByText('Update available')).toBeInTheDocument();
        expect(
            screen.getByText('What is new in Verso 1.2.0'),
        ).toBeInTheDocument();
        expect(screen.getByText('gallery').tagName).toBe('STRONG');
        expect(
            screen.getByRole('link', { name: /Release page/ }),
        ).toHaveAttribute(
            'href',
            'https://github.com/victoragudo/verso/releases/tag/v1.2.0',
        );
    });

    it('starts the update with the shown version', () => {
        render(<UpdatesIndex {...pageProps()} />);

        fireEvent.click(
            screen.getByRole('button', { name: 'Update to 1.2.0' }),
        );

        expect(formPost).toHaveBeenCalledWith('/admin/updates', {
            version: '1.2.0',
            allow_without_database_backup: false,
        });
    });

    it('asks for consent when the database cannot be backed up', () => {
        render(
            <UpdatesIndex
                {...pageProps({
                    databaseBackupProblem:
                        'mysqldump or mysql was not found on this server.',
                })}
            />,
        );

        const button = screen.getByRole('button', { name: 'Update to 1.2.0' });

        expect(
            screen.getByText(
                'mysqldump or mysql was not found on this server.',
            ),
        ).toBeInTheDocument();
        expect(button).toBeDisabled();

        fireEvent.click(
            screen.getByRole('checkbox', { name: /Update without one/ }),
        );

        expect(button).toBeEnabled();
    });

    it('shows a security release with a warning badge', () => {
        render(
            <UpdatesIndex
                {...pageProps({
                    check: {
                        ...pageProps().check,
                        latest: release({ security: true }),
                    },
                })}
            />,
        );

        expect(screen.getByText('Security update')).toBeInTheDocument();
    });

    it('says the site is up to date and hides the update button', () => {
        render(
            <UpdatesIndex
                {...pageProps({
                    check: {
                        ...pageProps().check,
                        currentVersion: '1.2.0',
                        updateAvailable: false,
                    },
                })}
            />,
        );

        expect(screen.getByText('Up to date')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Update to/ })).toBeNull();
    });

    it('polls and shows live progress while an update runs', () => {
        render(<UpdatesIndex {...pageProps({ activeUpdate: updateRun() })} />);

        expect(screen.getByText('Update in progress')).toBeInTheDocument();
        expect(usePoll).toHaveBeenCalledWith(1500, {
            only: ['activeUpdate', 'updates', 'check'],
        });
        expect(
            screen.getByRole('button', { name: 'Update to 1.2.0' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Check now' }),
        ).toBeDisabled();
    });

    it('does not poll once the update has finished', () => {
        render(
            <UpdatesIndex
                {...pageProps({
                    activeUpdate: updateRun({
                        status: 'completed',
                        status_label: 'Completed',
                        finished: true,
                        steps: steps(13, 'done'),
                    }),
                })}
            />,
        );

        expect(screen.getByText('Last update')).toBeInTheDocument();
        expect(usePoll).not.toHaveBeenCalled();
    });

    it('lists past updates in the log', () => {
        render(
            <UpdatesIndex
                {...pageProps({
                    updates: [
                        {
                            ...updateRun({
                                status: 'rolled_back',
                                status_label: 'Rolled back',
                                error: 'The site answered 500 at /login.',
                            }),
                        },
                    ],
                })}
            />,
        );

        expect(screen.getAllByText('1.1.4 to 1.2.0').length).toBeGreaterThan(0);
        expect(
            screen.getAllByText('The site answered 500 at /login.').length,
        ).toBeGreaterThan(0);
    });

    it('shows an empty log', () => {
        render(<UpdatesIndex {...pageProps()} />);

        expect(screen.getByText('No updates yet')).toBeInTheDocument();
    });

    it('explains that checks are off without a feed', () => {
        render(
            <UpdatesIndex
                {...pageProps({
                    check: {
                        ...pageProps().check,
                        configured: false,
                        latest: null,
                        updateAvailable: false,
                    },
                })}
            />,
        );

        expect(screen.getByText('Update checks are off')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Check now' })).toBeNull();
    });

    it('shows the description of the chosen settings', () => {
        render(<UpdatesIndex {...pageProps()} />);

        expect(screen.getByText('Only final releases.')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Save settings' }),
        ).toBeDisabled();
    });
});
