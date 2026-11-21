// Image comparison: the range input moves the edge between the two pictures.
document.querySelectorAll('[data-verso-compare]').forEach((figure) => {
    const range = figure.querySelector('.verso-compare__range');
    const show = () => {
        figure.style.setProperty('--verso-compare', `${range.value}%`);
        range.setAttribute(
            'aria-valuetext',
            range.dataset.valuetext.replace('{value}', range.value),
        );
    };

    range.hidden = false;
    figure.dataset.ready = '';
    range.addEventListener('input', show);
    show();
});
