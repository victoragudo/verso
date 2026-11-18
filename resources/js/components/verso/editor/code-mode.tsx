import { lazy, Suspense, useState } from 'react';
import { codeToBlocks, serializeBlocks } from '@/builder/code';
import type { CodeError } from '@/builder/code';
import { t, Trans } from '@/lib/i18n';
import { convert } from '@/routes/admin/editor';
import type { Block } from '@/types';

const CodeView = lazy(() => import('./code-view'));

// Reads the CSRF token that Laravel keeps in the XSRF-TOKEN cookie.
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

// Asks the server to turn pasted HTML or Gutenberg markup into blocks.
export async function convertHtml(html: string): Promise<Block[]> {
    const response = await fetch(convert.url(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify({ html }),
    });

    if (!response.ok) {
        throw new Error(`Convert failed with status ${response.status}.`);
    }

    const body = (await response.json()) as { blocks?: Block[] };

    return body.blocks ?? [];
}

export type CodeModeState = {
    code: string;
    error: CodeError | null;
    busy: boolean;
};

// Starts the Code view with the blocks written as code.
export function startCode(blocks: Block[]): CodeModeState {
    return { code: serializeBlocks(blocks), error: null, busy: false };
}

// Reads the code back. Gives the blocks, or keeps the code and reports the line with the problem.
export async function finishCode(
    state: CodeModeState,
    converter: (html: string) => Promise<Block[]> = convertHtml,
): Promise<{ blocks: Block[] } | { error: CodeError }> {
    return codeToBlocks(state.code, converter);
}

type Props = {
    state: CodeModeState;
    onChange: (state: CodeModeState) => void;
};

// The Code view of the editor, with the error of the last switch back to Visual.
export function CodeMode({ state, onChange }: Props) {
    const [focusLine, setFocusLine] = useState<number | null>(null);

    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                <Trans
                    text={t(
                        'The page as HTML. Each block sits between <code>{marker}</code> markers. You can paste plain HTML or WordPress blocks; they turn into blocks when you switch back to Visual.',
                        { marker: '<!-- verso:name -->' },
                    )}
                    components={{
                        code: (text) => <code className="text-xs">{text}</code>,
                    }}
                />
            </p>
            {state.error && (
                <div
                    role="alert"
                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm"
                >
                    <span>
                        <Trans
                            text={t(
                                '<strong>Line {line}:</strong> {message} Your code is kept.',
                                {
                                    line: String(state.error.line),
                                    message: state.error.message,
                                },
                            )}
                            components={{
                                strong: (text) => <strong>{text}</strong>,
                            }}
                        />
                    </span>
                    <button
                        type="button"
                        className="font-medium underline underline-offset-2 hover:decoration-2"
                        onClick={() => setFocusLine(state.error?.line ?? null)}
                    >
                        {t('Go to line {line}', {
                            line: String(state.error.line),
                        })}
                    </button>
                </div>
            )}
            <Suspense
                fallback={
                    <p className="min-h-96 text-sm text-muted-foreground">
                        {t('Loading the code editor…')}
                    </p>
                }
            >
                <CodeView
                    value={state.code}
                    focusLine={focusLine}
                    onChange={(code) => onChange({ ...state, code })}
                />
            </Suspense>
        </div>
    );
}
