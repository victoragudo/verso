import { currentLocale, t } from '@/lib/i18n';
import type { Block } from '@/types';

// Props shared by every block field editor.
export type BlockFieldProps = {
    block: Block;
    onChange: (block: Block) => void;
};

// A copy of the block with some attrs changed.
export function withAttrs(block: Block, attrs: Record<string, unknown>): Block {
    return { ...block, attrs: { ...block.attrs, ...attrs } };
}

// Blocks saved without attrs (like starter header blocks) read as empty.
export function stringAttr(block: Block, key: string): string {
    const value = block.attrs?.[key];

    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}

export function boolAttr(block: Block, key: string, fallback = false): boolean {
    const value = block.attrs?.[key];

    return typeof value === 'boolean' ? value : fallback;
}

const CAPITALIZED_NOUN_LOCALES = ['de'];

export function labelInSentence(label: string): string {
    const locale = currentLocale();
    const text = t(label);

    return CAPITALIZED_NOUN_LOCALES.includes(locale)
        ? text
        : text.toLocaleLowerCase(locale);
}
