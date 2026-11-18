import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const STATUSES = [
    { value: 'pending', label: 'Pending' },
    { value: 'paid', label: 'Paid' },
    { value: 'shipped', label: 'Shipped' },
];

function OrderStatus({
    initial = '',
    onPick = () => undefined,
}: {
    initial?: string;
    onPick?: (value: string) => void;
}) {
    const [status, setStatus] = useState(initial);

    return (
        <div>
            <label htmlFor="order-status">Order status</label>
            <Select
                value={status}
                onValueChange={(value) => {
                    setStatus(value);
                    onPick(value);
                }}
            >
                <SelectTrigger id="order-status" aria-describedby="status-hint">
                    <SelectValue placeholder="Pick a status" />
                </SelectTrigger>
                <SelectContent>
                    {STATUSES.map((status) => (
                        <SelectItem key={status.value} value={status.value}>
                            {status.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <p id="status-hint">Customers get an email when it changes.</p>
        </div>
    );
}

function supportBaseSelect(supported: boolean) {
    vi.spyOn(CSS, 'supports').mockImplementation(
        (property: string, value?: string) =>
            supported && property === 'appearance' && value === 'base-select',
    );
}

afterEach(() => vi.restoreAllMocks());

describe('Select with the customizable select', () => {
    it('renders a native select named by its label', () => {
        supportBaseSelect(true);
        render(<OrderStatus initial="paid" />);

        const select = screen.getByRole('combobox', { name: 'Order status' });

        expect(select.tagName).toBe('SELECT');
        expect(select).toHaveValue('paid');
        expect(select).toHaveAttribute('data-slot', 'select-trigger');
        expect(select).toHaveAccessibleDescription(
            'Customers get an email when it changes.',
        );
        expect(
            screen.getAllByRole('option').map((option) => option.textContent),
        ).toEqual(['Pending', 'Paid', 'Shipped']);
    });

    it('shows the placeholder while no option is picked', () => {
        supportBaseSelect(true);
        render(<OrderStatus />);

        const select = screen.getByRole('combobox', { name: 'Order status' });
        const placeholder = select.querySelector('option[value=""]');

        expect(select).toHaveAttribute('data-placeholder');
        expect(placeholder).toHaveTextContent('Pick a status');
        expect(placeholder).toHaveAttribute('hidden');
        expect(placeholder).toBeDisabled();
    });

    it('sends the picked value', async () => {
        supportBaseSelect(true);
        const onPick = vi.fn();
        render(<OrderStatus initial="pending" onPick={onPick} />);

        const select = screen.getByRole('combobox', { name: 'Order status' });
        await userEvent.selectOptions(select, 'Shipped');

        expect(onPick).toHaveBeenCalledWith('shipped');
        expect(select).toHaveValue('shipped');
        expect(select).not.toHaveAttribute('data-placeholder');
    });
});

describe('Select without the customizable select', () => {
    it('keeps the Radix select', () => {
        supportBaseSelect(false);
        render(<OrderStatus initial="paid" />);

        const trigger = screen.getByRole('combobox', { name: 'Order status' });

        expect(trigger.tagName).toBe('BUTTON');
        expect(trigger).toHaveTextContent('Paid');
    });
});
