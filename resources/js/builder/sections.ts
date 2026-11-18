import type { Block } from '@/types';
import type { ReadySection } from './types';

// The ready sections of Easy mode, offered in the Add panel. The server builds each one with fresh ids.
let sections: ReadySection[] = [];
let url: string | null = null;

export function setSections(
    next: ReadySection[],
    address: string | null,
): void {
    sections = next;
    url = address;
}

export function readySections(): ReadySection[] {
    return url === null ? [] : sections;
}

export async function sectionBlocks(key: string): Promise<Block[]> {
    if (url === null) {
        return [];
    }

    const response = await fetch(
        url.replace('section-key', encodeURIComponent(key)),
        {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    );

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    const body = (await response.json()) as { blocks?: Block[] };

    return body.blocks ?? [];
}
