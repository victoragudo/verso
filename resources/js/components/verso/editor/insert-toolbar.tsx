import { EllipsisVertical } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BuilderBlock } from '@/builder';
import { labelInSentence } from '@/components/verso/editor/blocks/shared';
import { t } from '@/lib/i18n';

type InsertToolbarProps = {
    types: BuilderBlock[];
    onInsert: (definition: BuilderBlock) => void;
};

// Shared look of every icon button in the editor toolbar.
export const TOOLBAR_BUTTON_CLASS =
    'size-8 shrink-0 p-0 text-zinc-600 hover:text-zinc-900 pointer-coarse:size-10 dark:text-zinc-400 dark:hover:text-zinc-100';

// Splits block types into toolbar buttons and the overflow menu, keeping their order.
// A block registered with "primary: true" gets a button.
export function splitBlockTypes(types: BuilderBlock[]): {
    primary: BuilderBlock[];
    core: BuilderBlock[];
    plugins: BuilderBlock[];
} {
    return {
        primary: types.filter((type) => type.primary),
        core: types.filter((type) => !type.primary && !type.fields),
        plugins: types.filter((type) => !type.primary && type.fields),
    };
}

// Header buttons that add a block under the cursor, with the less common blocks in a menu.
export function InsertToolbar({ types, onInsert }: InsertToolbarProps) {
    const { primary, core, plugins } = splitBlockTypes(types);

    const menuItem = (definition: BuilderBlock) => {
        const Icon = definition.icon;

        return (
            <DropdownMenuItem
                key={definition.type}
                data-test={`insert-${definition.type}`}
                onSelect={() => onInsert(definition)}
            >
                <Icon aria-hidden="true" />
                {t(definition.label)}
            </DropdownMenuItem>
        );
    };

    return (
        <TooltipProvider delayDuration={300}>
            <div
                role="group"
                aria-label={t('Insert block')}
                className="flex shrink-0 items-center gap-0.5"
            >
                {primary.map((definition) => {
                    const Icon = definition.icon;

                    return (
                        <Tooltip key={definition.type}>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    aria-label={t('Insert {block}', {
                                        block: labelInSentence(
                                            definition.label,
                                        ),
                                    })}
                                    data-test={`insert-${definition.type}`}
                                    className={TOOLBAR_BUTTON_CLASS}
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={() => onInsert(definition)}
                                >
                                    <Icon aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                {t(definition.label)}
                            </TooltipContent>
                        </Tooltip>
                    );
                })}
                {core.length + plugins.length > 0 && (
                    <DropdownMenu>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        aria-label={t('More blocks')}
                                        data-test="insert-more"
                                        className={TOOLBAR_BUTTON_CLASS}
                                        onMouseDown={(event) =>
                                            event.preventDefault()
                                        }
                                    >
                                        <EllipsisVertical aria-hidden="true" />
                                    </Button>
                                </DropdownMenuTrigger>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                {t('More blocks')}
                            </TooltipContent>
                        </Tooltip>
                        <DropdownMenuContent
                            align="start"
                            className="max-h-[min(24rem,var(--radix-dropdown-menu-content-available-height))] w-56"
                            onCloseAutoFocus={(event) => event.preventDefault()}
                        >
                            {core.map(menuItem)}
                            {plugins.length > 0 && (
                                <>
                                    {core.length > 0 && (
                                        <DropdownMenuSeparator />
                                    )}
                                    <DropdownMenuLabel className="text-xs font-medium tracking-wide text-zinc-500 uppercase">
                                        {t('Plugins')}
                                    </DropdownMenuLabel>
                                    {plugins.map(menuItem)}
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </TooltipProvider>
    );
}

export default InsertToolbar;
