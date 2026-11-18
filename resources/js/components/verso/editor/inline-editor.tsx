import Placeholder from '@tiptap/extension-placeholder';
import {
    type Editor,
    EditorContent,
    Extension,
    Node,
    useEditor,
} from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect, useMemo, useRef } from 'react';
import { cn } from '@/lib/utils';
import type { Block } from '@/types';
import { createShortcodeNode, useShortcodeDefinitions } from './shortcode-chip';
import {
    shortcodeDefinitions,
    shortcodeToBlock,
    singleShortcode,
    splitShortcodes,
    unwrapShortcodeChips,
    wrapShortcodeChips,
} from './shortcodes';

// How the text is shaped: one line, several paragraphs or a list.
export type InlineEditorVariant = 'line' | 'paragraphs' | 'list';

// Keys the parent can handle. Return true when the key was used.
export type InlineEditorKeys = {
    onEnter?: () => boolean;
    onBackspaceEmpty?: () => boolean;
    onArrowUp?: () => boolean;
    onArrowDown?: () => boolean;
    onEscape?: () => boolean;
    // A shortcode pasted alone into an empty editor becomes this block.
    onShortcodeBlock?: (block: Block) => boolean;
};

export type InlineEditorProps = InlineEditorKeys & {
    html: string;
    onChange: (html: string) => void;
    variant?: InlineEditorVariant;
    placeholder?: string;
    autoFocus?: boolean;
    className?: string;
    label: string;
    onFocus?: (editor: Editor) => void;
};

const DOCUMENT_CONTENT: Record<InlineEditorVariant, string> = {
    line: 'paragraph',
    paragraphs: 'paragraph+',
    list: '(bulletList | orderedList)',
};

// Inner HTML of the single paragraph used by one line editors.
function unwrapParagraph(html: string): string {
    const match = html.match(/^<p>([\s\S]*)<\/p>$/);

    return match ? match[1] : html;
}

// A small Tiptap editor for the text of one block, with inline marks only.
export function InlineEditor({
    html,
    onChange,
    variant = 'line',
    placeholder,
    autoFocus = false,
    className,
    label,
    onFocus,
    ...keys
}: InlineEditorProps) {
    const keysRef = useRef<InlineEditorKeys>(keys);
    const changeRef = useRef(onChange);
    const focusRef = useRef(onFocus);

    useEffect(() => {
        keysRef.current = keys;
        changeRef.current = onChange;
        focusRef.current = onFocus;
    });

    // Kept stable between renders: a new extension list would make Tiptap build a new editor.
    const allDefinitions = useShortcodeDefinitions();
    const definitions = useMemo(
        () => shortcodeDefinitions(allDefinitions),
        [allDefinitions],
    );
    const names = useMemo(
        () => definitions.map((definition) => definition.name),
        [definitions],
    );
    const shortcodeNode = useMemo(
        () =>
            definitions.length > 0 ? createShortcodeNode(definitions) : null,
        [definitions],
    );

    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            Node.create({
                name: 'doc',
                topNode: true,
                content: DOCUMENT_CONTENT[variant],
            }),
            StarterKit.configure({
                document: false,
                heading: false,
                blockquote: false,
                codeBlock: false,
                horizontalRule: false,
                dropcursor: false,
                gapcursor: false,
                trailingNode: false,
                bulletList: variant === 'list' ? {} : false,
                orderedList: variant === 'list' ? {} : false,
                listItem: variant === 'list' ? {} : false,
                listKeymap: variant === 'list' ? {} : false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    HTMLAttributes: { target: null, rel: null },
                },
            }),
            Placeholder.configure({ placeholder: placeholder ?? '' }),
            ...(shortcodeNode ? [shortcodeNode] : []),
            Extension.create({
                name: 'versoBlockKeys',
                addKeyboardShortcuts() {
                    return {
                        Enter: () =>
                            variant === 'line' &&
                            (keysRef.current.onEnter?.() ?? false),
                        Backspace: ({ editor: current }) =>
                            current.isEmpty &&
                            (keysRef.current.onBackspaceEmpty?.() ?? false),
                        ArrowUp: () => keysRef.current.onArrowUp?.() ?? false,
                        ArrowDown: () =>
                            keysRef.current.onArrowDown?.() ?? false,
                        Escape: () => keysRef.current.onEscape?.() ?? false,
                    };
                },
            }),
        ],
        content: wrapShortcodeChips(html, names),
        editorProps: {
            // A shortcode alone becomes its block. Inside text, known shortcodes become chips.
            handlePaste: (view, event) => {
                const text = event.clipboardData?.getData('text/plain') ?? '';

                if (names.length === 0 || !text.includes('[')) {
                    return false;
                }

                const single = singleShortcode(text);
                const block = single
                    ? shortcodeToBlock(single, definitions)
                    : null;

                if (
                    block &&
                    view.state.doc.textContent.trim() === '' &&
                    (keysRef.current.onShortcodeBlock?.(block) ?? false)
                ) {
                    return true;
                }

                const parts = splitShortcodes(text);

                if (
                    !parts.some(
                        (part) =>
                            part.kind === 'shortcode' &&
                            names.includes(part.shortcode.name),
                    )
                ) {
                    return false;
                }

                const { schema } = view.state;
                const nodes = parts.flatMap((part) => {
                    if (
                        part.kind === 'shortcode' &&
                        names.includes(part.shortcode.name)
                    ) {
                        return [
                            schema.nodes.shortcode.create({
                                raw: part.shortcode.raw,
                            }),
                        ];
                    }

                    const value =
                        part.kind === 'text' ? part.text : part.shortcode.raw;

                    return value === '' ? [] : [schema.text(value)];
                });
                view.dispatch(
                    view.state.tr.replaceSelectionWith(
                        schema.nodes.paragraph.create(null, nodes),
                        false,
                    ),
                );

                return true;
            },
            attributes: {
                'aria-label': label,
                role: 'textbox',
                'aria-multiline': variant === 'line' ? 'false' : 'true',
                class: 'outline-none',
            },
        },
        onUpdate: ({ editor: current }) => {
            const output = current.isEmpty
                ? ''
                : unwrapShortcodeChips(current.getHTML());

            changeRef.current(
                variant === 'line' ? unwrapParagraph(output) : output,
            );
        },
        onFocus: ({ editor: current }) => focusRef.current?.(current),
    });

    useEffect(() => {
        if (editor && !editor.isDestroyed && autoFocus) {
            editor.commands.focus('end');
        }
    }, [editor, autoFocus]);

    return (
        <EditorContent
            editor={editor}
            className={cn(
                'min-h-7 w-full break-words [&_.is-editor-empty:first-child]:before:pointer-events-none [&_.is-editor-empty:first-child]:before:float-start [&_.is-editor-empty:first-child]:before:h-0 [&_.is-editor-empty:first-child]:before:text-muted-foreground [&_.is-editor-empty:first-child]:before:content-[attr(data-placeholder)] [&_a]:text-blue-600 [&_a]:underline [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1 [&_code]:font-mono [&_code]:text-[0.9em] dark:[&_code]:bg-zinc-800',
                className,
            )}
        />
    );
}

export default InlineEditor;
