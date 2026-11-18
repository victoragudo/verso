// Dialogs: buttons open and close them with invoker commands (commandfor, command). Browsers without
// invoker commands get the same with click listeners, and browsers without closedby="any" close on a click outside.
const invokers = 'commandForElement' in HTMLButtonElement.prototype;
const closedBy = 'closedBy' in HTMLDialogElement.prototype;

export function wireDialog(dialog) {
    if (!invokers) {
        dialog.querySelectorAll('[data-closes]').forEach((button) =>
            button.addEventListener('click', () => {
                dialog.close();
            }),
        );
    }

    if (!closedBy) {
        let down = false;
        const isOut = (event) => {
            const box = dialog.getBoundingClientRect();

            return event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom;
        };
        dialog.addEventListener('pointerdown', (event) => {
            down = isOut(event);
        });
        dialog.addEventListener('pointerup', (event) => {
            if (down && isOut(event)) {
                dialog.close();
            }

            down = false;
        });
    }
}

document.querySelectorAll('[data-verso-modal]').forEach((modal) => {
    const button = modal.querySelector('.verso-modal__open');
    const dialog = document.getElementById(button.dataset.opens);
    wireDialog(dialog);

    if (!invokers) {
        button.addEventListener('click', () => {
            dialog.showModal();
        });
    }

    modal.dataset.ready = '';
});
