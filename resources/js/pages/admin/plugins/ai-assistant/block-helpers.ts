import { t } from '@/lib/i18n';
import type { Block } from '@/types';

// An image block that points to a media library file.
export type EntryImage = { mediaId: number; label: string };

// Plain text of inline HTML, read with the browser parser (no HTML is rendered).
export function htmlToText(html: string | undefined): string {
    if (!html) {
        return '';
    }

    const document = new DOMParser().parseFromString(html, 'text/html');

    return (document.body.textContent ?? '').trim();
}

// The text of each <li> in a list block.
export function listItems(html: string | undefined): string[] {
    if (!html) {
        return [];
    }

    const document = new DOMParser().parseFromString(html, 'text/html');

    return Array.from(document.querySelectorAll('li'))
        .map((item) => (item.textContent ?? '').trim())
        .filter((text) => text !== '');
}

// A block attribute as text, or an empty string when it is not text.
function textAttr(block: Block, name: string): string {
    const value = block.attrs?.[name];

    return typeof value === 'string' ? value : '';
}

// Every image block with a media id, also inside columns. Each media file once.
export function entryImages(blocks: Block[]): EntryImage[] {
    const images = new Map<number, EntryImage>();

    const walk = (list: Block[]) => {
        for (const block of list) {
            const mediaId = Number(block.attrs?.mediaId);

            if (block.type === 'image' && mediaId > 0 && !images.has(mediaId)) {
                const alt = textAttr(block, 'alt').trim();
                const src = textAttr(block, 'src');
                const file = src.split('/').pop() ?? '';

                images.set(mediaId, {
                    mediaId,
                    label:
                        alt ||
                        file ||
                        t('ai-assistant::Image {id}', { id: mediaId }),
                });
            }

            walk(block.children ?? []);
        }
    };

    walk(blocks);

    return [...images.values()];
}

// The same blocks with the alt text set on every image of the media file.
export function withImageAlt(
    blocks: Block[],
    mediaId: number,
    alt: string,
): Block[] {
    return blocks.map((block) => ({
        ...block,
        attrs:
            block.type === 'image' && Number(block.attrs?.mediaId) === mediaId
                ? { ...block.attrs, alt }
                : block.attrs,
        children: block.children
            ? withImageAlt(block.children, mediaId, alt)
            : block.children,
    }));
}

// True when the entry has no content yet (no blocks or one empty paragraph).
export function isEmptyContent(blocks: Block[]): boolean {
    return blocks.every(
        (block) =>
            block.type === 'paragraph' && htmlToText(block.content) === '',
    );
}

// Plain text of the blocks, one line per block, for previews.
export function blocksToText(blocks: Block[]): string[] {
    const lines: string[] = [];

    const walk = (list: Block[]) => {
        for (const block of list) {
            const text = htmlToText(block.content);

            if (text !== '' && block.type !== 'code' && block.type !== 'html') {
                lines.push(text);
            }

            walk(block.children ?? []);
        }
    };

    walk(blocks);

    return lines;
}
