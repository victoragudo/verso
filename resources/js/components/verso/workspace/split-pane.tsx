import { useRef, useState } from 'react';
import type {
    CSSProperties,
    KeyboardEvent,
    PointerEvent,
    ReactNode,
} from 'react';
import { useElementSize } from '@/hooks/use-element-size';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    clampSplit,
    defaultSplit,
    parseSplit,
    splitBounds,
    splitFromKey,
    splitFromPointer,
} from './split';
import { readStored, useWorkspaceKey, writeStored } from './workspace-storage';

type Props = {
    // Name of the saved size, like "theme-builder".
    name: string;
    startId: string;
    start: ReactNode;
    end: ReactNode;
    // The side shown on narrow screens, where only one side fits.
    shown: 'start' | 'end';
};

function isRtl(element: Element | null): boolean {
    return element ? getComputedStyle(element).direction === 'rtl' : false;
}

// Two panes side by side with a line between them that people drag or move with the arrow keys.
// The size is kept per user. Under 64rem of workspace width only one pane shows; the workspace shows tabs.
export function SplitPane({ name, startId, start, end, shown }: Props) {
    const storageKey = useWorkspaceKey(`split.${name}`);
    const [box, { width }] = useElementSize<HTMLDivElement>();
    const [saved, setSaved] = useState<number | null>(() =>
        parseSplit(readStored(storageKey)),
    );
    const [dragging, setDragging] = useState(false);
    const separator = useRef<HTMLDivElement>(null);
    const measured = width > 0 ? width : window.innerWidth;
    const split = clampSplit(saved ?? defaultSplit(measured), measured);
    const bounds = splitBounds(measured);

    const save = (next: number | null) => {
        setSaved(next);
        writeStored(storageKey, next === null ? null : next.toFixed(4));
    };

    const move = (event: PointerEvent<HTMLDivElement>) => {
        const rect = box.current?.getBoundingClientRect();

        if (!rect) {
            return;
        }

        setSaved(
            splitFromPointer({
                clientX: event.clientX,
                left: rect.left,
                width: rect.width,
                rtl: isRtl(box.current),
            }),
        );
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const next = splitFromKey({
            key: event.key,
            split,
            width: measured,
            rtl: isRtl(separator.current),
            big: event.shiftKey,
        });

        if (next === null) {
            return;
        }

        event.preventDefault();
        save(event.key === 'Enter' ? null : next);
    };

    return (
        <div
            ref={box}
            data-dragging={dragging ? '' : undefined}
            data-test="split-pane"
            className="flex min-h-0 min-w-0 flex-1 data-dragging:cursor-col-resize data-dragging:select-none data-dragging:[&_iframe]:pointer-events-none"
            style={{ '--split': `${split * 100}%` } as CSSProperties}
        >
            <div
                id={startId}
                data-shown={shown === 'start' ? '' : undefined}
                className="hidden min-h-0 min-w-0 flex-1 flex-col data-shown:flex @5xl/workspace:flex @5xl/workspace:flex-none @5xl/workspace:basis-(--split)"
            >
                {start}
            </div>
            <div
                ref={separator}
                role="separator"
                tabIndex={0}
                aria-orientation="vertical"
                aria-controls={startId}
                aria-label={t('Resize the editor and the preview')}
                aria-valuemin={Math.round(bounds.min * 100)}
                aria-valuemax={Math.round(bounds.max * 100)}
                aria-valuenow={Math.round(split * 100)}
                title={t(
                    'Drag to resize. Arrow keys move it, Enter or a double click resets it.',
                )}
                data-test="split-separator"
                onKeyDown={onKeyDown}
                onDoubleClick={() => save(null)}
                onPointerDown={(event) => {
                    if (event.button !== 0) {
                        return;
                    }

                    event.preventDefault();
                    event.currentTarget.setPointerCapture(event.pointerId);
                    setDragging(true);
                }}
                onPointerMove={(event) => dragging && move(event)}
                onPointerUp={(event) => {
                    if (!dragging) {
                        return;
                    }

                    event.currentTarget.releasePointerCapture(event.pointerId);
                    setDragging(false);
                    save(split);
                }}
                className={cn(
                    'group relative z-10 -mx-1 hidden w-2 shrink-0 cursor-col-resize touch-none justify-center outline-none @5xl/workspace:flex',
                    'focus-visible:ring-[3px] focus-visible:ring-ring/50',
                )}
            >
                <span
                    aria-hidden="true"
                    className="h-full w-px bg-border transition-colors group-hover:w-0.5 group-hover:bg-primary group-focus-visible:w-0.5 group-focus-visible:bg-primary in-data-dragging:bg-primary motion-reduce:transition-none"
                />
            </div>
            <div
                data-shown={shown === 'end' ? '' : undefined}
                className="hidden min-h-0 min-w-0 flex-1 flex-col data-shown:flex @5xl/workspace:flex"
            >
                {end}
            </div>
        </div>
    );
}
