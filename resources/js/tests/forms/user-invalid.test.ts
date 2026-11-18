import { fireEvent } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { installUserInvalidSync } from '@/lib/user-invalid';

// jsdom has no :user-invalid, so a field counts as used after it was changed and left.
const used = new WeakSet<Element>();
const nativeMatches = Object.getOwnPropertyDescriptor(
    Element.prototype,
    'matches',
)!.value as (this: Element, selector: string) => boolean;

function emailField(): { form: HTMLFormElement; input: HTMLInputElement } {
    document.body.innerHTML = `
        <form>
            <label for="invite-email">Email</label>
            <input id="invite-email" type="email" required>
            <button type="submit">Send invite</button>
        </form>`;

    uninstall = installUserInvalidSync();

    return {
        form: document.querySelector('form')!,
        input: document.querySelector('input')!,
    };
}

function announcer(): HTMLElement {
    return document.querySelector('[data-slot=validation-announcer]')!;
}

let uninstall: () => void = () => undefined;

beforeEach(() => {
    vi.useFakeTimers();
    vi.spyOn(Element.prototype, 'matches').mockImplementation(function (
        this: Element,
        selector: string,
    ) {
        if (selector === ':user-invalid') {
            return used.has(this) && !(this as HTMLInputElement).validity.valid;
        }

        return nativeMatches.call(this, selector);
    });
});

afterEach(() => {
    uninstall();
    vi.useRealTimers();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
});

describe('installUserInvalidSync', () => {
    it('marks a field valid when it gets focus before any use', () => {
        const { input } = emailField();

        fireEvent.focusIn(input);

        expect(input).toHaveAttribute('aria-invalid', 'false');
    });

    it('marks a used field invalid and announces its error with the label', () => {
        const { input } = emailField();

        fireEvent.focusIn(input);
        fireEvent.input(input, { target: { value: 'marta@' } });
        used.add(input);
        fireEvent.blur(input);

        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(announcer()).toHaveAttribute('aria-live', 'polite');
        expect(announcer()).toHaveTextContent('');

        vi.advanceTimersByTime(500);

        expect(announcer().textContent).toBe(
            `Email: ${input.validationMessage}`,
        );
    });

    it('clears the error and the announcement once the value is fixed', () => {
        const { input } = emailField();

        fireEvent.input(input, { target: { value: 'marta@' } });
        used.add(input);
        fireEvent.blur(input);
        fireEvent.input(input, { target: { value: 'marta@harborlane.es' } });

        expect(input).toHaveAttribute('aria-invalid', 'false');

        vi.advanceTimersByTime(500);

        expect(announcer()).toHaveTextContent('');
    });

    it('keeps an error the server set', () => {
        const { input } = emailField();
        input.value = 'marta@harborlane.es';
        input.setAttribute('aria-invalid', 'true');

        fireEvent.input(input, { target: { value: 'marta@harborlane.com' } });
        fireEvent.blur(input);

        expect(input).toHaveAttribute('aria-invalid', 'true');
    });

    it('flags failing fields on submit without announcing them', () => {
        const { form, input } = emailField();

        used.add(input);
        form.checkValidity();

        expect(input).toHaveAttribute('aria-invalid', 'true');

        fireEvent.blur(input);
        vi.advanceTimersByTime(500);

        expect(announcer()).toHaveTextContent('');
    });

    it('removes the announcer when uninstalled', () => {
        emailField();
        uninstall();
        uninstall = () => undefined;

        expect(announcer()).toBeNull();
    });
});
