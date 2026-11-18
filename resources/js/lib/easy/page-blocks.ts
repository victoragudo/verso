import { findBlock, replaceBlock } from '@/builder/tree';
import { t } from '@/lib/i18n';
import type { Block } from '@/types';

// Block tree operations of the visual page editor. Top level blocks are "sections".
// Every function returns a new list and leaves the given one untouched.

export type MoveDirection = 'up' | 'down';

// Where a picture lives: an image block, or one picture of a gallery.
export type ImageTarget = { blockId: string; index?: number };

// A picture picked in the media library.
export type PickedImage = {
    id: number;
    url: string;
    alt: string | null;
    width: number | null;
    height: number | null;
};

// The text parts the frame can change.
export type TextField = 'content' | 'label' | 'caption' | 'citation';

// Length of the random part of a new block id, like the server ids "b_k2j3h4ab".
const ID_LENGTH = 8;

// A new block id that is not in the given set. The set is updated.
export function freshId(used: Set<string>): string {
    let id: string;

    do {
        id = `b_${Math.random()
            .toString(36)
            .slice(2, 2 + ID_LENGTH)
            .padEnd(ID_LENGTH, '0')}`;
    } while (used.has(id));

    used.add(id);

    return id;
}

// Every block id at any depth.
export function allIds(blocks: Block[]): Set<string> {
    const ids = new Set<string>();
    const walk = (list: Block[]) => {
        for (const block of list) {
            ids.add(block.id);
            walk(block.children ?? []);
        }
    };
    walk(blocks);

    return ids;
}

// A deep copy of a block with a new id for it and every child.
export function withFreshIds(block: Block, used: Set<string>): Block {
    return {
        ...block,
        id: freshId(used),
        attrs: structuredClone(block.attrs ?? {}),
        children: (block.children ?? []).map((child) =>
            withFreshIds(child, used),
        ),
    };
}

// Position of a section (top level block), or -1.
export function sectionIndex(blocks: Block[], id: string): number {
    return blocks.findIndex((block) => block.id === id);
}

// Moves a section one place up or down. At the edge the list is returned as it is.
export function moveSection(
    blocks: Block[],
    id: string,
    direction: MoveDirection,
): Block[] {
    const from = sectionIndex(blocks, id);
    const to = direction === 'up' ? from - 1 : from + 1;

    if (from < 0 || to < 0 || to >= blocks.length) {
        return blocks;
    }

    const next = [...blocks];
    [next[from], next[to]] = [next[to], next[from]];

    return next;
}

// Puts a copy of a section right after it. The copy and its children get new ids.
export function duplicateSection(blocks: Block[], id: string): Block[] {
    const index = sectionIndex(blocks, id);

    if (index < 0) {
        return blocks;
    }

    const copy = withFreshIds(blocks[index], allIds(blocks));

    return [...blocks.slice(0, index + 1), copy, ...blocks.slice(index + 1)];
}

// Removes a section and says where it was, so "Undo" can put it back.
export function removeSection(
    blocks: Block[],
    id: string,
): { blocks: Block[]; removed: Block | null; index: number } {
    const index = sectionIndex(blocks, id);

    if (index < 0) {
        return { blocks, removed: null, index: -1 };
    }

    return {
        blocks: blocks.filter((block) => block.id !== id),
        removed: blocks[index],
        index,
    };
}

// Inserts sections at a position (0 is the top). Ids already on the page are replaced by new ones.
export function insertSections(
    blocks: Block[],
    index: number,
    sections: Block[],
): Block[] {
    const used = allIds(blocks);
    const fresh = sections.map((section) =>
        hasAnyId(section, used)
            ? withFreshIds(section, used)
            : remember(section, used),
    );
    const at = Math.max(0, Math.min(index, blocks.length));

    return [...blocks.slice(0, at), ...fresh, ...blocks.slice(at)];
}

function hasAnyId(block: Block, used: Set<string>): boolean {
    return (
        used.has(block.id) ||
        (block.children ?? []).some((child) => hasAnyId(child, used))
    );
}

function remember(block: Block, used: Set<string>): Block {
    allIds([block]).forEach((id) => used.add(id));

    return block;
}

// True when the block is hidden on the site (attrs.design.visibility.hidden).
export function isHidden(block: Block): boolean {
    const design = block.attrs?.design as
        | { visibility?: { hidden?: unknown } }
        | undefined;

    return design?.visibility?.hidden === true;
}

