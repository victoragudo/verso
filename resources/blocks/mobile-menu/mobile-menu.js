// Mobile menu: invoker commands open and close the drawer. Browsers without them get click listeners,
// and browsers without closedby="any" close it on a click on the backdrop. A link inside closes it.
const invokers = 'commandForElement' in HTMLButtonElement.prototype;
const closedBy = 'closedBy' in HTMLDialogElement.prototype;

document.querySelectorAll('[data-verso-mobile-menu]').forEach((menu) => {
    const button = menu.querySelector('.verso-mobile-menu__button');
    const drawer = menu.querySelector('dialog');

    if (!button || !drawer) {
        return;
    }

    if (!invokers) {
        button.addEventListener('click', () => {
            drawer.showModal();
        });
        drawer
            .querySelector('.verso-mobile-menu__close')
            ?.addEventListener('click', () => {
                drawer.close();
            });
    }

    if (!closedBy) {
        drawer.addEventListener('pointerdown', (event) => {
            if (event.target === drawer) {
                drawer.close();
            }
        });
    }

    drawer.addEventListener('click', (event) => {
        if (event.target.closest('a[href]')) {
            drawer.close();
        }
    });
});
