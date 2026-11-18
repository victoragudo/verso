import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useRef } from 'react';
import { describe, expect, it } from 'vitest';
import { nextToolbarIndex, useRovingToolbar } from '@/hooks/use-roving-toolbar';

function FormattingBar() {
    const ref = useRef<HTMLDivElement>(null);
    useRovingToolbar(ref);

    return (
        <>
            <button type="button">Before</button>
            <div ref={ref} role="toolbar" aria-label="Formatting">
                <button type="button">Bold</button>
                <button type="button">Italic</button>
                <button type="button">Link</button>
            </div>
            <button type="button">After</button>
        </>
    );
}

describe('nextToolbarIndex', () => {
    it('moves forward and wraps at the end', () => {
        expect(nextToolbarIndex('ArrowRight', 0, 3, false)).toBe(1);
        expect(nextToolbarIndex('ArrowRight', 2, 3, false)).toBe(0);
    });

    it('moves backward and wraps at the start', () => {
        expect(nextToolbarIndex('ArrowLeft', 0, 3, false)).toBe(2);
    });

    it('reverses the arrows for right to left text', () => {
        expect(nextToolbarIndex('ArrowLeft', 0, 3, true)).toBe(1);
    });

    it('jumps with Home and End and ignores other keys', () => {
        expect(nextToolbarIndex('Home', 2, 3, false)).toBe(0);
        expect(nextToolbarIndex('End', 0, 3, false)).toBe(2);
        expect(nextToolbarIndex('ArrowDown', 0, 3, false)).toBeNull();
        expect(nextToolbarIndex('ArrowRight', 0, 0, false)).toBeNull();
    });
});

describe('useRovingToolbar', () => {
    it('gives the toolbar a single tab stop', async () => {
        render(<FormattingBar />);

        await userEvent.click(screen.getByRole('button', { name: 'Before' }));
        await userEvent.tab();
        expect(screen.getByRole('button', { name: 'Bold' })).toHaveFocus();

        await userEvent.tab();
        expect(screen.getByRole('button', { name: 'After' })).toHaveFocus();
    });

    it('moves between buttons with the arrow keys and remembers the last one', async () => {
        render(<FormattingBar />);

        await userEvent.click(screen.getByRole('button', { name: 'Bold' }));
        await userEvent.keyboard('{ArrowRight}');
        expect(screen.getByRole('button', { name: 'Italic' })).toHaveFocus();

        await userEvent.keyboard('{End}');
        expect(screen.getByRole('button', { name: 'Link' })).toHaveFocus();

        await userEvent.tab();
        await userEvent.tab({ shift: true });
        expect(screen.getByRole('button', { name: 'Link' })).toHaveFocus();
    });
});
