import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import {
    BLOCK_TYPES,
    searchBlockTypes,
} from '@/components/verso/editor/block-types';
import { SlashMenu } from '@/components/verso/editor/slash-menu';

function renderMenu(query: string, activeIndex = 0) {
    const onSelect = vi.fn();
    const onHover = vi.fn();
    const items = searchBlockTypes(query, BLOCK_TYPES);

    render(
        <SlashMenu
            items={items}
            activeIndex={activeIndex}
            onSelect={onSelect}
            onHover={onHover}
        />,
    );

    return { onSelect, onHover, items };
}

describe('SlashMenu', () => {
    it('lists each matching block with its description', () => {
        renderMenu('video');

        expect(screen.getAllByRole('option')).toHaveLength(2);
        expect(
            screen.getByText('A YouTube or Vimeo video.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('A video file from the media library.'),
        ).toBeInTheDocument();
    });

    it('marks only the active item as selected', () => {
        renderMenu('grid', 1);

        expect(
            screen
                .getAllByRole('option')
                .map((option) => option.getAttribute('aria-selected')),
        ).toEqual(['false', 'true', 'false']);
    });

    it('picks a block on click', async () => {
        const { onSelect, items } = renderMenu('tab');

        await userEvent.click(screen.getByRole('option', { name: /Table/ }));

        expect(onSelect).toHaveBeenCalledWith(items[0]);
    });

    it('reports the hovered item', async () => {
        const { onHover } = renderMenu('grid');

        await userEvent.hover(screen.getByRole('option', { name: /Table/ }));

        expect(onHover).toHaveBeenLastCalledWith(2);
    });

    it('keeps the focus in the editor when clicked', () => {
        renderMenu('code');

        const option = screen.getAllByRole('option')[0];
        const event = new MouseEvent('mousedown', {
            bubbles: true,
            cancelable: true,
        });

        option.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it('shows a message instead of a list when nothing matches', () => {
        renderMenu('newsletter');

        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
        expect(screen.getByText('No matching blocks')).toBeInTheDocument();
    });
});
