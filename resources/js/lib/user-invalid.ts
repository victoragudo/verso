// Keeps aria-invalid in step with the CSS :user-invalid state of form fields,
// so screen readers hear about a browser validation error only after the person
// has used the field or tried to send the form. Errors from the server stay in
// charge: an aria-invalid="true" this code did not set is left alone.

type Field = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

const FIELD_SELECTOR =
    'input:not([type=hidden]):not([aria-hidden=true]), select:not([aria-hidden=true]), textarea';
// Wait until the screen reader has read the field that gets focus next.
const ANNOUNCE_DELAY_MS = 500;

function isField(target: EventTarget | null): target is Field {
    return (
        target instanceof Element &&
        'validity' in target &&
        target.matches(FIELD_SELECTOR)
    );
}

function matchesUserInvalid(field: Field): boolean {
    try {
        return field.matches(':user-invalid');
    } catch {
        return false;
    }
}

// The label text of a field, as a screen reader would start the message.
function labelText(field: Field): string {
    const text = field.labels?.[0]?.textContent?.replace(/\s+/g, ' ').trim();

    return text ? `${text}: ` : '';
}

export function installUserInvalidSync(root: Document = document): () => void {
    const announcer = root.createElement('div');
    announcer.setAttribute('aria-live', 'polite');
    announcer.dataset.slot = 'validation-announcer';
    announcer.className = 'sr-only';
    root.body.append(announcer);

    const flagged = new WeakSet<Field>();
    const announced = new WeakSet<Field>();
    let announcedField: Field | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const announce = (field: Field) => {
        clearTimeout(timer);
        announcer.textContent = '';
        announcedField = field;
        timer = setTimeout(() => {
            announcer.textContent = labelText(field) + field.validationMessage;
        }, ANNOUNCE_DELAY_MS);
    };

    const sync = (field: Field, withAnnouncement = false) => {
        if (matchesUserInvalid(field)) {
            if (withAnnouncement && !announced.has(field)) {
                announce(field);
                announced.add(field);
            }

            field.setAttribute('aria-invalid', 'true');
            flagged.add(field);

            return;
        }

        if (
            flagged.has(field) ||
            field.getAttribute('aria-invalid') !== 'true'
        ) {
            field.setAttribute('aria-invalid', 'false');
            flagged.delete(field);
            announced.delete(field);

            if (announcedField === field) {
                clearTimeout(timer);
                announcer.textContent = '';
            }
        }
    };

    // Firefox and Safari call an empty required field invalid before any use.
    const onFocusIn = (event: Event) => {
        if (
            isField(event.target) &&
            !event.target.hasAttribute('aria-invalid')
        ) {
            event.target.setAttribute('aria-invalid', 'false');
        }
    };
    const onBlur = (event: Event) => {
        if (isField(event.target)) {
            sync(event.target, true);
        }
    };
    const onInput = (event: Event) => {
        if (
            isField(event.target) &&
            event.target.hasAttribute('aria-invalid')
        ) {
            sync(event.target);
        }
    };
    // Focus goes to the first failing field after a submit, which reads its error.
    const onInvalid = (event: Event) => {
        if (isField(event.target)) {
            announced.add(event.target);
            sync(event.target);
        }
    };
    const onReset = (event: Event) => {
        const form = event.target;

        if (form instanceof HTMLFormElement) {
            setTimeout(() =>
                Array.from(form.elements)
                    .filter(isField)
                    .forEach((field) => sync(field)),
            );
        }
    };

    root.addEventListener('focusin', onFocusIn, true);
    root.addEventListener('blur', onBlur, true);
    root.addEventListener('input', onInput);
    root.addEventListener('change', onBlur, true);
    root.addEventListener('invalid', onInvalid, true);
    root.addEventListener('reset', onReset, true);

    return () => {
        clearTimeout(timer);
        root.removeEventListener('focusin', onFocusIn, true);
        root.removeEventListener('blur', onBlur, true);
        root.removeEventListener('input', onInput);
        root.removeEventListener('change', onBlur, true);
        root.removeEventListener('invalid', onInvalid, true);
        root.removeEventListener('reset', onReset, true);
        announcer.remove();
    };
}
