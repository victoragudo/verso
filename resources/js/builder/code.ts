import { newBlockId } from '@/components/verso/editor/block-types';
import { t } from '@/lib/i18n';
import type { Block } from '@/types';
import { builder } from './index';
import type { BuilderApi } from './index';
import type { BuilderCodeSerializer } from './types';

// Block markers look like HTML comments: <!-- verso:heading#b_k2j3 {"level":2} --> ... <!-- /verso:heading -->.
// Blocks with no inner code use one marker: <!-- verso:separator#b_x1 /-->.
const MARKER =
    /<!--\s*(\/)?verso:([a-z0-9][a-z0-9/_-]*)(?:#([A-Za-z0-9_-]{1,64}))?\s*(\{[\s\S]*?\}|\[[\s\S]*?\])?\s*(\/)?\s*-->/g;

// Turns attrs into JSON that can sit safely inside an HTML comment.
function attrsJson(attrs: Record<string, unknown>): string {
    return Object.keys(attrs).length === 0
        ? ''
        : ` ${JSON.stringify(attrs).replace(/--/g, '-\\u002d').replace(/>/g, '\\u003e')}`;
}

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function unescapeHtml(text: string): string {
    return text
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
}

// Inner code of a block: its serializer, or its content as it is.
function innerCode(block: Block): string {
    return (
        builder.codeSerializers.get(block.type)?.serialize(block) ??
        block.content ??
        ''
    );
}

// The whole document as code with block markers, one block per marker pair.
export function serializeBlocks(blocks: Block[], depth = 0): string {
    const indent = '  '.repeat(depth);

    return blocks
        .map((block) => {
            const { attrs = {} } = block;
            const head = `verso:${block.type}#${block.id}${attrsJson(attrs as Record<string, unknown>)}`;
            const inner = innerCode(block);
            const children = block.children ?? [];

            if (inner === '' && children.length === 0) {
                return `${indent}<!-- ${head} /-->`;
            }

            const body = [
                inner === '' ? null : inner,
                children.length > 0
                    ? serializeBlocks(children, depth + 1)
                    : null,
            ]
                .filter((part): part is string => part !== null)
                .join('\n');

            return `${indent}<!-- ${head} -->\n${body}\n${indent}<!-- /verso:${block.type} -->`;
        })
        .join('\n\n');
}

export type CodeError = { line: number; message: string };

// A piece of code outside any block marker, like pasted HTML or Gutenberg markup.
export type FreeCode = { html: string; line: number };

export type ParsedCode =
    | { ok: true; items: (Block | FreeCode)[] }
    | { ok: false; error: CodeError };

type Frame = {
    block: Block;
    line: number;
    text: string[];
    children: Block[];
};

function lineAt(code: string, offset: number): number {
    return code.slice(0, offset).split('\n').length;
}

function isBlock(item: Block | FreeCode): item is Block {
    return 'type' in item;
}

// Reads the code back into blocks. Code outside markers is kept as free code to convert later.
// On a problem it returns the line and a plain message, and nothing is lost.
export function parseCode(code: string): ParsedCode {
    const stack: Frame[] = [];
    const items: (Block | FreeCode)[] = [];
    const seen = new Set<string>();
    let cursor = 0;

    const addText = (text: string, offset: number) => {
        const top = stack[stack.length - 1];

        if (top) {
            top.text.push(text);
        } else if (text.trim() !== '') {
            items.push({
                html: text.trim(),
                line: lineAt(code, offset + text.search(/\S/)),
            });
        }
    };

    const finish = (frame: Frame) => {
        const inner = frame.text.join('').trim();
        const serializer: BuilderCodeSerializer | undefined =
            builder.codeSerializers.get(frame.block.type);
        const parsed = serializer
            ? serializer.parse(inner, frame.block.attrs ?? {})
            : { ...frame.block, content: inner };
        const block: Block = {
            ...frame.block,
            ...parsed,
            id: frame.block.id,
            type: frame.block.type,
            children: frame.children,
        };
        const parent = stack[stack.length - 1];

        if (parent) {
            parent.children.push(block);
        } else {
            items.push(block);
        }
    };

    for (const match of code.matchAll(MARKER)) {
        const [whole, closing, type, id, json, selfClosing] = match;
        const offset = match.index ?? 0;
        const line = lineAt(code, offset);
        addText(code.slice(cursor, offset), cursor);
        cursor = offset + whole.length;

        if (closing) {
            const top = stack.pop();

            if (!top || top.block.type !== type) {
                return {
                    ok: false,
                    error: {
                        line,
                        message: top
                            ? t(
                                  'This closes a "{type}" block, but the "{open}" block from line {line} is still open.',
                                  {
                                      type,
                                      open: top.block.type,
                                      line: String(top.line),
                                  },
                              )
                            : t(
                                  'This closes a "{type}" block that was never opened.',
                                  { type },
                              ),
                    },
                };
            }

            finish(top);
            continue;
        }

        if (!builder.blocks.has(type)) {
            return {
                ok: false,
                error: {
                    line,
                    message: t('"{type}" is not a known block.', { type }),
                },
            };
        }

        let attrs: Record<string, unknown> = {};

        if (json) {
            try {
                const value: unknown = JSON.parse(json);

                if (
                    !value ||
                    typeof value !== 'object' ||
                    Array.isArray(value)
                ) {
                    throw new Error('not an object');
                }

                attrs = value as Record<string, unknown>;
            } catch {
                return {
                    ok: false,
                    error: {
                        line,
                        message: t(
                            'The settings of the "{type}" block are not valid JSON.',
                            { type },
                        ),
                    },
                };
            }
        }

        const blockId = id && !seen.has(id) ? id : newBlockId();
        seen.add(blockId);
        const frame: Frame = {
            block: { id: blockId, type, attrs, content: '', children: [] },
            line,
            text: [],
            children: [],
        };

        if (selfClosing) {
            finish(frame);
        } else {
            stack.push(frame);
        }
    }

    addText(code.slice(cursor), cursor);
    const open = stack.pop();

    if (open) {
        return {
            ok: false,
            error: {
                line: open.line,
                message: t(
                    'The "{type}" block is never closed. Add {marker} after its content.',
                    {
                        type: open.block.type,
                        marker: `<!-- /verso:${open.block.type} -->`,
                    },
                ),
            },
        };
    }

    return { ok: true, items };
}

// Turns code into blocks. Free code (pasted HTML or Gutenberg markup) goes through the converter.
export async function codeToBlocks(
    code: string,
    convert: (html: string) => Promise<Block[]>,
): Promise<{ blocks: Block[] } | { error: CodeError }> {
    const parsed = parseCode(code);

    if (!parsed.ok) {
        return { error: parsed.error };
    }

    const blocks: Block[] = [];

    for (const item of parsed.items) {
        if (isBlock(item)) {
            blocks.push(item);
            continue;
        }

        try {
            blocks.push(...(await convert(item.html)));
        } catch {
            return {
                error: {
                    line: item.line,
                    message: t(
                        'This HTML could not be turned into blocks. Check it, or wrap it in block markers.',
                    ),
                },
            };
        }
    }

    return { blocks };
}

// Wraps the inner code in one HTML tag, and reads it back.
function wrapped(
    type: string,
    tagOf: (block: Block) => string,
    attrsOf: (tag: string) => Record<string, unknown>,
): BuilderCodeSerializer {
    return {
        name: type,
        serialize: (block) => {
            const tag = tagOf(block);

            return `<${tag}>${block.content ?? ''}</${tag}>`;
        },
        parse: (inner, attrs) => {
            const match = /^<([a-z][a-z0-9]*)>([\s\S]*)<\/\1>$/i.exec(
                inner.trim(),
            );
            const tag = match?.[1].toLowerCase() ?? '';
            const extra = match ? attrsOf(tag) : {};

            return {
                id: '',
                type,
                attrs: { ...attrs, ...extra },
                content: match ? match[2] : inner,
                children: [],
            };
        },
    };
}

// The code serializers of core blocks, registered like a plugin would.
export function registerCoreSerializers(api: BuilderApi): void {
    api.codeSerializers.register(
        wrapped(
            'heading',
            (block) =>
                `h${Math.min(6, Math.max(1, Number(block.attrs?.level ?? 2)))}`,
            (tag) => (/^h[1-6]$/.test(tag) ? { level: Number(tag[1]) } : {}),
        ),
    );
    api.codeSerializers.register(
        wrapped(
            'list',
            (block) => (block.attrs?.ordered ? 'ol' : 'ul'),
            (tag) =>
                tag === 'ol' || tag === 'ul' ? { ordered: tag === 'ol' } : {},
        ),
    );
    api.codeSerializers.register(
        wrapped(
            'quote',
            () => 'blockquote',
            () => ({}),
        ),
    );
    api.codeSerializers.register({
        name: 'code',
        serialize: (block) =>
            `<pre><code>${escapeHtml(block.content ?? '')}</code></pre>`,
        parse: (inner, attrs) => {
            const match = /^<pre><code>([\s\S]*)<\/code><\/pre>$/.exec(
                inner.trim(),
            );

            return {
                id: '',
                type: 'code',
                attrs,
                content: match ? unescapeHtml(match[1]) : inner,
                children: [],
            };
        },
    });
}
