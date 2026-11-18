// Tabs: arrow keys, Home and End move between tabs and select them; "find in page" opens the tab that holds the match.
const select = (tabs, tab, focus) => {
    tabs.querySelectorAll(':scope > [role="tablist"] > [role="tab"]').forEach((item) => {
        const selected = item === tab;
        const panel = document.getElementById(item.getAttribute('aria-controls'));
        item.setAttribute('aria-selected', String(selected));
        item.tabIndex = selected ? 0 : -1;

        if (panel) {
            if (selected) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'until-found');
            }
        }
    });

    if (focus) {
        tab.focus();
    }

    tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
};

document.querySelectorAll('[data-verso-tabs]').forEach((tabs) => {
    const list = tabs.querySelector(':scope > [role="tablist"]');
    const all = () => [...list.querySelectorAll('[role="tab"]')];

    list.addEventListener('click', (event) => {
        const tab = event.target.closest('[role="tab"]');

        if (tab) {
            select(tabs, tab, false);
        }
    });

    list.addEventListener('keydown', (event) => {
        const items = all();
        const index = items.indexOf(document.activeElement);
        const rtl = getComputedStyle(list).direction === 'rtl';
        const step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[event.key];
        let next = null;

        if (step !== undefined) {
            next = items[(index + step + items.length) % items.length];
        } else if (event.key === 'Home') {
            next = items[0];
        } else if (event.key === 'End') {
            next = items[items.length - 1];
        }

        if (next) {
            event.preventDefault();
            select(tabs, next, true);
        }
    });

    tabs.addEventListener('beforematch', (event) => {
        const panel = event.target.closest('[role="tabpanel"]');
        const tab = panel && all().find((item) => item.getAttribute('aria-controls') === panel.id);

        if (tab) {
            select(tabs, tab, false);
        }
    });
});
