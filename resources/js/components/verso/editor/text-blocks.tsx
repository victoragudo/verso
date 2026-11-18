import type { Editor } from '@tiptap/react';
import { List, ListOrdered } from 'lucide-react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toggle } from '@/components/ui/toggle';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { BlockFieldProps } from './blocks/shared';
import { boolAttr, stringAttr, withAttrs } from './blocks/shared';
import { InlineEditor, type InlineEditorKeys } from './inline-editor';

// Props for text blocks, which are edited with Tiptap.
export type TextBlockProps = BlockFieldProps &
    InlineEditorKeys & {
        autoFocus: boolean;
        onFocus: (editor: Editor) => void;
    };

const HEADING_LEVELS = ['1', '2', '3', '4', '5', '6'];

const HEADING_SIZES: Record<string, string> = {
    '1': 'text-3xl font-bold',
    '2': 'text-2xl font-semibold',
    '3': 'text-xl font-semibold',
    '4': 'text-lg font-semibold',
    '5': 'text-base font-semibold',
    '6': 'text-sm font-semibold uppercase tracking-wide',
};

// Changes the outer list tag of stored list HTML.
export function switchListTag(html: string, ordered: boolean): string {
    const tag = ordered ? 'ol' : 'ul';

    return html
        .replace(/^<(ul|ol)(\s[^>]*)?>/, `<${tag}>`)
        .replace(/<\/(ul|ol)>$/, `</${tag}>`);
}

export function ParagraphBlockFields({
    block,
    onChange,
    ...props
}: TextBlockProps) {
    return (
        <InlineEditor
            {...props}
            label={t('Paragraph')}
            html={block.content ?? ''}
            placeholder={t('Type / to choose a block')}
            onChange={(html) =>
                onChange({ ...block, content: `<p>${html}</p>` })
            }
            onShortcodeBlock={(next) => {
                onChange({ ...next, id: block.id });

                return true;
            }}
            className="text-base leading-7 text-zinc-700 dark:text-zinc-300"
        />
    );
}

export function HeadingBlockFields({
    block,
    onChange,
    ...props
}: TextBlockProps) {
    const level = stringAttr(block, 'level') || '2';

    return (
        <div className="flex items-start gap-2">
            <Select
                value={level}
                onValueChange={(value) =>
                    onChange(withAttrs(block, { level: Number(value) }))
                }
            >
                <SelectTrigger
                    aria-label={t('Heading level')}
                    className="h-8 w-16 shrink-0 text-xs"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {HEADING_LEVELS.map((value) => (
                        <SelectItem key={value} value={value}>
                            H{value}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InlineEditor
                {...props}
                label={t('Heading level {level}', { level })}
                html={block.content ?? ''}
                placeholder={t('Heading')}
                onChange={(html) => onChange({ ...block, content: html })}
                className={cn(
                    'text-zinc-900 dark:text-zinc-50',
                    HEADING_SIZES[level],
                )}
            />
        </div>
    );
}

export function ListBlockFields({ block, onChange, ...props }: TextBlockProps) {
    const ordered = boolAttr(block, 'ordered');

    const setOrdered = (next: boolean) =>
        onChange({
            ...block,
            attrs: { ...block.attrs, ordered: next },
            content: switchListTag(block.content ?? '', next),
        });

    return (
        <div className="flex items-start gap-2">
            <div className="flex shrink-0 gap-1">
                <Toggle
                    size="sm"
                    aria-label={t('Bulleted list')}
                    pressed={!ordered}
                    onPressedChange={() => setOrdered(false)}
                >
                    <List />
                </Toggle>
                <Toggle
                    size="sm"
                    aria-label={t('Numbered list')}
                    pressed={ordered}
                    onPressedChange={() => setOrdered(true)}
                >
                    <ListOrdered />
                </Toggle>
            </div>
            <InlineEditor
                key={ordered ? 'ordered' : 'bulleted'}
                {...props}
                variant="list"
                label={ordered ? t('Numbered list') : t('Bulleted list')}
                html={block.content ?? ''}
                placeholder={t('List item')}
                onChange={(html) => onChange({ ...block, content: html })}
                className="text-zinc-700 dark:text-zinc-300 [&_li]:my-1 [&_ol]:list-decimal [&_ol]:ps-6 [&_ul]:list-disc [&_ul]:ps-6"
            />
        </div>
    );
}

export function QuoteBlockFields({
    block,
    onChange,
    ...props
}: TextBlockProps) {
    return (
        <div className="grid gap-2 border-s-4 border-zinc-300 ps-4 dark:border-zinc-700">
            <InlineEditor
                {...props}
                variant="paragraphs"
                label={t('Quote')}
                html={block.content ?? ''}
                placeholder={t('Quote')}
                onChange={(html) => onChange({ ...block, content: html })}
                className="text-lg text-zinc-800 italic dark:text-zinc-200 [&_p+p]:mt-2"
            />
            <Input
                aria-label={t('Citation')}
                placeholder={t('Who said it')}
                value={stringAttr(block, 'citation')}
                onChange={(event) =>
                    onChange(withAttrs(block, { citation: event.target.value }))
                }
                className="h-8 max-w-sm text-sm"
            />
        </div>
    );
}
