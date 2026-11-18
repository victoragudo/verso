import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { SearchableSelect } from '@/components/verso/ui/searchable-select';

const COUNTRIES = [
    'Argentina',
    'Brazil',
    'Canada',
    'Denmark',
    'Egypt',
    'Finland',
    'Germany',
    'Hungary',
    'India',
    'Japan',
    'Kenya',
    'Lebanon',
    'Mexico',
    'Norway',
    'Portugal',
].map((name) => ({ value: name.toLowerCase(), label: name }));

function ShippingCountry({ initial = 'canada' }: { initial?: string }) {
    const [country, setCountry] = useState(initial);

    return (
        <div>
            <label htmlFor="ship-country">Ship to</label>
            <SearchableSelect
                id="ship-country"
                value={country}
                onValueChange={setCountry}
                options={COUNTRIES}
                emptyText="No country matches."
            />
            <output data-testid="picked">{country}</output>
        </div>
    );
}

const openPanel = async () => {
    await userEvent.click(screen.getByRole('combobox', { name: 'Ship to' }));

    return screen.getByRole('combobox', { name: 'Search' });
};

const activeOption = (search: HTMLElement) =>
    document.getElementById(search.getAttribute('aria-activedescendant')!);

describe('SearchableSelect', () => {
    it('is a combobox named by its label that shows the picked option', () => {
        render(<ShippingCountry />);

        const trigger = screen.getByRole('combobox', { name: 'Ship to' });

        expect(trigger).toHaveTextContent('Canada');
        expect(trigger).toHaveAttribute('aria-haspopup', 'dialog');
        expect(trigger).toHaveAttribute('aria-expanded', 'false');
    });

    it('points the search box at the active option', async () => {
        render(<ShippingCountry />);

        const search = await openPanel();

        expect(search).toHaveAttribute('autocomplete', 'off');
        expect(search).toHaveAttribute('enterkeyhint', 'done');
        expect(activeOption(search)).toHaveTextContent('Canada');

        fireEvent.keyDown(search, { key: 'ArrowDown' });
        expect(activeOption(search)).toHaveTextContent('Denmark');
    });

    it('moves ten options with Page Down and Page Up', async () => {
        render(<ShippingCountry initial="argentina" />);

        const search = await openPanel();
        fireEvent.keyDown(search, { key: 'PageDown' });
        expect(activeOption(search)).toHaveTextContent('Kenya');

        fireEvent.keyDown(search, { key: 'PageDown' });
        expect(activeOption(search)).toHaveTextContent('Portugal');

        fireEvent.keyDown(search, { key: 'PageUp' });
        expect(activeOption(search)).toHaveTextContent('Egypt');
    });

    it('uses Home and End for the list only while the search is empty', async () => {
        render(<ShippingCountry />);

        const search = await openPanel();
        fireEvent.keyDown(search, { key: 'End' });
        expect(activeOption(search)).toHaveTextContent('Portugal');

        await userEvent.type(search, 'a');
        const before = activeOption(search)?.textContent;
        const home = fireEvent.keyDown(search, { key: 'Home' });

        expect(home).toBe(true);
        expect(activeOption(search)?.textContent).toBe(before);
    });

    it('announces when no option matches', async () => {
        render(<ShippingCountry />);

        const search = await openPanel();
        await userEvent.type(search, 'atlantis');

        expect(
            screen
                .getAllByRole('status')
                .some((status) => status.textContent === 'No country matches.'),
        ).toBe(true);
        expect(search).not.toHaveAttribute('aria-activedescendant');
    });

    it('picks the active option with Enter', async () => {
        render(<ShippingCountry />);

        const search = await openPanel();
        fireEvent.keyDown(search, { key: 'ArrowDown' });
        fireEvent.keyDown(search, { key: 'Enter' });

        expect(screen.getByTestId('picked')).toHaveTextContent('denmark');
    });
});
