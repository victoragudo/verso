import type { CoreRelease, CoreUpdateDetail, CoreUpdateStep } from '@/types';

const STEP_LABELS: Record<string, string> = {
    lock: 'Make sure no other update runs',
    download: 'Download the package',
    verify: 'Check the checksum and signature',
    extract: 'Unpack the package',
    maintenance: 'Turn on maintenance mode',
    backup_database: 'Back up the database',
    backup_files: 'Back up the core files',
    replace_files: 'Copy the new core files',
    migrate: 'Run database migrations',
    clear_caches: 'Clear caches',
    online: 'Turn off maintenance mode',
    health_check: 'Check that the site works',
    cleanup: 'Remove temporary files',
};

// Steps of a run: the first `done` steps are finished, the next one has the given status.
export function steps(
    done: number,
    next: CoreUpdateStep['status'] = 'running',
): CoreUpdateStep[] {
    return Object.entries(STEP_LABELS).map(([key, label], index) => ({
        key,
        label,
        status: index < done ? 'done' : index === done ? next : 'pending',
        message: index < done ? `${label} finished.` : null,
        at: index <= done ? '2026-11-20T10:15:00Z' : null,
    }));
}

export function updateRun(
    overrides: Partial<CoreUpdateDetail> = {},
): CoreUpdateDetail {
    return {
        id: 7,
        from_version: '1.1.4',
        to_version: '1.2.0',
        status: 'running',
        status_label: 'Running',
        trigger: 'admin',
        trigger_label: 'Admin',
        user: 'Ines Navarro',
        error: null,
        created_at: '2026-11-20T10:14:00Z',
        started_at: '2026-11-20T10:14:05Z',
        finished_at: null,
        has_backup: false,
        finished: false,
        steps: steps(3),
        ...overrides,
    };
}

export function release(overrides: Partial<CoreRelease> = {}): CoreRelease {
    return {
        version: '1.2.0',
        name: 'Verso 1.2.0',
        notes_html:
            '<h2>Highlights</h2><ul><li>New <strong>gallery</strong> layouts</li></ul>',
        published_at: '2026-11-20T09:30:00Z',
        pre_release: false,
        security: false,
        page_url: 'https://github.com/victoragudo/verso/releases/tag/v1.2.0',
        package_size: 18_350_112,
        signed: false,
        ...overrides,
    };
}
