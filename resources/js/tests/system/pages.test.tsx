import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type {
    ServerLimits,
    SystemGroup,
    SystemSetting,
} from '@/components/verso/system';
import ServerHealthPage from '@/pages/admin/system/health';
import SystemSettingsPage from '@/pages/admin/system/settings';

const formMocks = vi.hoisted(() => ({ put: vi.fn(), post: vi.fn() }));

// A small useForm kept in React state, with transform support like Inertia.
vi.mock('@inertiajs/react', async () => {
    const base = (await import('@/tests/mocks/inertia')).inertiaMock;
    const react = await import('react');

    return {
        ...base,
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setState] = react.useState(initial);
            const transform = react.useRef((value: T) => value as unknown);

            return {
                data,
                setData: (key: keyof T, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                transform: (callback: (value: T) => unknown) => {
                    transform.current = callback;
                },
                errors: {},
                processing: false,
                clearErrors: vi.fn(),
                reset: vi.fn(),
                post: (url: string) =>
                    formMocks.post(url, transform.current(data)),
                put: (url: string) =>
                    formMocks.put(url, transform.current(data)),
            };
        },
    };
});

afterEach(() => {
    vi.clearAllMocks();
});

const groups: SystemGroup[] = [
    {
        key: 'uploads',
        label: 'Uploads and media',
        description: 'File sizes and image processing.',
        icon: 'media',
        plugin: null,
    },
    {
        key: 'email',
        label: 'Email',
        description: 'How Verso sends email.',
        icon: 'newsletter',
        plugin: null,
    },
];

function setting(overrides: Partial<SystemSetting>): SystemSetting {
    return {
        key: 'verso.media.per_page',
        group: 'uploads',
        label: 'Items per page in the media library',
        help: 'How many files the library loads at once.',
        type: 'integer',
        unit: 'items',
        dimension: null,
        min: 10,
        max: 200,
        options: [],
        env: 'VERSO_MEDIA_PER_PAGE',
        apply: [],
        limits: [],
        format: null,
        value: 40,
        default: 40,
        changed: false,
        envLocked: false,
        problem: null,
        upload: null,
        ...overrides,
    };
}

const parameters: SystemSetting[] = [
    setting({
        key: 'verso.media.max_upload_kb',
        label: 'Largest media upload',
        help: 'Biggest file people can add to the media library.',
        unit: 'kb',
        dimension: 'size',
        min: 1,
        max: 4194304,
        value: 10240,
        default: 10240,
        limits: ['upload_max_filesize', 'post_max_size', 'web_server_body'],
        upload: {
            bytes: 7.75 * 1024 ** 2,
            human: '7.8 MB',
            bottleneck: 'post_max_size',
            limitedByServer: true,
            explanation: 'Uploads stop at 7.8 MB because of PHP post_max_size.',
            parts: [
                {
                    key: 'verso',
                    label: 'the Verso setting',
                    bytes: 10 * 1024 ** 2,
                    note: '',
                },
                {
                    key: 'upload_max_filesize',
                    label: 'PHP upload_max_filesize',
                    bytes: 8 * 1024 ** 2,
                    note: '',
                },
                {
                    key: 'post_max_size',
                    label: 'PHP post_max_size',
                    bytes: 7.75 * 1024 ** 2,
                    note: '',
                },
                {
                    key: 'web_server',
                    label: 'the web server body limit',
                    bytes: null,
                    note: '',
                },
            ],
        },
    }),
    setting({}),
    setting({
        key: 'mail.mailers.smtp.host',
        group: 'email',
        label: 'SMTP host',
        help: 'Name or IP address of the SMTP server.',
        type: 'text',
        unit: 'none',
        min: 1,
        max: 190,
        env: 'MAIL_HOST',
        value: 'smtp.harbourlight.org',
        default: 'smtp.harbourlight.org',
        envLocked: true,
    }),
];

const server: ServerLimits = {
    sapi: 'fpm-fcgi',
    webServer: 'nginx',
    phpUpload: { upload_max_filesize: '8M', post_max_size: '8M' },
    target: {
        key: 'user_ini',
        label: '.user.ini (PHP-FPM or CGI)',
        file: 'public/.user.ini',
        writable: true,
        preview:
            '; BEGIN Verso managed PHP limits\nupload_max_filesize = 200M\n; END Verso managed PHP limits\n',
    },
    cacheTtl: 300,
    recommended: [
        {
            name: 'upload_max_filesize',
            current: '8M',
            value: '200M',
            scope: 'PHP_INI_PERDIR',
        },
    ],
    snippets: [
        {
            key: 'nginx',
            label: 'nginx',
            file: 'server { ... } block',
            code: 'client_max_body_size 201m;',
            recommended: true,
        },
    ],
};

function renderSettings() {
    return render(
        <SystemSettingsPage
            groups={groups}
            parameters={parameters}
            server={server}
            audit={[]}
            healthUrl="/admin/system/health"
        />,
    );
}

