import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { PluginBlockFields } from '@/components/verso/editor/blocks/plugin-block';
import { cssValueRule } from '@/lib/css-value';
import type { Block, PluginBlockField } from '@/types';

function field(overrides: Partial<PluginBlockField>): PluginBlockField {
    return {
        name: 'headline',
        label: 'Headline',
        type: 'text',
        default: null,
        options: [],
        help: '',
        required: false,
        min: null,
        max: null,
        ...overrides,
    };
}

const fields: PluginBlockField[] = [
    field({
        name: 'headline',
        label: 'Headline',
        required: true,
        max: 80,
        help: 'Shown above the plans.',
    }),
    field({ name: 'intro', label: 'Intro', type: 'textarea', max: 400 }),
    field({
        name: 'plans',
        label: 'Plans',
        type: 'number',
        min: 1,
        max: 4,
    }),
    field({ name: 'signupUrl', label: 'Sign up link', type: 'url' }),
    field({ name: 'yearly', label: 'Show yearly prices', type: 'toggle' }),
    field({
        name: 'currency',
        label: 'Currency',
        type: 'select',
        options: [
            { value: 'eur', label: 'Euro' },
            { value: 'usd', label: 'US dollar' },
        ],
    }),
];

const block: Block = {
    id: 'b_pricing',
    type: 'pricing-table',
    attrs: {
        headline: 'Plans for every bakery',
        plans: 3,
        yearly: true,
        currency: 'eur',
    },
    content: '',
    children: [],
};

function renderFields(
    target: Block = block,
    list: PluginBlockField[] = fields,
) {
    const onChange = vi.fn<(block: Block) => void>();

    render(
        <PluginBlockFields block={target} fields={list} onChange={onChange} />,
    );

    return onChange;
}

