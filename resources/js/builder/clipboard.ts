import { cloneBlock } from '@/components/verso/editor/block-types';
import type { Block } from '@/types';

// Data type of copied blocks; plain text holds the same JSON so it also works between tabs and pages.
export const BLOCKS_MIME = 'application/x-verso-blocks';

// Version of the copied JSON shape.
export const CLIPBOARD_VERSION = 1;

export function blocksToClipboard(blocks: Block[]): string {
    return JSON.stringify({
        verso: 'blocks',
        version: CLIPBOARD_VERSION,
        blocks,
    });
}

function isBlock(value: unknown): value is Block {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const block = value as Record<string, unknown>;

    return (
        typeof block.type === 'string' &&
        (block.attrs === undefined ||
            (typeof block.attrs === 'object' && !Array.isArray(block.attrs))) &&
        (block.content === undefined || typeof block.content === 'string') &&
        (block.children === undefined ||
            (Array.isArray(block.children) && block.children.every(isBlock)))
    );
}

// Reads copied blocks with new ids, or null when the text is not copied blocks.
export function blocksFromClipboard(text: string): Block[] | null {
    try {
        const data = JSON.parse(text) as Record<string, unknown>;

        if (
            data?.verso !== 'blocks' ||
            !Array.isArray(data.blocks) ||
            !data.blocks.every(isBlock)
        ) {
            return null;
        }

        return (data.blocks as Block[]).map((block) =>
            cloneBlock({
                ...block,
                attrs: block.attrs ?? {},
                content: block.content ?? '',
                children: block.children ?? [],
            }),
        );
    } catch {
        return null;
    }
}

// True when the key or clipboard event happens in a field that handles copy, paste and undo itself.
export function inTextField(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLElement &&
        (target.isContentEditable ||
            target.closest(
                'input, textarea, select, [contenteditable="true"], .cm-editor',
            ) !== null)
    );
}
