// Calls the AI assistant endpoints. Errors carry the message the server sent.

import { t } from '@/lib/i18n';

export class AiRequestError extends Error {
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

// Sends JSON (a POST unless told otherwise) and returns the "result" part of the answer.
export async function askAssistant<T>(
    url: string,
    body: Record<string, unknown>,
    signal?: AbortSignal,
    method: 'POST' | 'PUT' = 'POST',
): Promise<T> {
    let response: Response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            signal,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(body),
        });
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            throw error;
        }

        throw new AiRequestError(t('Check your connection and try again.'), 0);
    }

    const data = (await response.json().catch(() => ({}))) as {
        result?: T;
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok || data.result === undefined) {
        const firstError = data.errors
            ? Object.values(data.errors)[0]?.[0]
            : undefined;

        throw new AiRequestError(
            firstError ?? data.message ?? t('Something went wrong.'),
            response.status,
        );
    }

    return data.result;
}