describe('PluginBlockFields', () => {
    it('explains when the block has no settings', () => {
        renderFields(block, []);

        expect(
            screen.getByText(
                'This block has no settings. It is filled in on the site.',
            ),
        ).toBeInTheDocument();
    });

    it('builds one labelled input per field', () => {
        renderFields();

        expect(screen.getByLabelText(/Headline/)).toHaveAttribute(
            'type',
            'text',
        );
        expect(screen.getByLabelText('Intro').tagName).toBe('TEXTAREA');
        expect(screen.getByLabelText('Plans')).toHaveAttribute(
            'type',
            'number',
        );
        expect(screen.getByLabelText('Sign up link')).toHaveAttribute(
            'type',
            'url',
        );
        expect(
            screen.getByRole('checkbox', { name: 'Show yearly prices' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('combobox')).toBeInTheDocument();
    });

    it('fills the inputs with the current attrs', () => {
        renderFields();

        expect(screen.getByLabelText(/Headline/)).toHaveValue(
            'Plans for every bakery',
        );
        expect(screen.getByLabelText('Plans')).toHaveValue(3);
        expect(screen.getByLabelText('Intro')).toHaveValue('');
        expect(
            screen.getByRole('checkbox', { name: 'Show yearly prices' }),
        ).toBeChecked();
        expect(screen.getByRole('combobox')).toHaveTextContent('Euro');
    });

    it('shows empty inputs for a block saved without attrs', () => {
        const { attrs: _attrs, ...withoutAttrs } = block;

        renderFields(withoutAttrs as Block);

        expect(screen.getByLabelText(/Headline/)).toHaveValue('');
        expect(
            screen.getByRole('checkbox', { name: 'Show yearly prices' }),
        ).not.toBeChecked();
    });

    it('marks required fields with a star and the required attribute', () => {
        renderFields();

        expect(screen.getByText('*')).toBeInTheDocument();
        expect(screen.getByLabelText(/Headline/)).toBeRequired();
        expect(screen.getByLabelText('Intro')).not.toBeRequired();
    });

    it('shows the help text', () => {
        renderFields();

        expect(screen.getByText('Shown above the plans.')).toBeInTheDocument();
    });

    it('limits text length with max', () => {
        renderFields();

        expect(screen.getByLabelText(/Headline/)).toHaveAttribute(
            'maxLength',
            '80',
        );
        expect(screen.getByLabelText('Intro')).toHaveAttribute(
            'maxLength',
            '400',
        );
    });

    it('uses min and max as the number range', () => {
        renderFields();

        const plans = screen.getByLabelText('Plans');

        expect(plans).toHaveAttribute('min', '1');
        expect(plans).toHaveAttribute('max', '4');
        expect(plans).not.toHaveAttribute('maxLength');
    });

    it('saves typed text in the attrs', () => {
        const onChange = renderFields();

        fireEvent.change(screen.getByLabelText(/Headline/), {
            target: { value: 'Simple pricing' },
        });

        expect(onChange).toHaveBeenCalledWith({
            ...block,
            attrs: { ...block.attrs, headline: 'Simple pricing' },
        });
    });

    it('saves numbers as numbers', () => {
        const onChange = renderFields();

        fireEvent.change(screen.getByLabelText('Plans'), {
            target: { value: '2' },
        });

        expect(onChange.mock.lastCall?.[0].attrs.plans).toBe(2);
    });

    it('accepts decimal numbers', () => {
        const onChange = renderFields();

        fireEvent.change(screen.getByLabelText('Plans'), {
            target: { value: '2.5' },
        });

        expect(onChange.mock.lastCall?.[0].attrs.plans).toBe(2.5);
    });

    it('removes the attr when a text input is cleared', () => {
        const onChange = renderFields();

        fireEvent.change(screen.getByLabelText(/Headline/), {
            target: { value: '' },
        });

        expect(onChange.mock.lastCall?.[0].attrs).not.toHaveProperty(
            'headline',
        );
        expect(onChange.mock.lastCall?.[0].attrs).toMatchObject({
            plans: 3,
            currency: 'eur',
        });
    });

    it('removes the attr when a number is cleared', () => {
        const onChange = renderFields();

        fireEvent.change(screen.getByLabelText('Plans'), {
            target: { value: '' },
        });

        expect(onChange.mock.lastCall?.[0].attrs).not.toHaveProperty('plans');
    });

    it('removes the attr when the textarea is cleared', () => {
        const onChange = renderFields({
            ...block,
            attrs: { ...block.attrs, intro: 'Pick a plan.' },
        });

        fireEvent.change(screen.getByLabelText('Intro'), {
            target: { value: '' },
        });

        expect(onChange.mock.lastCall?.[0].attrs).not.toHaveProperty('intro');
    });

    it('saves the url as text', () => {
        const onChange = renderFields();

        fireEvent.change(screen.getByLabelText('Sign up link'), {
            target: { value: 'https://harborlane.example/join' },
        });

        expect(onChange.mock.lastCall?.[0].attrs.signupUrl).toBe(
            'https://harborlane.example/join',
        );
    });

    it('saves the toggle as a boolean', async () => {
        const onChange = renderFields();

        await userEvent.click(
            screen.getByRole('checkbox', { name: 'Show yearly prices' }),
        );

        expect(onChange.mock.lastCall?.[0].attrs.yearly).toBe(false);
    });

    it('treats a missing toggle attr as off', () => {
        renderFields({ ...block, attrs: {} });

        expect(
            screen.getByRole('checkbox', { name: 'Show yearly prices' }),
        ).not.toBeChecked();
    });

    it('shows a placeholder when no option is chosen', () => {
        renderFields({ ...block, attrs: {} });

        expect(screen.getByRole('combobox')).toHaveTextContent(
            'Choose an option',
        );
    });

    it('saves the chosen option', async () => {
        const onChange = renderFields();

        await userEvent.click(screen.getByRole('combobox'));
        await userEvent.click(
            await screen.findByRole('option', { name: 'US dollar' }),
        );

        expect(onChange.mock.lastCall?.[0].attrs.currency).toBe('usd');
    });

    it('gives every input an id that is unique to the block', () => {
        renderFields();

        expect(screen.getByLabelText('Intro')).toHaveAttribute(
            'id',
            'b_pricing-intro',
        );
    });

    it('shows a search box for a searchable select with few options', async () => {
        renderFields({ ...block, attrs: { form: 'contact' } }, [
            field({
                name: 'form',
                label: 'Form',
                type: 'select',
                searchable: true,
                options: [
                    { value: 'contact', label: 'Contact' },
                    { value: 'catering', label: 'Catering request' },
                ],
            }),
        ]);

        await userEvent.click(screen.getByRole('combobox', { name: 'Form' }));

        expect(
            await screen.findByPlaceholderText('Search options'),
        ).toBeInTheDocument();
    });

    it('edits a length field with a unit and keeps old pixel numbers', async () => {
        const height = field({
            name: 'height',
            label: 'Height',
            type: 'length',
            default: '44px',
            value: cssValueRule({ negative: false }),
        });
        const logo: Block = {
            ...block,
            type: 'site-logo',
            attrs: { height: 60 },
        };
        const onChange = renderFields(logo, [height]);

        expect(screen.getByLabelText('Height')).toHaveValue('60');
        expect(
            screen.getByRole('combobox', { name: 'Height unit' }),
        ).toHaveTextContent('px');

        await userEvent.click(
            screen.getByRole('combobox', { name: 'Height unit' }),
        );
        await userEvent.type(
            screen.getByRole('combobox', { name: 'Search units' }),
            'rem{Enter}',
        );

        expect(onChange).toHaveBeenLastCalledWith({
            ...logo,
            attrs: { height: '60rem' },
        });
    });
});
