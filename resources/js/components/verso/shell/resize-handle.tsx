import { useRef } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { widthForKey } from '@/hooks/use-resizable-width';
import type { WidthLimits } from '@/hooks/use-resizable-width';

type Props = {
    width: number;
    limits: WidthLimits;
    label: string;
    onResize: (width: number) => void;
    onReset: () => void;
    // For tests and styles: which panel this handle resizes.
    slot: string;
};

function isRtl(element: HTMLElement): boolean {
    return getComputedStyle(element).direction === 'rtl';
}

// The thin handle on the end edge of a panel on the start side. Drag it, or focus it and use the arrow keys.
// The parent needs "position: relative".
export function ResizeHandle({
    width,
    limits,
    label,
    onResize,
    onReset,
    slot,
}: Props) {
    const start = useRef<{ x: number; width: number } | null>(null);

    const onPointerDown = (event: PointerEvent<HTMLDivElement>) => {
        if (event.button !== 0) {
            return;
        }

        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        start.current = { x: event.clientX, width };
    };

    const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
        if (
            start.current === null ||
            !event.currentTarget.hasPointerCapture(event.pointerId)
        ) {
            return;
        }

        const moved = event.clientX - start.current.x;
        onResize(
            start.current.width + (isRtl(event.currentTarget) ? -moved : moved),
        );
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const next = widthForKey(
            limits,
            width,
            event.key,
            event.shiftKey,
            isRtl(event.currentTarget),
        );

        if (next !== null) {
            event.preventDefault();
            onResize(next);
        }
    };

    return (
        <div
            role="separator"
            aria-orientation="vertical"
            aria-label={label}
            aria-valuemin={limits.min}
            aria-valuemax={limits.max}
            aria-valuenow={width}
            tabIndex={0}
            data-slot={slot}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onLostPointerCapture={() => {
                start.current = null;
            }}
            onDoubleClick={onReset}
            onKeyDown={onKeyDown}
            className="group absolute inset-y-0 -end-1.5 z-10 w-3 cursor-col-resize touch-none outline-none select-none"
        >
            {/* The visible line: shown on hover, while dragging and on keyboard focus. */}
            <span
                aria-hidden="true"
                className="absolute inset-y-0 start-1/2 w-0.5 -translate-x-1/2 bg-transparent transition-colors group-hover:bg-primary/40 group-focus-visible:bg-primary group-active:bg-primary motion-reduce:transition-none rtl:translate-x-1/2"
            />
        </div>
    );
}
