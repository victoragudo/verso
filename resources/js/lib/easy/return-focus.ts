// Remembers what had focus when a dialog opened, also inside the page editor frame, and gives it back on close.
// Dialogs opened from a button inside the frame would otherwise send focus to the top of the page.

export type FocusMark = {
    element: HTMLElement | null;
    // The focused element inside a same origin frame, when the frame had focus.
    inner: HTMLElement | null;
};

export function markFocus(doc: Document = document): FocusMark {
    const element = doc.activeElement as HTMLElement | null;
    const inner =
        element instanceof HTMLIFrameElement
            ? ((element.contentDocument?.activeElement as HTMLElement | null) ??
              null)
            : null;

    return {
        element: element && element !== doc.body ? element : null,
        inner: inner && inner !== inner.ownerDocument.body ? inner : null,
    };
}

// Focuses the marked element again. False when it is gone, so the dialog can use its own default.
export function returnFocus(mark: FocusMark | null): boolean {
    const element = mark?.element;

    if (!element?.isConnected) {
        return false;
    }

    element.focus({ preventScroll: true });

    if (mark?.inner?.isConnected) {
        mark.inner.focus();
    }

    return true;
}
