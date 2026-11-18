import { t } from '@/lib/i18n';

// Calls the JSON endpoints of the install wizard (test buttons and the install runner).

export class InstallRequestError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

// Reads the CSRF token that Laravel keeps in the XSRF-TOKEN cookie.
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function installRequest<T>(
    url: string,
    method: 'GET' | 'POST' = 'POST',
    body: Record<string, unknown> = {},
): Promise<T> {
    let response: Response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            ...(method === 'POST' ? { body: JSON.stringify(body) } : {}),
        });
    } catch {
        throw new InstallRequestError(
            t('The server did not answer. Check that it is running.'),
            0,
        );
    }

    const data = (await response.json().catch(() => ({}))) as T & {
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok) {
        const firstError = data.errors
            ? Object.values(data.errors)[0]?.[0]
            : undefined;

        throw new InstallRequestError(
            response.status === 429
                ? t('Too many tries. Wait a minute, then try again.')
                : (firstError ?? data.message ?? t('Something went wrong.')),
            response.status,
        );
    }

    return data;
}

export type TestResult = {
    ok: boolean;
    message: string;
    hint?: string | null;
    problem?: string | null;
    can_create_database?: boolean;
    verso_tables?: string[];
};
