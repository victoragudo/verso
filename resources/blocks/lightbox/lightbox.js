// Lightbox: a link around each gallery picture opens one native dialog for the gallery,
// with previous and next buttons and the arrow keys. Without JavaScript the links open the picture.
document.querySelectorAll('[data-verso-gallery-lightbox]').forEach((gallery) => {
    const links = [...gallery.querySelectorAll('a[data-verso-lightbox]')];

    if (links.length === 0) {
        return;
    }

    const dialog = document.createElement('dialog');
    dialog.className = 'verso-lightbox';
    dialog.setAttribute('aria-label', gallery.dataset.title);
    dialog.setAttribute('closedby', 'any');
    const button = (label, text, attribute) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.setAttribute('aria-label', label);
        item.setAttribute(attribute, '');
        item.textContent = text;

        return item;
    };
    const previous = button(gallery.dataset.previous, '‹', 'data-previous');
    const next = button(gallery.dataset.next, '›', 'data-next');
    const close = button(gallery.dataset.close, '×', 'data-close');
    const figure = document.createElement('figure');
    const image = document.createElement('img');
    const caption = document.createElement('figcaption');
    figure.append(image, caption);
    dialog.append(previous, figure, next, close);
    gallery.after(dialog);
    let index = 0;

    const show = (at) => {
        index = (at + links.length) % links.length;
        const link = links[index];
        const thumb = link.querySelector('img');
        image.src = link.href;
        image.alt = thumb?.alt ?? '';
        caption.textContent = link.closest('figure')?.querySelector('figcaption')?.textContent ?? '';
        previous.hidden = links.length < 2;
        next.hidden = links.length < 2;
    };

    links.forEach((link, at) =>
        link.addEventListener('click', (event) => {
            event.preventDefault();
            show(at);
            dialog.showModal();
            close.focus();
        }),
    );
    previous.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    dialog.id = `verso-lightbox-${document.querySelectorAll('.verso-lightbox').length}`;
    close.setAttribute('commandfor', dialog.id);
    close.setAttribute('command', 'close');

    if (!('commandForElement' in HTMLButtonElement.prototype)) {
        close.addEventListener('click', () => {
            dialog.close();
        });
    }

    dialog.addEventListener('keydown', (event) => {
        const rtl = getComputedStyle(dialog).direction === 'rtl';

        if (event.key === 'ArrowRight') {
            show(index + (rtl ? -1 : 1));
        } else if (event.key === 'ArrowLeft') {
            show(index + (rtl ? 1 : -1));
        }
    });
    // Browsers without closedby="any" close the lightbox on a click beside the picture.
    if (!('closedBy' in HTMLDialogElement.prototype)) {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    }
});
