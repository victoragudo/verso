import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ThemeCustomize, { sizeValue } from '@/pages/admin/theme/customize';
import { cssValueRule } from '@/lib/css-value';
import type { ThemeSettingField } from '@/types';

// The shared mock has no useForm, so this file adds a small one kept in React state.
vi.mock('@inertiajs/react', async () => {
    const base = (await import('@/tests/mocks/inertia')).inertiaMock;
    const react = await import('react');

    return {
        ...base,
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setState] = react.useState(initial);

            return {
                data,
                setData: (key: keyof T, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                errors: {},
                processing: false,
                isDirty: false,
                put: vi.fn(),
            };
        },
    };
});

const layoutWidth: ThemeSettingField = {
    key: 'layout_width',
    type: 'size',
    label: 'Layout width',
    help: 'Widest size of the header, lists and footer.',
    default: 62,
    options: [],
    min: 48,
    max: 80,
    unit: 'rem',
    maxLength: 0,
    value: cssValueRule({ negative: false }),
};

const cardGap: ThemeSettingField = {
    key: 'card_gap',
    type: 'length',
    label: 'Card gap',
    help: '',
    default: '1.5rem',
    options: [],
    min: 0,
    max: 0,
    unit: 'px',
    maxLength: 0,
    value: cssValueRule({ units: ['px', 'rem', 'vw'], keywords: ['normal'] }),
};

function renderPage(values: Record<string, string | number | null>) {
    render(
        <ThemeCustomize
            theme={
                {
                    slug: 'default',
                    name: 'Verso Default',
                    version: '1.1.0',
                    description: 'A calm reading theme.',
                    author: 'The Verso contributors',
                    active: true,
                    parent: null,
                    parentName: null,
                } as never
            }
            fields={[layoutWidth, cardGap]}
            schemaErrors={[]}
            values={values}
            media={{}}
            fonts={{}}
        />,
    );
}

function previewSrc(): string {
    return decodeURIComponent(
        screen.getByTitle(/preview/i).getAttribute('src') ?? '',
    );
}

describe('ThemeCustomize sizes', () => {
    it('shows an old whole number in the unit of the field', () => {
        renderPage({ layout_width: 70, card_gap: null });

        expect(screen.getByLabelText('Layout width')).toHaveValue('70');
        expect(
            screen.getByRole('combobox', { name: 'Layout width unit' }),
        ).toHaveTextContent('rem');
        expect(screen.getByLabelText('Layout width in rem')).toHaveValue('70');
    });

    it('takes a size in another unit', async () => {
        renderPage({ layout_width: 70, card_gap: null });

        fireEvent.change(screen.getByLabelText('Layout width'), {
            target: { value: 'min(92vw, 1200px)' },
        });
        await waitFor(() =>
            expect(previewSrc().replace(/\+/g, ' ')).toContain(
                'values[layout_width]=min(92vw, 1200px)',
            ),
        );
    });

    it('offers only the units the theme lists for a length field', async () => {
        renderPage({ layout_width: 70, card_gap: '2rem' });

        await userEvent.click(
            screen.getByRole('combobox', { name: 'Card gap unit' }),
        );

        expect(
            screen
                .getAllByRole('option')
                .map((option) => option.firstElementChild?.textContent),
        ).toEqual(['px', 'rem', 'vw', 'normal', 'Custom']);
    });
});

describe('sizeValue', () => {
    it.each([
        [62, '62rem'],
        ['1100px', '1100px'],
        ['', undefined],
        [null, undefined],
    ])('%j gives %j', (value, expected) => {
        expect(sizeValue(value, layoutWidth)).toBe(expected);
    });
});
