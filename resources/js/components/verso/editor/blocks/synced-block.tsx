import { ExternalLink, Unlink } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { library } from '@/builder/library';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { cloneBlock, newBlockId } from '../block-types';
import type { BlockFieldProps } from './shared';

// A synced block in the editor: its name, how many blocks it holds, a link to change it in the library,
// and "Turn into normal blocks" to keep a copy here that no longer follows the library.
export function SyncedBlockFields({ block, onChange }: BlockFieldProps) {
    const items = useSyncExternalStore(
        library.subscribe,
        library.all,
        library.all,
    );
    const id = Number(block.attrs?.ref);
    const item = items.find((entry) => entry.id === id);

    if (!item) {
        return (
            <p className="text-sm text-muted-foreground">
                {t(
                    'This synced block was deleted from the library. Visitors see nothing here.',
                )}
            </p>
        );
    }

    return (
        <div className="grid gap-2" data-test="synced-block">
            <p className="text-sm">
                <span className="font-medium">{item.title}</span>{' '}
                <span className="text-muted-foreground">
                    {t(
                        '{count, plural, one {# block} other {# blocks}}, the same on every page that uses it.',
                        {
                            count: item.blocks.length,
                        },
                    )}
                </span>
            </p>
            <div className="flex flex-wrap gap-1.5">
                {library.canSave() && (
                    <Button asChild type="button" variant="outline" size="sm">
                        <a
                            href={library.editUrl(item.id)}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink />
                            {t('Change it in the library')}
                        </a>
                    </Button>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        onChange({
                            id: newBlockId(),
                            type: 'section',
                            attrs: { tag: 'div', label: item.title },
                            content: '',
                            children: item.blocks.map(cloneBlock),
                        })
                    }
                >
                    <Unlink />
                    {t('Turn into normal blocks')}
                </Button>
            </div>
        </div>
    );
}
