// Share links: "Copy link" writes the address to the clipboard and says so; the device share sheet where there is one.
document.querySelectorAll('[data-verso-copy]').forEach((button) => {
    const tip = button.parentElement.querySelector('.verso-share__tip');
    let timer = 0;
    button.hidden = false;
    button.addEventListener('click', async () => {
        clearTimeout(timer);
        let message = button.dataset.done;

        try {
            await navigator.clipboard.writeText(button.dataset.versoCopy);
        } catch {
            message = button.dataset.failed;
        }

        // A repeated message gets a hidden space, so screen readers say it again.
        tip.textContent = tip.textContent === message ? `${message} ` : message;
        timer = setTimeout(() => {
            tip.textContent = '';
        }, 2500);
    });
});

document.querySelectorAll('[data-verso-native-share]').forEach((button) => {
    if (typeof navigator.share !== 'function') {
        return;
    }

    button.hidden = false;
    button.addEventListener('click', () => {
        navigator.share({ url: button.dataset.url, title: button.dataset.title }).catch(() => {});
    });
});
