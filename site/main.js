// Tabs for the theme code card. Selection follows focus, like native radio groups.
// Browsers with `focusgroup` handle arrow keys themselves; others use the small fallback below.

const nativeFocusgroup = Object.hasOwn(HTMLElement.prototype, 'focusGroup');

for (const tablist of document.querySelectorAll('[data-tabs]')) {
    const tabs = [...tablist.querySelectorAll('[role="tab"]')];
    const panels = tabs.map((tab) => document.getElementById(tab.dataset.panel));

    const select = (next, focus) => {
        tabs.forEach((tab, index) => {
            const selected = tab === next;
            tab.setAttribute('aria-selected', String(selected));
            tab.toggleAttribute('focusgroupstart', selected);
            tab.tabIndex = nativeFocusgroup || selected ? 0 : -1;

            if (selected) {
                panels[index].removeAttribute('hidden');
                panels[index].tabIndex = 0;
            } else {
                panels[index].hidden = 'until-found';
                panels[index].removeAttribute('tabindex');
            }
        });

        next.scrollIntoView({ block: 'nearest', inline: 'nearest', container: 'nearest' });

        if (focus) {
            next.focus();
        }
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => select(tab, false));
        tab.addEventListener('focus', () => select(tab, false));
    });

    // Find in page can open a hidden panel: select its tab too.
    panels.forEach((panel, index) => {
        panel.addEventListener('beforematch', () => select(tabs[index], false));
    });

    if (!nativeFocusgroup) {
        tabs.forEach((tab) => {
            tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
        });

        tablist.addEventListener('keydown', (event) => {
            const current = tabs.indexOf(document.activeElement);

            if (current === -1) {
                return;
            }

            const rtl = getComputedStyle(tablist).direction === 'rtl';
            const step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[event.key];
            let next = null;

            if (step !== undefined) {
                next = tabs[(current + step + tabs.length) % tabs.length];
            } else if (event.key === 'Home') {
                next = tabs[0];
            } else if (event.key === 'End') {
                next = tabs[tabs.length - 1];
            }

            if (next) {
                event.preventDefault();
                select(next, true);
            }
        });
    }
}
