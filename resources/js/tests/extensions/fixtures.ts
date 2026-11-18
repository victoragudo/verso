import type {
    StagedExtension,
    VerificationFinding,
    VerificationReport,
} from '@/types';

// A finding from the shell rule, as the server sends it.
export const shellFinding: VerificationFinding = {
    rule: 'php.shell',
    severity: 'high',
    message: 'exec() runs a shell command.',
    file: 'src/Backup.php',
    line: 15,
};

// A stored verification report with sensible defaults.
export function verificationReport(
    overrides: Partial<VerificationReport> = {},
): VerificationReport {
    return {
        id: 7,
        kind: 'plugin',
        blocking: [],
        slug: 'backup-runner',
        version: '1.2.0',
        risk: 'high',
        score: 31,
        counts: { high: 1, medium: 1, low: 0, info: 0 },
        findings: [
            shellFinding,
            {
                rule: 'permission.undeclared',
                severity: 'medium',
                message:
                    'The code needs "manage_users", but verso.json does not ask for it.',
                file: 'src/Backup.php',
                line: 4,
            },
        ],
        details: {
            name: 'Backup Runner',
            files: 3,
            permissions: {
                declared: ['upload_files'],
                used: ['manage_users', 'upload_files'],
                network: [],
                hosts: ['backups.harbourlights.io'],
            },
            sandbox: {
                status: 'passed',
                seconds: 0.42,
                migrated: ['2026_12_01_000000_create_backups_table'],
                rolledBack: ['2026_12_01_000000_create_backups_table'],
                routes: [],
                errors: [],
                output: '',
            },
        },
        source: 'upload',
        checkedAt: '2026-11-17T09:30:00+00:00',
        installedAt: null,
        ...overrides,
    };
}

// A stored report of a theme that rendered in the sandbox.
export function themeReport(
    overrides: Partial<VerificationReport> = {},
): VerificationReport {
    return {
        id: 12,
        kind: 'theme',
        blocking: [],
        slug: 'harbor-light',
        version: '1.2.0',
        risk: 'low',
        score: 2,
        counts: { high: 0, medium: 0, low: 1, info: 1 },
        findings: [
            {
                rule: 'theme.image-size',
                severity: 'low',
                message:
                    'An image without width and height makes the page jump while it loads.',
                file: 'views/partials/entry-card.blade.php',
                line: 4,
            },
            {
                rule: 'theme.raw-output',
                severity: 'info',
                message: 'Prints data without escaping with {!! !!}.',
                file: 'views/entry.blade.php',
                line: 11,
            },
        ],
        details: {
            name: 'Harbor Light',
            files: 9,
            blocking: [],
            theme: {
                parent: null,
                requires: '^0.1',
                php: '^8.4',
                screenshot: 'screenshot.png',
                network: ['cdn.jsdelivr.net'],
                hosts: [],
                quality: {
                    seo: true,
                    head: true,
                    footer: true,
                    skipLink: true,
                    lang: false,
                },
            },
            sandbox: {
                status: 'passed',
                seconds: 1.2,
                migrated: [],
                rolledBack: [],
                routes: [],
                errors: [],
                output: '',
                pages: [
                    {
                        name: 'home',
                        path: '/',
                        status: 200,
                        seconds: 0.08,
                        bytes: 24576,
                        html: '<!DOCTYPE html><html lang="en"><body><h1>Harbor Journal</h1></body></html>',
                    },
                    {
                        name: 'entry',
                        path: '/blog/slow-travel-through-the-basque-country-part-1',
                        status: 200,
                        seconds: 0.05,
                        bytes: 30720,
                        html: '<!DOCTYPE html><html lang="en"><body><h1>Slow travel</h1></body></html>',
                    },
                    {
                        name: 'archive',
                        path: '/blog',
                        status: 500,
                        seconds: 0.04,
                        bytes: 0,
                        html: '',
                    },
                ],
            },
        },
        source: 'upload',
        checkedAt: '2026-11-17T09:30:00+00:00',
        installedAt: null,
        ...overrides,
    };
}

// A staged plugin upload waiting on the consent screen.
export function stagedPlugin(
    overrides: Partial<StagedExtension> = {},
): StagedExtension {
    return {
        token: '0b9d2f8e-3c1a-4c55-9e0f-6a7b8c9d0e1f',
        package: {
            kind: 'plugin',
            slug: 'backup-runner',
            name: 'Backup Runner',
            version: '1.2.0',
            description: 'Backs up your database and uploads every night.',
            author: 'Marta Ibarra',
            requires: '^0.1',
            permissions: ['upload_files'],
            network: ['backups.harbourlights.io'],
            fileCount: 3,
            unpackedBytes: 4096,
        },
        source: 'upload',
        sha256: 'a'.repeat(64),
        installedVersion: null,
        isUpdate: false,
        isDowngrade: false,
        ...overrides,
    };
}

export const capabilityLabels: Record<string, string> = {
    upload_files: 'Upload files',
    manage_users: 'Manage users',
};
