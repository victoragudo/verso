// Video background: plays only while on screen and never for people who ask for less motion. A button pauses it.
const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)');

document.querySelectorAll('[data-verso-video-bg]').forEach((box) => {
    const video = box.querySelector('video');
    const button = box.querySelector('.verso-video-bg__pause');

    if (!video || !button) {
        return;
    }

    let paused = reduceMotion.matches;
    let visible = false;

    const apply = () => {
        button.setAttribute('aria-pressed', String(paused));
        button.textContent = paused
            ? button.dataset.playLabel
            : button.dataset.pauseLabel;

        if (!paused && visible) {
            video.play().catch(() => {});
        } else {
            video.pause();
        }
    };

    button.hidden = false;
    button.addEventListener('click', () => {
        paused = !paused;
        apply();
    });
    reduceMotion.addEventListener('change', () => {
        paused = reduceMotion.matches;
        apply();
    });
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        apply();
    }).observe(box);
});
