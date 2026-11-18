import { ChevronRight } from 'lucide-react';
import { builder } from '@/builder';
import { t } from '@/lib/i18n';
import type { Block } from '@/types';

// The blocks from the page down to a block: [section, columns, column, paragraph].
export function blockPath(blocks: Block[], id: string | null): Block[] {
    if (id === null) {
        return [];
    }

    for (const block of blocks) {
        if (block.id === id) {
            return [block];
        }

        const inner = blockPath(block.children ?? [], id);

        if (inner.length > 0) {
            return [block, ...inner];
        }
    }

    return [];
}

type Props = {
    blocks: Block[];
    selectedId: string | null;
    onPick: (id: string) => void;
};

// Where the selected block sits: "Page › Section › Columns › Paragraph". Each step selects that block.
export function BlockBreadcrumb({ blocks, selectedId, onPick }: Props) {
    const path = blockPath(blocks, selectedId);

    if (path.length === 0) {
        return null;
    }

    return (
        <nav
            aria-label={t('Selected block')}
            className="border-b border-zinc-200 px-2 py-1 text-xs dark:border-zinc-800"
            data-test="block-breadcrumb"
        >
            <ol className="flex flex-wrap items-center gap-0.5">
                <li className="px-1.5 text-muted-foreground">{t('Page')}</li>
                {path.map((block, index) => {
                    const last = index === path.length - 1;
                    const label =
                        builder.blocks.get(block.type)?.label ?? block.type;

                    return (
                        <li
                            key={block.id}
                            className="flex items-center gap-0.5"
                        >
                            <ChevronRight
                                aria-hidden="true"
                                className="size-3 text-muted-foreground rtl:rotate-180"
                            />
                            <button
                                type="button"
                                aria-current={last ? 'location' : undefined}
                                onClick={() => onPick(block.id)}
                                className="min-h-6 rounded px-1.5 hover:bg-accent aria-[current=location]:font-semibold"
                            >
                                {t(label)}
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
