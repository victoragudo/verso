// Theme builder header: after the visitor scrolls past the offset it gets "is-scrolled" (solid or smaller);
// with "hide" it slides away while scrolling down and comes back when scrolling up or when it gets focus.
document.querySelectorAll('[data-verso-header]').forEach((header) => {
    const effect = header.dataset.scroll;

    if (!effect || effect === 'none') {
        return;
    }

    const offset = Number(header.dataset.offset) || 0;
    let last = window.scrollY;
    let frame = 0;

    const update = () => {
        const y = window.scrollY;
        header.classList.toggle('is-scrolled', y > offset);

        if (effect === 'hide') {
            header.classList.toggle('is-hidden', y > offset && y > last);
        }

        last = y;
    };

    window.addEventListener(
        'scroll',
        () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(update);
        },
        { passive: true },
    );
    update();
});
