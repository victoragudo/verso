import {
    ArrowDown,
    ArrowUp,
    ClipboardCopy,
    Copy,
    Group,
    LibraryBig,
    Trash,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';

export type SelectionActions = {
    moveUp?: () => void;
    moveDown?: () => void;
    duplicate: () => void;
    copy: () => void;
    group?: () => void;
    save?: () => void;
    remove: () => void;
    clear: () => void;
};

// Actions for blocks picked together. Shown under the editor toolbar while two or more blocks are picked.
export function SelectionBar({
    count,
    actions,
}: {
    count: number;
    actions: SelectionActions;
}) {
    return (
        <div
            role="toolbar"
            aria-label={t('Selected blocks')}
            data-test="selection-bar"
            className="flex flex-wrap items-center gap-1 border-b border-zinc-200 bg-primary/5 px-2 py-1.5 dark:border-zinc-800"
        >
            <span className="px-2 text-sm font-medium tabular-nums">
                {t(
                    '{count, plural, one {# block selected} other {# blocks selected}}',
                    { count },
                )}
            </span>
            <BarButton
                icon={ArrowUp}
                label={t('Move up')}
                onClick={actions.moveUp}
            />
            <BarButton
                icon={ArrowDown}
                label={t('Move down')}
                onClick={actions.moveDown}
            />
            <BarButton
                icon={Copy}
                label={t('Duplicate')}
                onClick={actions.duplicate}
            />
            <BarButton
                icon={ClipboardCopy}
                label={t('Copy')}
                onClick={actions.copy}
            />
            <BarButton
                icon={Group}
                label={t('Group in a section')}
                onClick={actions.group}
            />
            {actions.save && (
                <BarButton
                    icon={LibraryBig}
                    label={t('Save to the library')}
                    onClick={actions.save}
                />
            )}
            <BarButton
                icon={Trash}
                label={t('Delete')}
                onClick={actions.remove}
            />
            <Button
                type="button"
                variant="ghost"
                size="sm"
                className="ms-auto h-8 px-2"
                onClick={actions.clear}
            >
                <X />
                {t('Clear selection')}
            </Button>
        </div>
    );
}

function BarButton({
    icon: Icon,
    label,
    onClick,
}: {
    icon: LucideIcon;
    label: string;
    onClick?: () => void;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-8 px-2"
            disabled={!onClick}
            onClick={onClick}
        >
            <Icon />
            {label}
        </Button>
    );
}
