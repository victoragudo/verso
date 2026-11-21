// Browsers without the details "name" attribute: opening one item of a "one at a time" accordion closes the others.
if (!Object.hasOwn(HTMLDetailsElement.prototype, 'name')) {
    document.addEventListener(
        'toggle',
        (event) => {
            const details = event.target;
            const group =
                details instanceof HTMLDetailsElement
                    ? details.getAttribute('name')
                    : null;

            if (!group || !details.open) {
                return;
            }

            document
                .querySelectorAll(`details[name="${CSS.escape(group)}"]`)
                .forEach((other) => {
                    if (other !== details) {
                        other.open = false;
                    }
                });
        },
        { capture: true },
    );
}
