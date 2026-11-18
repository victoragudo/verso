import { type RefObject, useEffect } from 'react';

// Buttons of the toolbar that can take focus, in DOM order.
export function toolbarItems(bar: HTMLElement): HTMLButtonElement[] {
    return Array.from(bar.querySelectorAll('button')).filter(
        (button) => !button.disabled && !button.closest('[hidden], [inert]'),
    );
}

// The item that gets focus for a key press, or null when the key is not handled.
export function nextToolbarIndex(
    key: string,
    current: number,
    count: number,
    rtl: boolean,
): number | null {
    if (count === 0) {
        return null;
    }

    const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
    const backward = rtl ? 'ArrowRight' : 'ArrowLeft';

    switch (key) {
        case forward:
            return (current + 1) % count;
        case backward:
            return (current - 1 + count) % count;
        case 'Home':
            return 0;
        case 'End':
            return count - 1;
        default:
            return null;
    }
}

// One tab stop for the whole toolbar with arrow key movement, used only when the browser has no native focusgroup.
export function useRovingToolbar(ref: RefObject<HTMLElement | null>): void {
    useEffect(() => {
        const bar = ref.current;

        if (!bar || 'focusGroup' in HTMLElement.prototype) {
            return;
        }

        const activate = (target: HTMLButtonElement | null) => {
            const items = toolbarItems(bar);
            const active =
                target && items.includes(target)
                    ? target
                    : (items.find((item) => item.tabIndex === 0) ?? items[0]);
            items.forEach((item) => {
                item.tabIndex = item === active ? 0 : -1;
            });
        };

        const onFocusIn = (event: FocusEvent) => {
            if (event.target instanceof HTMLButtonElement) {
                activate(event.target);
            }
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (!(event.target instanceof HTMLButtonElement)) {
                return;
            }

            const items = toolbarItems(bar);
            const next = nextToolbarIndex(
                event.key,
                items.indexOf(event.target),
                items.length,
                getComputedStyle(bar).direction === 'rtl',
            );

            if (next === null) {
                return;
            }

            event.preventDefault();
            items[next].focus();
        };

        activate(null);
        const observer = new MutationObserver(() => activate(null));
        observer.observe(bar, { childList: true, subtree: true });
        bar.addEventListener('focusin', onFocusIn);
        bar.addEventListener('keydown', onKeyDown);

        return () => {
            observer.disconnect();
            bar.removeEventListener('focusin', onFocusIn);
            bar.removeEventListener('keydown', onKeyDown);
        };
    }, [ref]);
}
