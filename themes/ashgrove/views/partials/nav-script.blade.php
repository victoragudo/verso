{{-- Small script for the header menu: inline when the links fit, a popover when they do not. Without it the menu still opens as a popover. --}}
<script>
(() => {
    const header = document.querySelector('[data-site-header]');
    const inner = header?.querySelector('.header-inner');
    const menu = header?.querySelector('#site-menu');
    if (!inner || !menu || !('popover' in HTMLElement.prototype)) return;
    const buttons = [...header.querySelectorAll('[data-submenu]')];
    const listOf = (button) => document.getElementById(button.getAttribute('aria-controls'));
    let measuredWidth = 0;
    const apply = (wide) => {
        const wasOpen = menu.matches(':popover-open');
        header.classList.toggle('is-wide', wide);
        if (wide) menu.removeAttribute('popover'); else menu.setAttribute('popover', 'auto');
        if (!wide && wasOpen) menu.showPopover();
        buttons.forEach((button) => {
            const list = listOf(button);
            if (wide) { list.setAttribute('popover', 'auto'); button.setAttribute('popovertarget', list.id); } else { list.removeAttribute('popover'); button.removeAttribute('popovertarget'); }
            list.hidden = !wide;
            button.setAttribute('aria-expanded', 'false');
        });
    };
    const measure = () => {
        const width = header.clientWidth;
        const wide = header.classList.contains('is-wide');
        if (!wide && measuredWidth && width <= measuredWidth) return;
        measuredWidth = width;
        header.classList.add('is-measuring', 'is-wide');
        const fits = inner.scrollWidth <= inner.clientWidth;
        if (fits !== wide || !header.dataset.navReady) apply(fits); else header.classList.toggle('is-wide', wide);
        header.dataset.navReady = '';
        requestAnimationFrame(() => header.classList.remove('is-measuring'));
    };
    buttons.forEach((button) => {
        const list = listOf(button);
        button.addEventListener('click', () => {
            if (header.classList.contains('is-wide')) return;
            list.hidden = !list.hidden;
            button.setAttribute('aria-expanded', String(!list.hidden));
        });
        list.addEventListener('toggle', (event) => {
            button.setAttribute('aria-expanded', String(event.newState === 'open'));
            if (event.newState === 'open' && !CSS.supports('position-area', 'block-end')) {
                const box = button.getBoundingClientRect();
                list.style.top = box.bottom + 'px';
                list.style.left = Math.max(0, Math.min(box.left, innerWidth - list.offsetWidth)) + 'px';
            }
        });
    });
    const observer = new ResizeObserver(measure);
    observer.observe(header);
    observer.observe(menu);
})();
</script>
