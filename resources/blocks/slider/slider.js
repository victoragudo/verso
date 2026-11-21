// Slider: previous and next buttons, one button per slide, a polite "Slide 2 of 5" after moving, and autoplay
// with a pause button that never runs for people who ask for less motion.
const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)');

document.querySelectorAll('[data-verso-slider]').forEach((slider) => {
    const track = slider.querySelector('.verso-slider__track');
    const slides = [...track.children];
    const previous = slider.querySelector('[data-direction="previous"]');
    const next = slider.querySelector('[data-direction="next"]');
    const status = slider.querySelector('[data-slide-status]');
    const dots = slider.querySelector('.verso-slider__dots');
    const pause = slider.querySelector('[data-verso-pause]');
    let current = 0;
    let settled = 0;
    let timer = null;

    if (slides.length === 0) {
        return;
    }

    // The slide closest to the start of the track.
    const nearest = () => {
        const start = track.getBoundingClientRect();
        let best = 0;
        let distance = Infinity;
        const rtl = getComputedStyle(track).direction === 'rtl';

        slides.forEach((slide, index) => {
            const box = slide.getBoundingClientRect();
            const gap = Math.abs(
                rtl ? start.right - box.right : box.left - start.left,
            );

            if (gap < distance) {
                distance = gap;
                best = index;
            }
        });

        return best;
    };

    const go = (index) => {
        const target = slides[Math.max(0, Math.min(slides.length - 1, index))];
        const box = target.getBoundingClientRect();
        const start = track.getBoundingClientRect();
        const rtl = getComputedStyle(track).direction === 'rtl';
        track.scrollBy({
            left: rtl ? box.right - start.right : box.left - start.left,
        });
    };

    const update = (announce) => {
        current = nearest();
        const atEnd =
            track.scrollLeft !== 0 &&
            Math.abs(
                track.scrollWidth -
                    track.clientWidth -
                    Math.abs(track.scrollLeft),
            ) < 2;

        if (previous && next) {
            const focused = document.activeElement;
            previous.disabled = current === 0;
            next.disabled = current === slides.length - 1 || atEnd;

            if (focused === previous && previous.disabled) {
                next.focus();
            } else if (focused === next && next.disabled) {
                previous.focus();
            }
        }

        dots?.querySelectorAll('button').forEach((dot, index) => {
            dot.toggleAttribute('aria-current', index === current);

            if (index === current) {
                dot.setAttribute('aria-current', 'true');
            }
        });

        if (announce && status && current !== settled) {
            status.textContent = status.dataset.template
                .replace(':number', String(current + 1))
                .replace(':count', String(slides.length));
        }

        settled = current;
    };

    slides.forEach((slide, index) => {
        if (!dots) {
            return;
        }

        const dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute(
            'aria-label',
            slide.dataset.markerName ?? String(index + 1),
        );
        dot.addEventListener('click', () => go(index));
        dots.append(dot);
    });

    previous?.addEventListener('click', () => go(current - 1));
    next?.addEventListener('click', () => go(current + 1));

    let frame = 0;
    let settle = 0;
    track.addEventListener('scroll', () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => update(false));
        clearTimeout(settle);
        settle = setTimeout(() => update(true), 150);
    });

    const stop = () => {
        clearInterval(timer);
        timer = null;
    };

    const play = () => {
        stop();

        if (
            !slider.dataset.autoplay ||
            reduceMotion.matches ||
            pause?.getAttribute('aria-pressed') === 'true'
        ) {
            return;
        }

        timer = setInterval(
            () => go(current === slides.length - 1 ? 0 : current + 1),
            Number(slider.dataset.autoplay) * 1000,
        );
    };

    pause?.addEventListener('click', () => {
        const paused = pause.getAttribute('aria-pressed') !== 'true';
        pause.setAttribute('aria-pressed', String(paused));
        pause.textContent = paused
            ? pause.dataset.playLabel
            : pause.dataset.pauseLabel;
        play();
    });

    // Autoplay rests while people point at or work inside the slider.
    slider.addEventListener('pointerenter', stop);
    slider.addEventListener('pointerleave', play);
    slider.addEventListener('focusin', stop);
    slider.addEventListener('focusout', (event) => {
        if (!slider.contains(event.relatedTarget)) {
            play();
        }
    });
    reduceMotion.addEventListener('change', play);

    slider.dataset.ready = '';
    update(false);
    play();
});
