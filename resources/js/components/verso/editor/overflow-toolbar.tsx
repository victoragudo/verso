import { Ellipsis } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode, RefObject } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { visibleGroupKeys } from './toolbar-overflow';

// hideOrder: groups with a higher number move to the More menu first. By default the last group goes first.
// label: a short title shown above the group inside the More menu.
export type ToolbarGroup = {
    key: string;
    node: ReactNode;
    hideOrder?: number;
    label?: string;
};

type Props = {
    toolbarRef: RefObject<HTMLDivElement | null>;
    label: string;
    groups: ToolbarGroup[];
    className?: string;
};

// Space between groups, in pixels (gap-1).
const GROUP_GAP = 4;

// Width kept for the More button, in pixels.
const MORE_WIDTH = 36;

// A toolbar that never clips: the groups that do not fit in one row move, in order, to a More menu at the end.
export function OverflowToolbar({
    toolbarRef,
    label,
    groups,
    className,
}: Props) {
    const widths = useRef(new Map<string, number>());
    const [shownKeys, setShownKeys] = useState<string[] | null>(null);
    const [moreOpen, setMoreOpen] = useState(false);
    const keys = groups
        .map((group, index) => `${group.key}:${group.hideOrder ?? index}`)
        .join('|');
    const measureRef = useRef<() => void>(() => {});

    // Group widths can change after any render (a text block gets focus, a label shows), so measure after each one.
    useLayoutEffect(() => measureRef.current());

    useLayoutEffect(() => {
        const bar = toolbarRef.current;

        if (!bar) {
            return;
        }

        const measure = () => {
            bar.querySelectorAll<HTMLElement>('[data-toolbar-group]').forEach(
                (element) => {
                    widths.current.set(
                        element.dataset.toolbarGroup ?? '',
                        element.getBoundingClientRect().width,
                    );
                },
            );
            const style = getComputedStyle(bar);
            const available =
                bar.clientWidth -
                parseFloat(style.paddingInlineStart || '0') -
                parseFloat(style.paddingInlineEnd || '0');
            const known = keys.split('|').map((item) => {
                const [key, order] = item.split(':');

                return {
                    key,
                    width: widths.current.get(key) ?? 0,
                    hideOrder: Number(order),
                };
            });

            if (available <= 0) {
                return;
            }

            const next = visibleGroupKeys({
                available,
                groups: known,
                moreWidth: MORE_WIDTH,
                gap: GROUP_GAP,
            });
            setShownKeys((current) =>
                current !== null && current.join('|') === next.join('|')
                    ? current
                    : next,
            );
        };

        measureRef.current = measure;
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(bar);

        return () => observer.disconnect();
    }, [toolbarRef, keys]);

    const shown =
        shownKeys === null
            ? groups
            : groups.filter((group) => shownKeys.includes(group.key));
    const hidden = groups.filter((group) => !shown.includes(group));

    return (
        <div
            ref={toolbarRef}
            role="toolbar"
            aria-label={label}
            {...({ focusgroup: 'toolbar wrap' } as object)}
            data-test="editor-toolbar"
            className={cn('flex items-center gap-1 overflow-clip', className)}
        >
            {shown.map((group) => (
                <div
                    key={group.key}
                    data-toolbar-group={group.key}
                    className="flex shrink-0 items-center gap-1"
                >
                    {group.node}
                </div>
            ))}
            {hidden.length > 0 && (
                <Popover open={moreOpen} onOpenChange={setMoreOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="ms-auto size-8 shrink-0 p-0"
                            aria-label={t('More tools')}
                            title={t('More tools')}
                            data-test="toolbar-more"
                        >
                            <Ellipsis aria-hidden="true" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent
                        align="end"
                        sideOffset={6}
                        className="w-auto max-w-[min(26rem,calc(100vw-2rem))] p-0"
                    >
                        {/* Each hidden group is its own section; the bar separators are not needed here. */}
                        <div
                            // Focus the menu itself on open, so the first button does not open its tooltip over the menu.
                            data-autofocus
                            tabIndex={-1}
                            role="group"
                            aria-label={t('More tools')}
                            className="divide-y divide-zinc-100 outline-none dark:divide-zinc-800 [&_[role=separator]]:hidden"
                        >
                            {hidden.map((group) => (
                                <section
                                    key={group.key}
                                    aria-label={group.label}
                                    className="px-2 py-2"
                                >
                                    {group.label && (
                                        <p className="mb-1 px-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                            {group.label}
                                        </p>
                                    )}
                                    <div className="flex flex-wrap items-center gap-0.5">
                                        {group.node}
                                    </div>
                                </section>
                            ))}
                        </div>
                    </PopoverContent>
                </Popover>
            )}
        </div>
    );
}
