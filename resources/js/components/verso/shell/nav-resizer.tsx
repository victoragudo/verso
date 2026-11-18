import { useRef } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import {
    NAV_WIDTH_MAX,
    NAV_WIDTH_MIN,
    navWidthForKey,
} from '@/hooks/use-admin-nav-width';
import { t } from '@/lib/i18n';

type Props = {
    width: number;
    onResize: (width: number) => void;
    onReset: () => void;
};

function isRtl(element: HTMLElement): boolean {
    return getComputedStyle(element).direction === 'rtl';
}

// The thin handle on the inner edge of the sidebar. Drag it, or focus it and use the arrow keys.
export function NavResizer({ width, onResize, onReset }: Props) {
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
        const next = navWidthForKey(
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
            aria-label={t(
                'Resize the menu. Use the arrow keys, or double click to reset.',
            )}
            aria-valuemin={NAV_WIDTH_MIN}
            aria-valuemax={NAV_WIDTH_MAX}
            aria-valuenow={width}
            tabIndex={0}
            data-slot="admin-nav-resizer"
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
