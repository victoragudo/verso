import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import {
    composeLength,
    LengthInput,
    unitName,
    unitOptions,
} from '@/components/verso/ui/length-input';
import { cssValueRule } from '@/lib/css-value';

// Keeps the value like a real form does, so the field sees what it sent.
function Harness({
    initial,
    onChange,
    ...props
}: {
    initial?: unknown;
    onChange: (value: string | undefined) => void;
} & Partial<Parameters<typeof LengthInput>[0]>) {
    const [value, setValue] = useState<unknown>(initial);

    return (
        <>
            <label htmlFor="card-gap">Card gap</label>
            <LengthInput
                id="card-gap"
                label="Card gap"
                value={value}
                onChange={(next) => {
                    setValue(next);
                    onChange(next);
                }}
                {...props}
            />
        </>
    );
}

function field() {
    return screen.getByLabelText('Card gap') as HTMLInputElement;
}

function unitButton() {
    return screen.getByRole('combobox', { name: 'Card gap unit' });
}

async function pickUnit(search: string) {
    await userEvent.click(unitButton());
    await userEvent.type(
        screen.getByRole('combobox', { name: 'Search units' }),
        `${search}{Enter}`,
    );
}

describe('LengthInput', () => {
    it('shows a stored value as a number and a unit', () => {
        render(<Harness initial="1.5rem" onChange={vi.fn()} />);

        expect(field()).toHaveValue('1.5');
        expect(unitButton()).toHaveTextContent('rem');
    });

    it('shows an old plain number in pixels', () => {
        render(<Harness initial={24} onChange={vi.fn()} />);

        expect(field()).toHaveValue('24');
        expect(unitButton()).toHaveTextContent('px');
    });

    it('saves a typed number with the picked unit', () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} />);

        fireEvent.change(field(), { target: { value: '18' } });

        expect(onChange).toHaveBeenLastCalledWith('18px');
    });

    it('keeps the number when the unit changes', async () => {
        const onChange = vi.fn();
        render(<Harness initial="2rem" onChange={onChange} />);

        await pickUnit('cqi');

        expect(onChange).toHaveBeenLastCalledWith('2cqi');
        expect(field()).toHaveValue('2');
        expect(unitButton()).toHaveTextContent('cqi');
    });

    it('finds a unit by its plain name', async () => {
        const onChange = vi.fn();
        render(<Harness initial="10vh" onChange={onChange} />);

        await pickUnit('dynamic screen height');

        expect(onChange).toHaveBeenLastCalledWith('10dvh');
    });

    it('lists common units first and the rest in groups', async () => {
        render(<Harness onChange={vi.fn()} />);
        await userEvent.click(unitButton());
        const listbox = screen.getByRole('listbox');
        const groups = within(listbox)
            .getAllByRole('group')
            .map((group) => group.getAttribute('aria-labelledby'))
            .map((id) => document.getElementById(id!)?.textContent);

        expect(groups).toEqual([
            'Common',
            'Absolute',
            'Text relative',
            'Screen',
            'Container',
            'Other',
        ]);
        expect(
            within(listbox)
                .getAllByRole('option')
                .slice(0, 6)
                .map((option) => option.firstElementChild?.textContent),
        ).toEqual(['px', 'rem', 'em', '%', 'vw', 'vh']);
    });

    it('steps with the arrow keys and ten steps with Shift', () => {
        const onChange = vi.fn();
        render(<Harness initial="16px" onChange={onChange} />);

        fireEvent.keyDown(field(), { key: 'ArrowUp' });
        expect(onChange).toHaveBeenLastCalledWith('17px');

        fireEvent.keyDown(field(), { key: 'ArrowDown', shiftKey: true });
        expect(onChange).toHaveBeenLastCalledWith('7px');
    });

    it('steps rem in tenths', () => {
        const onChange = vi.fn();
        render(<Harness initial="1.2rem" onChange={onChange} />);

        fireEvent.keyDown(field(), { key: 'ArrowUp' });

        expect(onChange).toHaveBeenLastCalledWith('1.3rem');
    });

    it('does not step below zero when negatives are not allowed', () => {
        const onChange = vi.fn();
        render(
            <Harness
                initial="0.05rem"
                rule={{ negative: false }}
                onChange={onChange}
            />,
        );

        fireEvent.keyDown(field(), { key: 'ArrowDown' });

        expect(onChange).toHaveBeenLastCalledWith('0rem');
    });

    it('switches the unit when a full value is typed', () => {
        const onChange = vi.fn();
        render(<Harness initial="4px" onChange={onChange} />);

        fireEvent.change(field(), { target: { value: '3vmin' } });
        fireEvent.blur(field());

        expect(onChange).toHaveBeenLastCalledWith('3vmin');
        expect(field()).toHaveValue('3');
        expect(unitButton()).toHaveTextContent('vmin');
    });

    it('accepts a custom clamp() value', () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} />);

        fireEvent.change(field(), {
            target: { value: 'clamp(1rem,2vw+1rem,3rem)' },
        });
        fireEvent.blur(field());

        expect(onChange).toHaveBeenLastCalledWith(
            'clamp(1rem, 2vw + 1rem, 3rem)',
        );
        expect(unitButton()).toHaveTextContent('Custom');
    });

    it('shows the error only after leaving the field and never saves an unsafe value', () => {
        const onChange = vi.fn();
        render(<Harness initial="8px" onChange={onChange} />);

        fireEvent.change(field(), {
            target: { value: 'calc(1px); background: url(x)' },
        });
        expect(field()).not.toHaveAttribute('aria-invalid');

        fireEvent.blur(field());

        expect(onChange).not.toHaveBeenCalled();
        expect(field()).toHaveAttribute('aria-invalid', 'true');
        expect(field()).toHaveAccessibleDescription(
            /Use a size like 16px, 1.5rem or 50%/,
        );
    });

    it('goes back to the saved value with Escape', () => {
        render(<Harness initial="8px" onChange={vi.fn()} />);

        fireEvent.change(field(), { target: { value: 'calc(' } });
        fireEvent.keyDown(field(), { key: 'Escape' });

        expect(field()).toHaveValue('8');
    });

    it('clears the value when the field is emptied', () => {
        const onChange = vi.fn();
        render(<Harness initial="8px" onChange={onChange} />);

        fireEvent.change(field(), { target: { value: '' } });

        expect(onChange).toHaveBeenLastCalledWith(undefined);
    });

    it('picks a keyword the property allows', async () => {
        const onChange = vi.fn();
        render(
            <Harness
                initial="40rem"
                rule={{ keywords: ['none'] }}
                onChange={onChange}
            />,
        );

        await pickUnit('none');

        expect(onChange).toHaveBeenLastCalledWith('none');
        expect(field()).toHaveValue('none');
    });

    it('offers no unit for line height and keeps plain numbers', async () => {
        const onChange = vi.fn();
        render(
            <Harness
                initial={1.6}
                rule={{ unitless: true, keywords: ['normal'] }}
                onChange={onChange}
            />,
        );

        expect(unitButton()).toHaveTextContent('No unit');

        fireEvent.keyDown(field(), { key: 'ArrowUp' });
        expect(onChange).toHaveBeenLastCalledWith('1.7');

        await pickUnit('lh');
        expect(onChange).toHaveBeenLastCalledWith('1.7lh');
    });

    it('starts an empty line height without a unit', () => {
        render(
            <Harness
                rule={{ unitless: true, keywords: ['normal'] }}
                onChange={vi.fn()}
            />,
        );

        expect(unitButton()).toHaveTextContent('No unit');
    });

    it('accepts design tokens when asked', () => {
        const onChange = vi.fn();
        render(<Harness tokens onChange={onChange} />);

        fireEvent.change(field(), { target: { value: 'var:spacing.md' } });

        expect(onChange).toHaveBeenLastCalledWith('var:spacing.md');
    });

    it('offers angle units for an angle rule', async () => {
        render(<Harness rule={{ type: 'angle' }} onChange={vi.fn()} />);
        await userEvent.click(unitButton());

        expect(
            within(screen.getByRole('listbox'))
                .getAllByRole('option')
                .map((option) => option.textContent),
        ).toEqual([
            expect.stringMatching(/^deg/),
            expect.stringMatching(/^rad/),
            expect.stringMatching(/^grad/),
            expect.stringMatching(/^turn/),
            expect.stringMatching(/^Custom/),
        ]);
    });
});

describe('composeLength', () => {
    const length = cssValueRule();

    it.each([
        ['12', 'px', '12px'],
        ['12', 'rem', '12rem'],
        ['-1.5', 'em', '-1.5em'],
        ['', 'px', ''],
        ['2rem', 'px', '2rem'],
        ['12', ':custom', '12px'],
        ['1.', 'px', null],
        ['12 apples', 'px', null],
    ])('%j with %s gives %j', (text, pick, expected) => {
        expect(composeLength(text, pick, length)).toBe(expected);
    });
});

describe('unitOptions', () => {
    it('names viewport variants in plain words', () => {
        expect(unitName('svh')).toBe('screen height, small');
        expect(unitName('dvmax')).toBe('bigger screen side, dynamic');
        expect(unitName('Q')).toBe('quarter millimeters');
    });

    it('lists every length unit once', () => {
        const values = unitOptions(cssValueRule()).map(
            (option) => option.value,
        );

        expect(new Set(values).size).toBe(values.length);
        expect(values).toHaveLength(51);
    });
});
