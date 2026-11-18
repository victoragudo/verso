import { useEffect, useId, useRef } from 'react';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const CONTROL_SELECTOR =
    'input:not([type=hidden]):not([aria-hidden=true]), textarea, select:not([aria-hidden=true]), [role=combobox], [role=checkbox], [role=switch]';

// Finds the form control that an error message belongs to: the one its label
// points at, or else the first control in the same field group.
function fieldControl(error: HTMLElement): HTMLElement | null {
    const group = error.parentElement;

    if (!group) {
        return null;
    }

    const label = group.querySelector<HTMLLabelElement>('label[for]');
    const labelled = label?.htmlFor
        ? group.querySelector<HTMLElement>(`#${CSS.escape(label.htmlFor)}`)
        : null;

    return labelled ?? group.querySelector<HTMLElement>(CONTROL_SELECTOR);
}

// Adds or removes one id in a space separated attribute like aria-describedby.
function toggleToken(
    element: HTMLElement,
    attribute: string,
    token: string,
    on: boolean,
): void {
    const tokens = (element.getAttribute(attribute) ?? '')
        .split(/\s+/)
        .filter((value) => value && value !== token);

    if (on) {
        tokens.push(token);
    }

    if (tokens.length > 0) {
        element.setAttribute(attribute, tokens.join(' '));
    } else {
        element.removeAttribute(attribute);
    }
}

// A field error. It marks its field as invalid and links the message to it,
// so screen readers read the error when the field gets focus.
export default function InputError({
    message,
    className = '',
    id,
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    const generatedId = useId();
    const errorId = id ?? `${generatedId}-error`;
    const ref = useRef<HTMLParagraphElement>(null);

    useEffect(() => {
        const error = ref.current;
        const control = error ? fieldControl(error) : null;

        if (!message || !control) {
            return;
        }

        const previous = control.getAttribute('aria-invalid');
        toggleToken(control, 'aria-describedby', errorId, true);
        control.setAttribute('aria-invalid', 'true');

        return () => {
            toggleToken(control, 'aria-describedby', errorId, false);

            if (previous === null) {
                control.removeAttribute('aria-invalid');
            } else {
                control.setAttribute('aria-invalid', previous);
            }
        };
    }, [message, errorId]);

    return message ? (
        <p
            {...props}
            ref={ref}
            id={errorId}
            className={cn('text-sm text-red-600 dark:text-red-400', className)}
        >
            {message}
        </p>
    ) : null;
}
