import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Field } from '@/components/verso/site/settings-fields';
import { SearchableSelect } from '@/components/verso/ui/searchable-select';

// Every control in a form cell fills the cell, so the edges line up.
describe('Field controls', () => {
    it('lets a text input fill the field cell', () => {
        render(
            <Field label="Site name" id="site-name">
                <Input id="site-name" defaultValue="Harbor Lane Journal" />
            </Field>,
        );

        const input = screen.getByLabelText('Site name');
        const field = input.parentElement;

        expect(field).toHaveClass('grid', 'min-w-0');
        expect(input).toHaveClass('w-full');
        expect(input.className).not.toMatch(/max-w-/);
    });

    it('gives selects, searchable selects and text areas the full width too', () => {
        render(
            <>
                <Field label="Language" id="language">
                    <Select defaultValue="pt">
                        <SelectTrigger id="language">
                            <SelectValue />
                        </SelectTrigger>
                    </Select>
                </Field>
                <Field label="Timezone" id="timezone">
                    <SearchableSelect
                        id="timezone"
                        value="Europe/Lisbon"
                        onValueChange={() => undefined}
                        options={[
                            { value: 'Europe/Lisbon', label: 'Lisbon' },
                            { value: 'Europe/Madrid', label: 'Madrid' },
                        ]}
                    />
                </Field>
                <Field label="Footer note" id="footer-note">
                    <Textarea id="footer-note" />
                </Field>
            </>,
        );

        expect(screen.getByLabelText('Language')).toHaveClass('w-full');
        expect(
            screen
                .getByLabelText('Timezone')
                .closest('[data-slot="searchable-select"]'),
        ).toHaveClass('w-full');
        expect(screen.getByLabelText('Footer note')).toHaveClass('w-full');
    });
});
