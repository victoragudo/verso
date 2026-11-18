import { matchesSearch, normalizeSearchText } from '@/lib/option-search';
import type { Block, BlockPattern, PatternBlock } from '@/types';
import { newBlockId } from './block-types';

export type PatternGroup = { category: string; patterns: BlockPattern[] };

// Turns the blocks of a pattern into editor blocks with fresh ids.
export function patternToBlocks(pattern: BlockPattern): Block[] {
    return pattern.blocks.map(toBlock);
}

function toBlock(source: PatternBlock): Block {
    const block: Block = {
        id: newBlockId(),
        type: source.type,
        attrs: structuredClone(source.attrs ?? {}),
    };

    if (source.content !== undefined) {
        block.content = source.content;
    }

    if (source.children !== undefined) {
        block.children = source.children.map(toBlock);
    }

    return block;
}

// Adds several blocks after the block with the given id, or at the end.
export function insertBlocksAfter(
    blocks: Block[],
    afterId: string | null,
    added: Block[],
): Block[] {
    const index =
        afterId === null ? -1 : blocks.findIndex((item) => item.id === afterId);
    const next = [...blocks];
    next.splice(index === -1 ? blocks.length : index + 1, 0, ...added);

    return next;
}

// Patterns whose title, category, description, name or keywords match every word of the query.
export function searchPatterns(
    patterns: BlockPattern[],
    query: string,
): BlockPattern[] {
    if (normalizeSearchText(query) === '') {
        return patterns;
    }

    return patterns.filter((pattern) =>
        matchesSearch(
            normalizeSearchText(
                [
                    pattern.title,
                    pattern.category,
                    pattern.description,
                    pattern.name,
                    ...pattern.keywords,
                ].join(' '),
            ),
            query,
        ),
    );
}

// Puts patterns of the same category together, keeping their first order.
export function groupPatterns(patterns: BlockPattern[]): PatternGroup[] {
    const groups = new Map<string, BlockPattern[]>();

    for (const pattern of patterns) {
        const list = groups.get(pattern.category);

        if (list) {
            list.push(pattern);
        } else {
            groups.set(pattern.category, [pattern]);
        }
    }

    return Array.from(groups, ([category, list]) => ({
        category,
        patterns: list,
    }));
}
