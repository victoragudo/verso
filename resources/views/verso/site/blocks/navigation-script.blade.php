{{-- Fit script of the menu block: inline links when the row fits, a "Menu" popover when it does not. Without it the menu still opens as a popover. --}}
<script>
(() => {
    if (!('popover' in HTMLElement.prototype)) return;
    const setUp = (nav) => {
        if (nav.dataset.versoNavReady !== undefined) return;
        nav.dataset.versoNavReady = '';
        const menu = nav.querySelector('.verso-nav__list');
        const row = nav.closest('.verso-section') ?? nav.parentElement;
        const buttons = [...nav.querySelectorAll('[data-verso-submenu]')];
        const listOf = (button) => document.getElementById(button.getAttribute('aria-controls'));
        let measuredWidth = 0;
        const apply = (wide) => {
            const wasOpen = menu.matches(':popover-open');
            nav.classList.toggle('is-wide', wide);
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
            const width = row.clientWidth;
            const wide = nav.classList.contains('is-wide');
            if (!wide && measuredWidth && width <= measuredWidth) return;
            measuredWidth = width;
            nav.classList.add('is-measuring', 'is-wide');
            const fits = row.scrollWidth <= row.clientWidth;
            if (fits !== wide || nav.dataset.versoNavSet === undefined) apply(fits); else nav.classList.toggle('is-wide', wide);
            nav.dataset.versoNavSet = '';
            requestAnimationFrame(() => nav.classList.remove('is-measuring'));
        };
        buttons.forEach((button) => {
            const list = listOf(button);
            button.addEventListener('click', () => {
                if (nav.classList.contains('is-wide')) return;
                list.hidden = !list.hidden;
                button.setAttribute('aria-expanded', String(!list.hidden));
            });
            list.addEventListener('toggle', (event) => button.setAttribute('aria-expanded', String(event.newState === 'open')));
        });
        const observer = new ResizeObserver(measure);
        observer.observe(row);
        observer.observe(menu);
    };
    const run = () => document.querySelectorAll('[data-verso-nav]').forEach(setUp);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
