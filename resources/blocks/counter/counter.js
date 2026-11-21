// Counter: counts up to the number in the page when it scrolls into view, in the language of the page.
const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)');
const locale = document.documentElement.lang || undefined;

document.querySelectorAll('[data-verso-counter]').forEach((counter) => {
    const number = counter.querySelector('.verso-counter__number');
    const to = Number(counter.dataset.to);
    const decimals = Number(counter.dataset.decimals) || 0;
    const duration = Number(counter.dataset.duration) || 0;
    const format = new Intl.NumberFormat(locale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
    const final = format.format(to);

    // The width of the final number is kept from the start, so nothing moves while it counts.
    number.textContent = final;
    number.style.setProperty('display', 'inline-block');
    number.style.setProperty('min-inline-size', `${final.length}ch`);

    if (reduceMotion.matches || duration === 0 || Number.isNaN(to)) {
        return;
    }

    const observer = new IntersectionObserver(([entry]) => {
        if (!entry.isIntersecting) {
            return;
        }

        observer.disconnect();
        counter.setAttribute(
            'aria-label',
            `${counter.querySelector('.verso-counter__prefix').textContent}${final}${counter.querySelector('.verso-counter__suffix').textContent}`,
        );
        number.setAttribute('aria-hidden', 'true');
        const start = performance.now();
        const tick = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - (1 - progress) ** 3;
            number.textContent = format.format(to * eased);

            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                number.removeAttribute('aria-hidden');
                counter.removeAttribute('aria-label');
            }
        };
        requestAnimationFrame(tick);
    });

    observer.observe(counter);
});
