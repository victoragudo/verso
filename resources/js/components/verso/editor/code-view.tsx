import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { javascript } from '@codemirror/lang-javascript';
import { EditorView, basicSetup } from 'codemirror';
import { useEffect, useRef } from 'react';
import { t } from '@/lib/i18n';

export type CodeLanguage = 'html' | 'css' | 'javascript';

const LANGUAGES = { html, css, javascript };

type Props = {
    value: string;
    language?: CodeLanguage;
    label?: string;
    onChange: (value: string) => void;
    // Line to show and select, for example the line of a parse error.
    focusLine?: number | null;
};

// Code editor of the Code view: HTML highlighting, tag completion, folding, search and replace, line numbers.
export default function CodeView({
    value,
    onChange,
    focusLine = null,
    language = 'html',
    label,
}: Props) {
    const host = useRef<HTMLDivElement>(null);
    const view = useRef<EditorView | null>(null);
    const change = useRef(onChange);
    change.current = onChange;

    useEffect(() => {
        if (!host.current) {
            return;
        }

        const editor = new EditorView({
            doc: value,
            parent: host.current,
            extensions: [
                basicSetup,
                LANGUAGES[language](),
                EditorView.lineWrapping,
                EditorView.contentAttributes.of({
                    'aria-label': label ?? t('Page code'),
                }),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        change.current(update.state.doc.toString());
                    }
                }),
            ],
        });
        view.current = editor;

        return () => {
            editor.destroy();
            view.current = null;
        };
        // The editor keeps its own text after it starts, so it is made only once.
    }, []);

    useEffect(() => {
        const editor = view.current;

        if (!editor || !focusLine) {
            return;
        }

        const line = editor.state.doc.line(
            Math.min(Math.max(1, focusLine), editor.state.doc.lines),
        );
        editor.dispatch({
            selection: { anchor: line.from, head: line.to },
            effects: EditorView.scrollIntoView(line.from, { y: 'center' }),
        });
        editor.focus();
    }, [focusLine]);

    return (
        <div
            ref={host}
            data-test="code-view"
            className="min-h-64 overflow-hidden rounded-md border text-sm [&_.cm-editor]:min-h-64 [&_.cm-editor.cm-focused]:outline-2 [&_.cm-editor.cm-focused]:outline-ring"
        />
    );
}
