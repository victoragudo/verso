// Popups built with blocks. Each popup says when it shows (on load, after a delay, after some scrolling,
// when the pointer leaves the page, or on a click), how often, and on which screen sizes. Choices are kept
// in the browser storage of the visitor only: nothing is sent anywhere.
const SIZES = { phone: '(max-width: 640px)', tablet: '(min-width: 641px) and (max-width: 1024px)', computer: '(min-width: 1025px)' };

const read = (key) => {
    try {
        return localStorage.getItem(key) ?? sessionStorage.getItem(key);
    } catch {
        return null;
    }
};

const write = (key, value, session) => {
    try {
        (session ? sessionStorage : localStorage).setItem(key, value);
    } catch {
        // Private windows may refuse storage; the popup then shows again next time.
    }
};

// True when the visitor asked not to see it again for now.
const resting = (popup) => {
    const value = read(popup.dataset.key);

    return value !== null && (value === 'dismissed' || value === 'shown' || Number(value) > Date.now());
};

const remember = (popup) => {
    const { frequency, days, key } = popup.dataset;

    if (frequency === 'session') {
        write(key, 'shown', true);
    } else if (frequency === 'days') {
        write(key, String(Date.now() + Number(days) * 86400000), false);
    } else if (frequency === 'dismissed') {
        write(key, 'dismissed', false);
    }
};

const fits = (popup) => (popup.dataset.devices ?? '').split(' ').some((size) => SIZES[size] && matchMedia(SIZES[size]).matches);

const modalOpen = () => document.querySelector('dialog:modal') !== null;

let returnFocus = null;

const open = (popup, byClick) => {
    if (popup.classList.contains('is-open') || (!byClick && (modalOpen() || resting(popup) || !fits(popup)))) {
        return;
    }

    returnFocus = byClick ? document.activeElement : null;

    if (popup instanceof HTMLDialogElement) {
        popup.showModal();
    } else if (typeof popup.showPopover === 'function' && popup.hasAttribute('popover')) {
        popup.showPopover();
    }

    popup.classList.add('is-open');
};

const close = (popup) => {
    if (popup instanceof HTMLDialogElement) {
        popup.close();
    } else if (typeof popup.hidePopover === 'function' && popup.matches(':popover-open')) {
        popup.hidePopover();
    }

    if (popup.classList.contains('verso-topbar')) {
        popup.hidden = true;
    }

    popup.classList.remove('is-open');
    remember(popup);
    returnFocus?.focus?.();
    returnFocus = null;
};

document.querySelectorAll('[data-verso-popup]').forEach((popup) => {
    const { trigger } = popup.dataset;

    popup.querySelectorAll('[data-dismiss], [data-closes]').forEach((button) =>
        button.addEventListener('click', (event) => {
            event.preventDefault();
            close(popup);
        }),
    );

    if (popup instanceof HTMLDialogElement) {
        popup.addEventListener('close', () => {
            popup.classList.remove('is-open');
            remember(popup);
        });
    }

    popup.querySelectorAll('[data-consent]').forEach((button) =>
        button.addEventListener('click', () => {
            write('verso-consent', button.dataset.consent, false);
            document.dispatchEvent(new CustomEvent('verso:consent', { detail: { choice: button.dataset.consent } }));
            close(popup);
        }),
    );

    // Any button or link that names the popup opens it, whatever its trigger.
    document.addEventListener('click', (event) => {
        const opener = event.target.closest(`[data-verso-open-popup="${popup.dataset.versoPopup}"], a[href="#${popup.id}"]`);

        if (opener) {
            event.preventDefault();
            open(popup, true);
        }
    });

    if (popup.classList.contains('verso-topbar') || trigger === 'click') {
        return;
    }

    if (popup.hasAttribute('data-cookie')) {
        if (read('verso-consent') === null) {
            open(popup, false);
        }

        return;
    }

    if (trigger === 'load') {
        open(popup, false);
    } else if (trigger === 'delay') {
        setTimeout(() => open(popup, false), Number(popup.dataset.delay) * 1000);
    } else if (trigger === 'scroll') {
        const check = () => {
            const total = document.documentElement.scrollHeight - innerHeight;

            if (total <= 0 || (scrollY / total) * 100 >= Number(popup.dataset.scroll)) {
                removeEventListener('scroll', check);
                open(popup, false);
            }
        };
        addEventListener('scroll', check, { passive: true });
    } else if (trigger === 'exit') {
        const leave = (event) => {
            if (event.clientY <= 0 && !event.relatedTarget) {
                document.removeEventListener('mouseout', leave);
                open(popup, false);
            }
        };
        document.addEventListener('mouseout', leave);
    }
});