describe('System settings page', () => {
    it('asks before a setting goes back to its default', async () => {
        const user = userEvent.setup();
        const confirm = vi.spyOn(window, 'confirm').mockReturnValueOnce(false);
        render(
            <SystemSettingsPage
                groups={groups}
                parameters={[
                    setting({
                        key: 'verso.media.per_page',
                        label: 'Media per page',
                        value: 60,
                        changed: true,
                    }),
                ]}
                server={server}
                audit={[]}
                healthUrl="/admin/system/health"
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Reset to default' }));
        expect(confirm).toHaveBeenCalledWith(
            'Go back to the default value of this setting?',
        );
        expect(router.post).not.toHaveBeenCalled();

        confirm.mockReturnValueOnce(true);
        await user.click(screen.getByRole('button', { name: 'Reset to default' }));
        expect(router.post).toHaveBeenCalledWith(
            expect.any(String),
            { key: 'verso.media.per_page' },
            expect.any(Object),
        );
        confirm.mockRestore();
    });

    it('shows sizes in a fitting unit and warns when a value is above the PHP limit', async () => {
        const user = userEvent.setup();
        renderSettings();

        const input = screen.getByLabelText('Largest media upload');
        expect(input).toHaveValue(10);
        expect(
            screen.getByRole('combobox', {
                name: 'Unit of Largest media upload',
            }),
        ).toHaveTextContent('MB');

        await user.clear(input);
        await user.type(input, '64');

        const warning = screen
            .getByText('Above the server limit')
            .closest('[role="status"]') as HTMLElement;
        expect(
            within(warning).getByText(
                /Uploads will stop at 7.8 MB because of PHP post_max_size/,
            ),
        ).toBeInTheDocument();
        expect(screen.getByText('1 unsaved change')).toBeInTheDocument();
    });

    it('saves only changed values, in the stored unit', async () => {
        const user = userEvent.setup();
        renderSettings();

        const input = screen.getByLabelText('Largest media upload');
        await user.clear(input);
        await user.type(input, '64');
        await user.click(screen.getByRole('button', { name: 'Save changes' }));

        expect(formMocks.put).toHaveBeenCalledWith('/admin/system/settings', {
            values: { 'verso.media.max_upload_kb': 65536 },
        });
    });

    it('shows a range message after the field is left', async () => {
        const user = userEvent.setup();
        renderSettings();

        const input = screen.getByLabelText(
            'Items per page in the media library',
        );
        await user.clear(input);
        await user.type(input, '5000');
        expect(
            screen.queryByText('Use 200 items or less.'),
        ).not.toBeInTheDocument();

        fireEvent.blur(input);
        expect(screen.getByText('Use 200 items or less.')).toBeInTheDocument();
        expect(input).toHaveAttribute('aria-invalid', 'true');
    });

    it('searches every group and locks values set by the environment', async () => {
        const user = userEvent.setup();
        renderSettings();

        await user.type(
            screen.getByRole('searchbox', { name: 'Search all settings' }),
            'smtp',
        );

        expect(screen.getByText('Results for "smtp"')).toBeInTheDocument();
        expect(screen.getByLabelText('SMTP host')).toBeDisabled();
        expect(
            screen.getByText('Set by the server environment'),
        ).toBeInTheDocument();
        expect(
            screen.queryByLabelText('Largest media upload'),
        ).not.toBeInTheDocument();
    });

    it('asks for consent before writing .user.ini', async () => {
        const user = userEvent.setup();
        renderSettings();

        await user.click(
            screen.getByRole('button', { name: 'Apply to public/.user.ini' }),
        );
        const dialog = screen.getByRole('dialog');
        expect(
            within(dialog).getByText(/up to 300 seconds/),
        ).toBeInTheDocument();

        await user.click(within(dialog).getByRole('checkbox'));
        await user.click(
            within(dialog).getByRole('button', { name: 'Write the limits' }),
        );

        expect(formMocks.post).toHaveBeenCalledWith(
            '/admin/system/settings/php-limits',
            { target: 'user_ini', confirm: true },
        );
    });
});

describe('Server health page', () => {
    it('lists risks with commands and runs the planned fixes after consent', async () => {
        const user = userEvent.setup();
        render(
            <ServerHealthPage
                php={{
                    version: '8.4.14',
                    minimum: '8.4.0',
                    sapi: 'fpm-fcgi',
                    webServer: 'nginx',
                    iniFile: '/etc/php/8.4/fpm/php.ini',
                    user: 'www-data',
                    group: 'www-data',
                }}
                extensions={[
                    { name: 'intl', loaded: true, required: true },
                    { name: 'sodium', loaded: false, required: true },
                ]}
                loadedExtensions={['Core', 'intl', 'pdo_sqlite']}
                ini={[
                    {
                        name: 'upload_max_filesize',
                        value: '8M',
                        about: 'Biggest single uploaded file.',
                        runtime: false,
                        perDirectory: true,
                        scope: 'PHP_INI_PERDIR',
                    },
                ]}
                checks={[
                    {
                        key: 'env',
                        group: 'files',
                        label: '.env is private',
                        status: 'danger',
                        detail: 'Every user on the server can read this file.',
                        path: '.env',
                        mode: '0644',
                        recommended: '0640',
                        fixes: [
                            {
                                type: 'chmod',
                                path: '.env',
                                mode: '0640',
                                description: 'Change the mode of .env to 0640',
                            },
                        ],
                        command: 'chmod 640 .env',
                    },
                    {
                        key: 'debug',
                        group: 'web',
                        label: 'Debug mode is off in production',
                        status: 'ok',
                        detail: 'Debug mode is off.',
                        path: null,
                        mode: null,
                        recommended: null,
                        fixes: [],
                        command: null,
                    },
                ]}
                plan={[
                    {
                        description: 'Change the mode of .env to 0640',
                        done: false,
                        error: null,
                    },
                ]}
                modes={{
                    folders: '0755 or 0775',
                    files: '0644 or 0664',
                    env: '0640',
                }}
                settingsUrl="/admin/system/settings"
            />,
        );

        expect(screen.getByText('chmod 640 .env')).toBeInTheDocument();
        expect(
            screen.getByText('1 required extension is missing.'),
        ).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Fix 1 problem' }));
        const dialog = screen.getByRole('dialog');
        await user.click(within(dialog).getByRole('checkbox'));
        await user.click(
            within(dialog).getByRole('button', { name: 'Fix now' }),
        );

        expect(formMocks.post).toHaveBeenCalledWith(
            '/admin/system/health/fix',
            { confirm: true },
        );
    });
});
