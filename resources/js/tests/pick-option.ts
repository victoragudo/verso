import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

type Name = string | RegExp;

const combobox = (select: Name | HTMLElement): HTMLElement =>
    select instanceof HTMLElement
        ? select
        : screen.getByRole('combobox', { name: select });

// Opens an admin select, found by its label or given, and clicks one option.
// Works for the normal select and for the select with a search box.
export async function pickOption(
    select: Name | HTMLElement,
    option: Name,
): Promise<void> {
    await userEvent.click(combobox(select));
    await userEvent.click(await screen.findByRole('option', { name: option }));
}
