{{-- Click script of the dark mode switch. The saved choice lives in localStorage; no value means "follow the system". --}}
<script>
(() => {
    const key = 'verso-color-scheme';
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const meta = () => {
        let tag = document.querySelector('meta[name="color-scheme"]');
        if (!tag) {
            tag = document.createElement('meta');
            tag.name = 'color-scheme';
            document.head.append(tag);
        }
        return tag;
    };
    const systemScheme = () => (system.matches ? 'dark' : 'light');
    const apply = (scheme) => {
        meta().content = scheme === 'light' || scheme === 'dark' ? scheme : 'light dark';
    };
    const shown = () => {
        const content = meta().content;
        return content === 'light' || content === 'dark' ? content : systemScheme();
    };
    const reveal = () => document.querySelectorAll('[data-verso-scheme-toggle][hidden]').forEach((button) => {
        button.hidden = false;
    });
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-verso-scheme-toggle]') : null;
        if (!button) return;
        // Pick the opposite of what the visitor sees. Going back to the system colors forgets the choice.
        const target = shown() === 'dark' ? 'light' : 'dark';
        const followsSystem = target === systemScheme();
        try {
            if (followsSystem) localStorage.removeItem(key); else localStorage.setItem(key, target);
        } catch {}
        apply(followsSystem ? null : target);
    });
    // A choice made in another tab applies here too.
    window.addEventListener('storage', (event) => {
        if (event.key === key) apply(event.newValue);
    });
    reveal();
    document.addEventListener('DOMContentLoaded', reveal);
})();
</script>
