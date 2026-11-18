import { msg } from '@/lib/i18n';
import { xsrfToken } from '@/lib/json-request';
import type { Block } from '@/types';
import {
    errorKind,
    firstValidationMessage,
    plainMessage,
} from './plain-errors';
import type { ErrorAction, ErrorKind } from './plain-errors';

// Requests of the visual page editor: draw the page from blocks, build a ready section,
// keep the automatic draft and list earlier versions.

export class PageEditorError extends Error {
    constructor(
        message: string,
        public readonly kind: ErrorKind = 'unknown',
    ) {
        super(message);
    }
}

const DRAW: ErrorAction = { retry: msg('Undo') };
const BUILD: ErrorAction = { retry: msg('Add section') };
const VERSIONS: ErrorAction = { retry: msg('Restore this page') };
const DRAFT: ErrorAction = { retry: msg('Save') };

// One earlier version of the page, from the revisions.
export type PageVersion = {
    id: number;
    savedAt: string | null;
    author: string | null;
    title: string;
    summary: string;
    blocks: Block[];
};

// The automatic draft the server keeps.
export type ServerDraft = { blocks: Block[]; savedAt: string | null };

const headers = (json: boolean): HeadersInit => ({
    Accept: 'application/json',
    ...(json ? { 'Content-Type': 'application/json' } : {}),
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': xsrfToken(),
});

// The plain message of a failed answer: the first validation message for 422, a sentence for the status otherwise.
async function errorFor(
    response: Response,
    action: ErrorAction,
): Promise<PageEditorError> {
    const kind = errorKind(response.status);
    const body =
        kind === 'invalid'
            ? ((await response.json().catch(() => ({}))) as {
                  errors?: Record<string, string[]>;
              })
            : {};

    return new PageEditorError(
        plainMessage(kind, action, firstValidationMessage(body.errors)),
        kind,
    );
}

// fetch() that turns a lost connection and error answers into plain messages. A cancelled request still throws AbortError.
async function send(
    url: string,
    init: RequestInit,
    action: ErrorAction,
): Promise<Response> {
    let response: Response;

    try {
        response = await fetch(url, { credentials: 'same-origin', ...init });
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            throw error;
        }

        throw new PageEditorError(plainMessage('offline', action), 'offline');
    }

    if (!response.ok) {
        throw await errorFor(response, action);
    }

    return response;
}

// The page HTML drawn by the theme from unsaved blocks.
export async function drawPage(
    url: string,
    blocks: Block[],
    signal?: AbortSignal,
): Promise<string> {
    const response = await send(
        url,
        {
            method: 'POST',
            headers: headers(true),
            body: JSON.stringify({ blocks }),
            signal,
        },
        DRAW,
    );

    return response.text();
}

// The blocks of a ready section, with ids made by the server.
export async function buildSection(
    url: string,
    signal?: AbortSignal,
): Promise<Block[]> {
    const response = await send(
        url,
        { headers: headers(false), signal },
        BUILD,
    );
    const body = (await response.json()) as { blocks?: Block[] };

    return Array.isArray(body.blocks) ? body.blocks : [];
}

// Keeps the blocks as the automatic draft on the server.
export async function saveDraft(
    url: string,
    blocks: Block[],
    signal?: AbortSignal,
): Promise<ServerDraft> {
    const response = await send(
        url,
        {
            method: 'PUT',
            headers: headers(true),
            body: JSON.stringify({ blocks }),
            signal,
        },
        DRAFT,
    );

    return (await response.json()) as ServerDraft;
}

// Throws the automatic draft away on the server.
export async function discardDraft(url: string): Promise<void> {
    await send(url, { method: 'DELETE', headers: headers(false) }, DRAFT);
}

// The earlier versions of the page, newest first.
export async function loadVersions(
    url: string,
    signal?: AbortSignal,
): Promise<PageVersion[]> {
    const response = await send(
        url,
        { headers: headers(false), signal },
        VERSIONS,
    );
    const body = (await response.json()) as { versions?: PageVersion[] };

    return Array.isArray(body.versions) ? body.versions : [];
}
