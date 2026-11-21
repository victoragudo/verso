import { msg, t } from '@/lib/i18n';

// Plain sentences with a next step for the errors people meet while building their site.
// Shared by the page editor, "Make it yours" and the guided start.

export type ErrorKind =
    | 'offline'
    | 'session'
    | 'forbidden'
    | 'too-large'
    | 'invalid'
    | 'server'
    | 'unknown';

// What the person was doing, so the next step can name the button to press again.
export type ErrorAction = {
    // The English label of the button, like msg('Save'). It is translated in the message: "then press Save".
    retry: string;
};

const SAVE: ErrorAction = { retry: msg('Save') };

// The kind of error for an HTTP status, or for a request that never reached the server.
export function errorKind(status: number | null): ErrorKind {
    if (status === null || status === 0) {
        return 'offline';
    }

    switch (status) {
        case 401:
        case 419:
            return 'session';
        case 403:
            return 'forbidden';
        case 413:
            return 'too-large';
        case 422:
            return 'invalid';
        default:
            return status >= 500 ? 'server' : 'unknown';
    }
}

// The browser knows when the device has no network. When it has one, the site itself did not answer.
function browserIsOnline(): boolean {
    return typeof navigator === 'undefined' || navigator.onLine !== false;
}

// The plain sentence for a kind of error. Work is never lost, so every message says what to do next.
export function plainMessage(
    kind: ErrorKind,
    action: ErrorAction = SAVE,
    detail?: string,
): string {
    const button = t(action.retry);

    switch (kind) {
        case 'offline':
            return browserIsOnline()
                ? t(
                      'We could not reach your site. Check that it is still running, then press {button}.',
                      { button },
                  )
                : t(
                      'You seem to be offline. Check your internet connection, then press {button}.',
                      { button },
                  );
        case 'session':
            return t(
                'Your session ended. Sign in again in a new tab, then come back here and press {button}.',
                { button },
            );
        case 'forbidden':
            return t(
                'You do not have permission to do this. Ask the person who runs the site to give you access.',
            );
        case 'too-large':
            return t(
                'This is too big to send in one go. Use a smaller picture or remove a section, then press {button}.',
                { button },
            );
        case 'invalid':
            return detail && detail.trim() !== ''
                ? detail
                : t(
                      'Something on the page is not right. Undo your last change, then press {button}.',
                      { button },
                  );
        case 'server':
            return t(
                'Something went wrong on our side. Your changes are still here. Wait a moment, then press {button}.',
                { button },
            );
        default:
            return t(
                'That did not work. Your changes are still here. Press {button} to try again.',
                { button },
            );
    }
}

// The plain sentence for an HTTP status.
export function messageForStatus(
    status: number | null,
    action: ErrorAction = SAVE,
    detail?: string,
): string {
    return plainMessage(errorKind(status), action, detail);
}

// The first message of Laravel validation errors (Inertia errors bag or a JSON "errors" object).
export function firstValidationMessage(
    errors: Record<string, string | string[] | undefined> | undefined,
    preferred?: string,
): string | undefined {
    if (!errors) {
        return undefined;
    }

    const pick = (value: string | string[] | undefined) =>
        Array.isArray(value) ? value[0] : value;
    const first = preferred ? pick(errors[preferred]) : undefined;

    return first ?? Object.values(errors).map(pick).find(Boolean);
}

// Inertia visit options that turn HTTP and network failures into one plain message instead of the error dialog.
// Validation errors still come through onError of the visit.
export function plainVisitErrors(
    onMessage: (message: string) => void,
    action: ErrorAction = SAVE,
) {
    return {
        onHttpException: (response: { status: number }) => {
            onMessage(messageForStatus(response.status, action));

            return false as const;
        },
        onNetworkError: () => {
            onMessage(plainMessage('offline', action));

            return false as const;
        },
    };
}
