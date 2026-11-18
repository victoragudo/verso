document.querySelectorAll('.harbor-tides li').forEach((item) => {
    item.addEventListener('click', () => item.classList.toggle('is-open'));
});
