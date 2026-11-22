import { type Editor, useEditorState } from '@tiptap/react';
import { LetterB, Code, Italic, Link2, Link2Off } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Toggle } from '@/components/ui/toggle';
import { t } from '@/lib/i18n';

type MarkState = {
    bold: boolean;
    italic: boolean;
    code: boolean;
    link: boolean;
    href: string;
};

const EMPTY_STATE: MarkState = {
    bold: false,
    italic: false,
    code: false,
    link: false,
    href: '',
};

// Toolbar for inline marks (bold, italic, code and links) of the focused text block.
export function MarkToolbar({
    editor: activeEditor,
}: {
    editor: Editor | null;
}) {
    // A text block can turn into another block (a pasted shortcode), which destroys its editor.
    const editor = activeEditor?.isDestroyed ? null : activeEditor;
    const [linkOpen, setLinkOpen] = useState(false);
    const [href, setHref] = useState('');

    const state =
        useEditorState({
            editor,
            selector: ({ editor: current }): MarkState =>
                current
                    ? {
                          bold: current.isActive('bold'),
                          italic: current.isActive('italic'),
                          code: current.isActive('code'),
                          link: current.isActive('link'),
                          href: String(
                              current.getAttributes('link').href ?? '',
                          ),
                      }
                    : EMPTY_STATE,
        }) ?? EMPTY_STATE;

    const disabled = editor === null;

    const openLink = () => {
        setHref(state.href);
        setLinkOpen(true);
    };

    const applyLink = (event: FormEvent) => {
        event.preventDefault();

        if (!editor) {
            return;
        }

        const chain = editor.chain().focus().extendMarkRange('link');

        if (href.trim() === '') {
            chain.unsetLink().run();
        } else {
            chain.setLink({ href: href.trim() }).run();
        }

        setLinkOpen(false);
    };

    return (
        <div
            role="group"
            aria-label={t('Text formatting')}
            className="flex shrink-0 items-center gap-0.5"
            onMouseDown={(event) => {
                if (!(event.target instanceof HTMLInputElement)) {
                    event.preventDefault();
                }
            }}
        >
            <Toggle
                size="sm"
                aria-label={t('Bold')}
                pressed={state.bold}
                disabled={disabled}
                onPressedChange={() =>
                    editor?.chain().focus().toggleBold().run()
                }
            >
                <LetterB />
            </Toggle>
            <Toggle
                size="sm"
                aria-label={t('Italic')}
                pressed={state.italic}
                disabled={disabled}
                onPressedChange={() =>
                    editor?.chain().focus().toggleItalic().run()
                }
            >
                <Italic />
            </Toggle>
            <Toggle
                size="sm"
                aria-label={t('Inline code')}
                pressed={state.code}
                disabled={disabled}
                onPressedChange={() =>
                    editor?.chain().focus().toggleCode().run()
                }
            >
                <Code />
            </Toggle>
            <Toggle
                size="sm"
                aria-label={t('Link')}
                pressed={state.link || linkOpen}
                disabled={disabled}
                onPressedChange={() =>
                    linkOpen ? setLinkOpen(false) : openLink()
                }
            >
                <Link2 />
            </Toggle>
            {state.link && !linkOpen && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    aria-label={t('Remove link')}
                    onClick={() =>
                        editor
                            ?.chain()
                            .focus()
                            .extendMarkRange('link')
                            .unsetLink()
                            .run()
                    }
                >
                    <Link2Off />
                </Button>
            )}
            {linkOpen && (
                <form onSubmit={applyLink} className="flex items-center gap-1">
                    <Input
                        autoFocus
                        aria-label={t('Link address')}
                        placeholder={t('https://example.org or /blog/post')}
                        value={href}
                        onChange={(event) => setHref(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Escape') {
                                setLinkOpen(false);
                                editor?.commands.focus();
                            }
                        }}
                        className="h-8 w-64"
                    />
                    <Button type="submit" size="sm">
                        {t('Apply')}
                    </Button>
                </form>
            )}
        </div>
    );
}

export default MarkToolbar;
