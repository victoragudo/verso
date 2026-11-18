import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { Field } from '@/components/verso/install/fields';
import { OptionSelect } from '@/components/verso/ui/option-select';
import type { SelectOption } from '@/components/verso/ui/option-select';
import { pickOption } from '@/tests/pick-option';

const ENCRYPTIONS: SelectOption[] = [
    { value: 'none', label: 'None' },
    { value: 'tls', label: 'TLS' },
    { value: 'ssl', label: 'SSL' },
];

const TIMEZONES: SelectOption[] = [
    'Africa/Lagos',
    'America/Bogota',
    'America/Toronto',
    'Asia/Kolkata',
    'Asia/Tokyo',
    'Atlantic/Azores',
    'Australia/Perth',
    'Europe/Lisbon',
    'Europe/Madrid',
    'Europe/Oslo',
    'Pacific/Auckland',
    'Pacific/Fiji',
].map((zone) => ({
    value: zone,
    label: zone.replace('_', ' '),
    group: zone.split('/')[0],
}));

function Picker({
    options,
    initial,
    onPick = () => undefined,
    name,
}: {
    options: SelectOption[];
    initial: string;
    onPick?: (value: string) => void;
    name?: string;
}) {
    const [value, setValue] = useState(initial);

    return (
        <form aria-label="Mail settings">
            <label htmlFor="mail-choice">Encryption</label>
            <OptionSelect
                id="mail-choice"
                name={name}
                value={value}
                options={options}
                placeholder="Pick one"
                onValueChange={(next) => {
                    setValue(next);
                    onPick(next);
                }}
            />
        </form>
    );
}

const formValues = () =>
    Object.fromEntries(
        new FormData(
            screen.getByRole('form', {
                name: 'Mail settings',
            }) as HTMLFormElement,
        ),
    );

describe('OptionSelect', () => {
    it('uses the admin select for a short list, named by its label', async () => {
        const onPick = vi.fn();
        render(<Picker options={ENCRYPTIONS} initial="tls" onPick={onPick} />);

        const trigger = screen.getByRole('combobox', { name: 'Encryption' });

        expect(trigger.tagName).toBe('BUTTON');
        expect(trigger).toHaveAttribute('data-slot', 'select-trigger');
        expect(trigger).toHaveTextContent('TLS');

        await pickOption('Encryption', 'SSL');

        expect(onPick).toHaveBeenCalledWith('ssl');
        expect(trigger).toHaveTextContent('SSL');
    });

    it('uses the select with a search box for a long list, with groups', async () => {
        const onPick = vi.fn();
        render(
            <Picker
                options={TIMEZONES}
                initial="Europe/Lisbon"
                onPick={onPick}
            />,
        );

        const trigger = screen.getByRole('combobox', { name: 'Encryption' });
        expect(trigger.closest('[data-slot="searchable-select"]')).not.toBe(
            null,
        );

        await userEvent.click(trigger);
        await userEvent.type(
            screen.getByRole('combobox', { name: 'Search' }),
            'tokyo',
        );
        await userEvent.click(
            screen.getByRole('option', { name: 'Asia/Tokyo' }),
        );

        expect(onPick).toHaveBeenCalledWith('Asia/Tokyo');
    });

    it('allows an empty choice, which the Radix select does not', async () => {
        const onPick = vi.fn();
        render(
            <Picker
                options={[{ value: '', label: 'Default' }, ...ENCRYPTIONS]}
                initial=""
                onPick={onPick}
            />,
        );

        const trigger = screen.getByRole('combobox', { name: 'Encryption' });
        expect(trigger).toHaveTextContent('Default');

        await pickOption('Encryption', 'TLS');
        await pickOption('Encryption', 'Default');

        expect(onPick).toHaveBeenLastCalledWith('');
        expect(trigger).toHaveTextContent('Default');
    });

    it('shows the placeholder when the value has no option', () => {
        render(<Picker options={ENCRYPTIONS} initial="starttls" />);

        expect(
            screen.getByRole('combobox', { name: 'Encryption' }),
        ).toHaveTextContent('Pick one');
    });

    it('keeps the normal select when a long list has a disabled option', async () => {
        const options = TIMEZONES.map((zone) => ({
            ...zone,
            disabled: zone.value === 'Pacific/Fiji',
        }));
        render(<Picker options={options} initial="Europe/Oslo" />);

        await userEvent.click(
            screen.getByRole('combobox', { name: 'Encryption' }),
        );

        expect(
            screen.getByRole('option', { name: 'Pacific/Fiji' }),
        ).toHaveAttribute('aria-disabled', 'true');
        expect(screen.getByRole('group', { name: 'Pacific' })).toBeVisible();
    });

    it('posts the value with the form through a hidden input', async () => {
        render(
            <Picker options={ENCRYPTIONS} initial="tls" name="mail_encryption" />,
        );

        expect(formValues()).toMatchObject({ mail_encryption: 'tls' });

        await pickOption('Encryption', 'None');

        expect(formValues()).toMatchObject({ mail_encryption: 'none' });
    });

    it('takes the id, description and error of an install field', () => {
        render(
            <Field label="SSL mode" hint="Ask your host." error="Pick a mode.">
                <OptionSelect
                    value="prefer"
                    onValueChange={() => undefined}
                    options={['disable', 'prefer', 'require'].map((mode) => ({
                        value: mode,
                        label: mode,
                    }))}
                />
            </Field>,
        );

        const trigger = screen.getByRole('combobox', { name: 'SSL mode' });

        expect(trigger).toHaveAttribute('aria-invalid', 'true');
        expect(trigger).toHaveAccessibleDescription('Ask your host. Pick a mode.');
    });
});
