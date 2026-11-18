import { render, renderHook, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useReturnFocus } from '@/hooks/use-return-focus';

function closeEvent(): Event {
    return new Event('focusOutside', { cancelable: true });
}

describe('useReturnFocus', () => {
    it('sends focus back to the element that opened the overlay', () => {
        render(
            <>
                <button type="button">Open navigation</button>
                <input aria-label="Search admin pages" />
            </>,
        );
        const trigger = screen.getByRole('button', { name: 'Open navigation' });
        const { result } = renderHook(() => useReturnFocus());

        trigger.focus();
        result.current.remember();
        screen.getByLabelText('Search admin pages').focus();

        const event = closeEvent();
        result.current.restore(event);

        expect(trigger).toHaveFocus();
        expect(event.defaultPrevented).toBe(true);
    });

    it('lets the overlay do its default when nothing had focus', () => {
        const { result } = renderHook(() => useReturnFocus());

        (document.activeElement as HTMLElement | null)?.blur();
        result.current.remember();

        const event = closeEvent();
        result.current.restore(event);

        expect(event.defaultPrevented).toBe(false);
    });

    it('skips an element that left the page', () => {
        const { unmount } = render(
            <button type="button">Search admin pages</button>,
        );
        const { result } = renderHook(() => useReturnFocus());

        screen.getByRole('button', { name: 'Search admin pages' }).focus();
        result.current.remember();
        unmount();

        const event = closeEvent();
        result.current.restore(event);

        expect(event.defaultPrevented).toBe(false);
    });
});
