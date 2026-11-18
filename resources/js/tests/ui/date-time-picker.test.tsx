import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DatePicker, DateTimePicker } from '@/components/verso/ui';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

function PublishDate({ initial = '' }: { initial?: string }) {
    const [value, setValue] = useState(initial);

    return (
        <div>
            <label htmlFor="published_at">Publish date</label>
            <DateTimePicker
                id="published_at"
                value={value}
                onChange={setValue}
            />
            <output data-testid="sent">{value}</output>
        </div>
    );
}

function RenewalDay({ initial }: { initial: string }) {
    const [value, setValue] = useState(initial);

    return (
        <div>
            <label htmlFor="renews_on">Renews on</label>
            <DatePicker id="renews_on" value={value} onChange={setValue} />
            <output data-testid="sent">{value}</output>
        </div>
    );
}

afterEach(() => {
    document.documentElement.dir = '';
});

describe('DateTimePicker', () => {
    it('is a combobox named by its label that shows the picked value', () => {
        render(<PublishDate initial="2026-11-03T10:30" />);

        const trigger = screen.getByRole('combobox', { name: 'Publish date' });

        expect(trigger).toHaveAttribute('aria-haspopup', 'dialog');
        expect(trigger).toHaveAttribute('aria-expanded', 'false');
        expect(trigger).toHaveTextContent('2026');
        expect(trigger).toHaveTextContent('10:30');
    });

    it('moves hours with Home, End and Page keys', async () => {
        render(<PublishDate initial="2026-11-03T10:30" />);

        await userEvent.click(
            screen.getByRole('combobox', { name: 'Publish date' }),
        );
        const hours = screen.getByRole('spinbutton', { name: 'Hours' });

        expect(hours).toHaveAttribute('autocomplete', 'off');
        expect(screen.getByRole('group', { name: 'Time' })).toHaveAttribute(
            'dir',
            'ltr',
        );
        expect(hours).toHaveAttribute('inputmode', 'numeric');

        fireEvent.keyDown(hours, { key: 'End' });
        expect(hours).toHaveAttribute('aria-valuenow', '23');

        fireEvent.keyDown(hours, { key: 'Home' });
        expect(hours).toHaveAttribute('aria-valuenow', '0');

        fireEvent.keyDown(hours, { key: 'PageUp' });
        expect(hours).toHaveAttribute('aria-valuenow', '6');

        fireEvent.keyDown(hours, { key: 'PageDown' });
        fireEvent.keyDown(hours, { key: 'PageDown' });
        expect(hours).toHaveAttribute('aria-valuenow', '18');
    });

    it('moves minutes by a quarter hour with Page Up', async () => {
        render(<PublishDate initial="2026-11-03T10:30" />);

        await userEvent.click(
            screen.getByRole('combobox', { name: 'Publish date' }),
        );
        const minutes = screen.getByRole('spinbutton', { name: 'Minutes' });

        fireEvent.keyDown(minutes, { key: 'PageUp' });

        expect(minutes).toHaveAttribute('aria-valuenow', '45');
    });
});

describe('DatePicker arrow keys', () => {
    const openGrid = async () => {
        await userEvent.click(
            screen.getByRole('combobox', { name: 'Renews on' }),
        );

        return screen.getByRole('grid');
    };

    it('moves to the next day with the right arrow in left to right text', async () => {
        render(<RenewalDay initial="2026-11-10" />);

        const grid = await openGrid();
        fireEvent.keyDown(grid, { key: 'ArrowRight' });
        fireEvent.keyDown(grid, { key: 'Enter' });

        expect(screen.getByTestId('sent')).toHaveTextContent('2026-11-11');
    });

    it('moves to the next day with the left arrow in right to left text', async () => {
        document.documentElement.dir = 'rtl';
        render(<RenewalDay initial="2026-11-10" />);

        const grid = await openGrid();
        grid.style.direction = 'rtl';
        fireEvent.keyDown(grid, { key: 'ArrowLeft' });
        fireEvent.keyDown(grid, { key: 'Enter' });

        expect(screen.getByTestId('sent')).toHaveTextContent('2026-11-11');
    });
});
