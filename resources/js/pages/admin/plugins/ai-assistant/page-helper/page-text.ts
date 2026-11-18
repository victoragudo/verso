import { replaceBlock } from '@/builder/tree';
import { plainText, setText, updateBlock } from '@/lib/easy/page-blocks';
import { msg, t } from '@/lib/i18n';
import type { Block } from '@/types';
import { AiRequestError } from '../ai-api';

// Pure helpers of the page editor helper: what a section holds, the changes to preview and how to apply them.

// Block types whose content the helper writes, the same list as the server config "section_text".
const TEXT_TYPES = ['paragraph', 'heading', 'list', 'quote'];

// Text attrs of blocks that the preview compares, like a button label.
const TEXT_ATTRS = ['label', 'caption', 'citation', 'alt'] as const;

// One text that the helper changed: where it is and its text before and after, without tags.
export type TextChange = {
    key: string;
    before: string;
    after: string;
};

// A picture with no description: an image block, or one picture of a gallery.
export type ImageSpot = {
    key: string;
    blockId: string;
    index?: number;
    mediaId: number;
    src: string;
};

// The plain messages people see when the helper fails.
export const HELPER_ERRORS = {
    busy: msg('ai-assistant::The AI helper is busy. Try again in a minute.'),
    offline: msg('ai-assistant::Check your connection and try again.'),
    notReady: msg(
        'ai-assistant::The AI helper is not set up yet. An admin can add a key in Settings, AI Assistant.',
    ),
    forbidden: msg(
        'ai-assistant::You do not have permission to use the AI helper.',
    ),
};

function walk(blocks: Block[], visit: (block: Block) => void): void {
    for (const block of blocks) {
        visit(block);
        walk(block.children ?? [], visit);
    }
}

function textAttr(block: Block, name: string): string {
    const value = block.attrs?.[name];

    return typeof value === 'string' ? value : '';
}

// True when the section holds text the helper can write, like a heading or a button label.
export function sectionHasText(section: Block): boolean {
    let found = false;

    walk(section.children ?? [], (block) => {
        found ||=
            (TEXT_TYPES.includes(block.type) &&
                plainText(block.content ?? '') !== '') ||
            textAttr(block, 'label').trim() !== '';
    });

    return found;
}

// The first heading of a section (or the block itself when it is one).
export function firstHeading(
    block: Block,
): { id: string; text: string } | null {
    if (block.type === 'heading') {
        return { id: block.id, text: plainText(block.content ?? '') };
    }

    for (const child of block.children ?? []) {
        const heading = firstHeading(child);

        if (heading) {
            return heading;
        }
    }

    return null;
}

// The first section of the page with a heading, for "New headline for the top of the page".
export function topSection(blocks: Block[]): Block | null {
    return blocks.find((block) => firstHeading(block) !== null) ?? null;
}

// The words of a section as plain text, one line per block.
export function sectionWords(section: Block): string {
    const lines: string[] = [];

    walk(section.children ?? [], (block) => {
        const text = TEXT_TYPES.includes(block.type)
            ? plainText(block.content ?? '')
            : '';

        if (text !== '') {
            lines.push(text);
        }
    });

    return lines.join('\n');
}

function hasMedia(value: unknown): value is number {
    return typeof value === 'number' && value > 0;
}

// Pictures of a section that point to the media library and have no description yet.
export function imagesWithoutAlt(section: Block): ImageSpot[] {
    const spots: ImageSpot[] = [];

    walk([section], (block) => {
        if (block.type === 'image') {
            const mediaId = Number(block.attrs?.mediaId);

            if (hasMedia(mediaId) && textAttr(block, 'alt').trim() === '') {
                spots.push({
                    key: block.id,
                    blockId: block.id,
                    mediaId,
                    src: textAttr(block, 'src'),
                });
            }
        }

        if (block.type === 'gallery' && Array.isArray(block.attrs?.images)) {
            (block.attrs.images as Record<string, unknown>[]).forEach(
                (image, index) => {
                    const mediaId = Number(image?.mediaId);
                    const alt = typeof image?.alt === 'string' ? image.alt : '';

                    if (hasMedia(mediaId) && alt.trim() === '') {
                        spots.push({
                            key: `${block.id}:${index}`,
                            blockId: block.id,
                            index,
                            mediaId,
                            src: typeof image.src === 'string' ? image.src : '',
                        });
                    }
                },
            );
        }
    });

    return spots;
}

// The blocks with a description set on each given picture.
export function withAltTexts(
    blocks: Block[],
    described: (ImageSpot & { alt: string })[],
): Block[] {
    return described.reduce(
        (current, spot) =>
            updateBlock(current, spot.blockId, (block) => {
                if (spot.index === undefined) {
                    return {
                        ...block,
                        attrs: { ...block.attrs, alt: spot.alt },
                    };
                }

                const images = Array.isArray(block.attrs?.images)
                    ? [...(block.attrs.images as Record<string, unknown>[])]
                    : [];

                if (!images[spot.index]) {
                    return block;
                }

                images[spot.index] = { ...images[spot.index], alt: spot.alt };

                return { ...block, attrs: { ...block.attrs, images } };
            }),
        blocks,
    );
}

// The page with one section swapped for its new version (same id).
export function withSection(blocks: Block[], section: Block): Block[] {
    return replaceBlock(blocks, section.id, section);
}

// Text that is safe to put in a block content.
export function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// The page with a new headline in the given heading block.
export function withHeadline(
    blocks: Block[],
    headingId: string,
    headline: string,
): Block[] {
    return setText(blocks, headingId, 'content', escapeHtml(headline.trim()));
}

// Every text that differs between two versions of the same blocks (matched by block id), in reading order.
export function changedTexts(before: Block[], after: Block[]): TextChange[] {
    const next = new Map<string, Block>();
    walk(after, (block) => next.set(block.id, block));

    const changes: TextChange[] = [];

    walk(before, (block) => {
        const other = next.get(block.id);

        if (!other) {
            return;
        }

        const pairs: [string, string, string][] = [
            ['content', block.content ?? '', other.content ?? ''],
            ...TEXT_ATTRS.map((name): [string, string, string] => [
                name,
                textAttr(block, name),
                textAttr(other, name),
            ]),
        ];

        for (const [field, old, fresh] of pairs) {
            const oldText = plainText(old);
            const freshText = plainText(fresh);

            if (oldText !== freshText && (oldText !== '' || freshText !== '')) {
                changes.push({
                    key: `${block.id}:${field}`,
                    before: oldText,
                    after: freshText,
                });
            }
        }
    });

    return changes;
}

// A plain message for a failed request. Rate limits and checks keep the server message.
export function friendlyError(error: unknown): string {
    if (!(error instanceof AiRequestError)) {
        return t(HELPER_ERRORS.busy);
    }

    switch (error.status) {
        case 0:
            return t(HELPER_ERRORS.offline);
        case 403:
            return t(HELPER_ERRORS.forbidden);
        case 422:
        case 429:
            return error.message;
        case 503:
            return t(HELPER_ERRORS.notReady);
        default:
            return t(HELPER_ERRORS.busy);
    }
}
