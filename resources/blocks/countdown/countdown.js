// Countdown: shows days, hours, minutes and seconds left until the date. Screen readers read the date line;
// the ticking numbers are hidden from them and the end is said once.
document.querySelectorAll('[data-verso-countdown]').forEach((countdown) => {
    const until = Date.parse(countdown.dataset.until);
    const units = countdown.querySelector('.verso-countdown__units');
    const done = countdown.querySelector('.verso-countdown__done');
    const numbers = Object.fromEntries(
        [...countdown.querySelectorAll('[data-unit]')].map((item) => [
            item.dataset.unit,
            item,
        ]),
    );
    const pad = (value) => String(value).padStart(2, '0');
    let timer = 0;

    const tick = () => {
        const left = Math.max(0, Math.floor((until - Date.now()) / 1000));

        if (Number.isNaN(until) || left === 0) {
            clearInterval(timer);
            units.hidden = true;
            done.hidden = false;
            done.textContent = done.dataset.done;

            return;
        }

        numbers.days.textContent = pad(Math.floor(left / 86400));
        numbers.hours.textContent = pad(Math.floor((left % 86400) / 3600));
        numbers.minutes.textContent = pad(Math.floor((left % 3600) / 60));
        numbers.seconds.textContent = pad(left % 60);
        units.hidden = false;
    };

    tick();
    timer = setInterval(tick, 1000);
});
