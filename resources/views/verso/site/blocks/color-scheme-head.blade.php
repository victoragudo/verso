{{-- Head script of the dark mode switch: applies the saved choice before the first paint, so the page never flashes. --}}
(() => {
    try {
        const scheme = localStorage.getItem('verso-color-scheme');
        if (scheme !== 'light' && scheme !== 'dark') return;
        let meta = document.querySelector('meta[name="color-scheme"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.name = 'color-scheme';
            document.head.append(meta);
        }
        meta.content = scheme;
    } catch {}
})();
