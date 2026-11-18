// Table of contents: marks the link of the heading being read, with aria-current for screen readers.
document.querySelectorAll('[data-verso-toc]').forEach((toc) => {
    const links = [...toc.querySelectorAll('a[href^="#"]')];
    const targets = links.map((link) => document.getElementById(decodeURIComponent(link.hash.slice(1)))).filter(Boolean);

    if (targets.length === 0) {
        return;
    }

    const mark = (id) =>
        links.forEach((link) => {
            if (link.hash.slice(1) === id) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });

    const observer = new IntersectionObserver(
        (entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting);

            if (visible.length > 0) {
                mark(visible[0].target.id);
            }
        },
        { rootMargin: '-50% 0px -50% 0px' },
    );

    targets.forEach((target) => observer.observe(target));
});
