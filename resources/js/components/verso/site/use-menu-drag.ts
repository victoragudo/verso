import { useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';

// Where a dragged menu item came from and where it would land now (an index in the flat list).
export type MenuDrag = { from: number; to: number };

type Options = {
    count: number;
    onDrop: (from: number, to: number) => void;
};

// Pointer drag for the menu builder handles: one pointer, captured on the handle,
// the drop spot found from the row midpoints, one move on release and Escape to cancel.
export function useMenuDrag({ count, onDrop }: Options) {
    const listRef = useRef<HTMLDivElement>(null);
    const pointer = useRef<number | null>(null);
    const [drag, setDrag] = useState<MenuDrag | null>(null);

    // The first row whose middle is below the pointer, or the end of the list.
    const spotAt = (clientY: number): number => {
        const rows =
            listRef.current?.querySelectorAll<HTMLElement>('[data-menu-row]');

        if (!rows) {
            return count;
        }

        for (let index = 0; index < rows.length; index += 1) {
            const box = rows[index].getBoundingClientRect();

            if (clientY < box.top + box.height / 2) {
                return index;
            }
        }

        return rows.length;
    };

    const end = () => {
        pointer.current = null;
        document.documentElement.classList.remove('is-dragging-menu');
        setDrag(null);
    };

    const handleProps = (index: number) => ({
        onPointerDown: (event: PointerEvent<HTMLButtonElement>) => {
            if (event.button !== 0 || pointer.current !== null) {
                return;
            }

            pointer.current = event.pointerId;
            event.currentTarget.setPointerCapture(event.pointerId);
            document.documentElement.classList.add('is-dragging-menu');
            setDrag({ from: index, to: index });
        },
        onPointerMove: (event: PointerEvent<HTMLButtonElement>) => {
            if (event.pointerId !== pointer.current) {
                return;
            }

            const to = spotAt(event.clientY);
            setDrag((current) =>
                current && current.to !== to ? { ...current, to } : current,
            );
        },
        onPointerUp: (event: PointerEvent<HTMLButtonElement>) => {
            if (event.pointerId !== pointer.current || !drag) {
                return;
            }

            const { from, to } = drag;
            end();

            if (to !== from && to !== from + 1) {
                onDrop(from, to);
            }
        },
        onPointerCancel: (event: PointerEvent<HTMLButtonElement>) => {
            if (event.pointerId === pointer.current) {
                end();
            }
        },
        onLostPointerCapture: () => {
            if (pointer.current !== null) {
                end();
            }
        },
        onKeyDown: (event: KeyboardEvent<HTMLButtonElement>) => {
            if (event.key === 'Escape' && drag) {
                event.preventDefault();
                end();
            }
        },
    });

    return { listRef, drag, handleProps };
}
