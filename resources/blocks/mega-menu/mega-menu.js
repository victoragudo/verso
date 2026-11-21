// Mega menu: the popover panel keeps aria-expanded in step, and with a mouse on wide screens it opens on hover.
// It never opens on focus; Escape, clicks outside and focus are handled by the popover itself.
const hover = matchMedia('(hover: hover) and (pointer: fine)');

document.querySelectorAll('[data-verso-mega]').forEach((mega) => {
    const button = mega.querySelector('.verso-mega__button');
    const panel = mega.querySelector('.verso-mega__panel');

    if (!button || !panel || typeof panel.showPopover !== 'function') {
        return;
    }

    let previewed = false;
    panel.addEventListener('toggle', (event) => {
        button.setAttribute('aria-expanded', String(event.newState === 'open'));
    });

    if (mega.hasAttribute('data-hover')) {
        mega.addEventListener('pointerenter', (event) => {
            if (
                hover.matches &&
                event.pointerType === 'mouse' &&
                !panel.matches(':popover-open')
            ) {
                panel.showPopover({ source: button });
                previewed = true;
            }
        });
        mega.addEventListener('pointerleave', (event) => {
            if (
                previewed &&
                event.pointerType === 'mouse' &&
                !panel.contains(event.relatedTarget)
            ) {
                panel.hidePopover();
            }

            previewed = false;
        });
        panel.addEventListener('pointerleave', (event) => {
            if (
                hover.matches &&
                event.pointerType === 'mouse' &&
                !mega.contains(event.relatedTarget) &&
                panel.matches(':popover-open')
            ) {
                panel.hidePopover();
            }
        });
    }

    button.addEventListener('click', () => {
        previewed = false;
    });
    mega.addEventListener('focusout', (event) => {
        if (
            !mega.contains(event.relatedTarget) &&
            !panel.contains(event.relatedTarget) &&
            panel.matches(':popover-open')
        ) {
            panel.hidePopover();
        }
    });
    mega.dataset.ready = '';
});