// Hides a shown section or shows a hidden one. Other style options are kept.
export function toggleHidden(blocks: Block[], id: string): Block[] {
    return updateBlock(blocks, id, (block) => {
        const design = {
            ...(block.attrs?.design as Record<string, unknown> | undefined),
        };
        const visibility = {
            ...(design.visibility as Record<string, unknown> | undefined),
        };

        if (isHidden(block)) {
            delete visibility.hidden;
        } else {
            visibility.hidden = true;
        }

        if (Object.keys(visibility).length === 0) {
            delete design.visibility;
        } else {
            design.visibility = visibility;
        }

        const rest = Object.fromEntries(
            Object.entries(block.attrs ?? {}).filter(
                ([key]) => key !== 'design',
            ),
        );

        return {
            ...block,
            attrs:
                Object.keys(design).length === 0 ? rest : { ...rest, design },
        };
    });
}

// Changes one block anywhere in the tree. Unknown ids and changes that keep the block leave the list as it is.
export function updateBlock(
    blocks: Block[],
    id: string,
    change: (block: Block) => Block,
): Block[] {
    const block = findBlock(blocks, id);

    if (!block) {
        return blocks;
    }

    const next = change(block);

    return next === block ? blocks : replaceBlock(blocks, id, next);
}

// Sets the text of a block: its content or a text attr like a button label.
export function setText(
    blocks: Block[],
    id: string,
    field: TextField,
    value: string,
): Block[] {
    const edited = findBlock(blocks, id);
    const next = updateBlock(blocks, id, (block) => {
        const current =
            field === 'content' ? block.content : block.attrs?.[field];

        if (current === value) {
            return block;
        }

        return field === 'content'
            ? { ...block, content: value }
            : { ...block, attrs: { ...block.attrs, [field]: value } };
    });

    if (next === blocks || field !== 'content' || edited?.type !== 'heading') {
        return next;
    }

    return renameSection(next, id, plainText(edited.content ?? ''), value);
}

// A section named after its heading takes the new heading text, so screen readers hear the title people see.
function renameSection(
    blocks: Block[],
    headingId: string,
    before: string,
    after: string,
): Block[] {
    const section = blocks.find(
        (block) => block.id === headingId || findBlock([block], headingId),
    );
    const label = section?.attrs?.label;

    if (!section || typeof label !== 'string' || label.trim() !== before) {
        return blocks;
    }

    return replaceBlock(blocks, section.id, {
        ...section,
        attrs: { ...section.attrs, label: plainText(after) },
    });
}

// Image attrs from a picked picture. The alt text of the media item wins over an old one.
function imageAttrs(
    image: PickedImage,
    previous: Record<string, unknown>,
): Record<string, unknown> {
    const attrs: Record<string, unknown> = {
        ...previous,
        mediaId: image.id,
        src: image.url,
        alt: image.alt ?? '',
    };

    for (const key of ['width', 'height'] as const) {
        if (image[key]) {
            attrs[key] = image[key];
        } else {
            delete attrs[key];
        }
    }

    return attrs;
}

// Puts a picked picture in an image block or in one place of a gallery.
export function setImage(
    blocks: Block[],
    target: ImageTarget,
    image: PickedImage,
): Block[] {
    return updateBlock(blocks, target.blockId, (block) => {
        if (block.type !== 'gallery' || target.index === undefined) {
            return { ...block, attrs: imageAttrs(image, block.attrs ?? {}) };
        }

        const images = Array.isArray(block.attrs?.images)
            ? [...(block.attrs.images as Record<string, unknown>[])]
            : [];

        if (target.index < 0 || target.index >= images.length) {
            return block;
        }

        images[target.index] = imageAttrs(image, images[target.index] ?? {});
        const mediaIds = images
            .map((item) => item.mediaId)
            .filter((value): value is number => typeof value === 'number');

        return { ...block, attrs: { ...block.attrs, images, mediaIds } };
    });
}

// A short name of a section for buttons and announcements, like "Section 2: What we offer".
export function sectionName(blocks: Block[], id: string): string {
    const index = sectionIndex(blocks, id);
    const block = blocks[index];

    if (!block) {
        return t('Section');
    }

    const label =
        typeof block.attrs?.label === 'string' ? block.attrs.label.trim() : '';
    const heading = label || firstHeading(block);

    return heading
        ? t('Section {number}: {heading}', { number: index + 1, heading })
        : t('Section {number}', { number: index + 1 });
}

function firstHeading(block: Block): string {
    if (block.type === 'heading') {
        return plainText(block.content ?? '');
    }

    for (const child of block.children ?? []) {
        const text = firstHeading(child);

        if (text) {
            return text;
        }
    }

    return '';
}

// The text of a little HTML, without tags.
export function plainText(html: string): string {
    return html
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#0?39;/g, "'")
        .replace(/\s+/g, ' ')
        .trim();
}
