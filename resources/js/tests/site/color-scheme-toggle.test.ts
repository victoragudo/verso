import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// The scripts live in Blade views of the dark mode switch block. They are plain JavaScript.
const viewsPath = resolve(__dirname, '../../../views/verso/site/blocks');

function scriptOf(file: string): string {
    return readFileSync(resolve(viewsPath, file), 'utf8')
        .replace(/\{\{--[\s\S]*?--\}\}/g, '')
        .replace(/<\/?script>/g, '');
}

const headScript = scriptOf('color-scheme-head.blade.php');
const clickScript = scriptOf('color-scheme-script.blade.php');

let systemDark = false;
const listeners: Array<{
    type: string;
    listener: EventListener;
    target: EventTarget;
}> = [];

function run(code: string): void {
    // Keep track of listeners, so each test starts clean.
    const add = (target: EventTarget) => {
        const original = target.addEventListener.bind(target);

        return vi
            .spyOn(target, 'addEventListener')
            .mockImplementation((type, listener, options) => {
                listeners.push({
                    type,
                    listener: listener as EventListener,
                    target,
                });
                original(type, listener, options);
            });
    };
    const spies = [add(document), add(window)];
    // oxlint-disable-next-line typescript/no-implied-eval -- runs the inline head script the way the browser does.
    new Function(code)();
    spies.forEach((spy) => spy.mockRestore());
}

function meta(): HTMLMetaElement {
    return document.querySelector(
        'meta[name="color-scheme"]',
    ) as HTMLMetaElement;
}

function button(): HTMLButtonElement {
    return document.querySelector(
        '[data-verso-scheme-toggle]',
    ) as HTMLButtonElement;
}

beforeEach(() => {
    systemDark = false;
    localStorage.clear();
    document.head.innerHTML = '<meta name="color-scheme" content="light dark">';
    document.body.innerHTML =
        '<header><button type="button" data-verso-scheme-toggle hidden>Switch</button></header>';
    vi.stubGlobal('matchMedia', (query: string) => ({
        get matches() {
            return query === '(prefers-color-scheme: dark)' && systemDark;
        },
        media: query,
    }));
});

afterEach(() => {
    listeners
        .splice(0)
        .forEach(({ type, listener, target }) =>
            target.removeEventListener(type, listener),
        );
    vi.unstubAllGlobals();
});

describe('dark mode switch head script', () => {
    it('applies a saved choice before the first paint', () => {
        localStorage.setItem('verso-color-scheme', 'dark');
        run(headScript);

        expect(meta().content).toBe('dark');
    });

    it('follows the system when nothing or something odd is saved', () => {
        run(headScript);
        expect(meta().content).toBe('light dark');

        localStorage.setItem('verso-color-scheme', 'sepia');
        run(headScript);
        expect(meta().content).toBe('light dark');
    });

    it('adds the meta tag when the theme has none', () => {
        document.head.innerHTML = '';
        localStorage.setItem('verso-color-scheme', 'light');
        run(headScript);

        expect(meta().content).toBe('light');
    });

    it('does not break when storage is blocked', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new DOMException('Blocked', 'SecurityError');
        });

        expect(() => run(headScript)).not.toThrow();
        expect(meta().content).toBe('light dark');
        vi.restoreAllMocks();
    });
});

describe('dark mode switch click script', () => {
    it('shows the button once the script runs', () => {
        run(clickScript);

        expect(button().hidden).toBe(false);
    });

    it('stores the opposite of the system and goes back to the system on the next click', () => {
        run(clickScript);

        button().click();
        expect(meta().content).toBe('dark');
        expect(localStorage.getItem('verso-color-scheme')).toBe('dark');

        button().click();
        expect(meta().content).toBe('light dark');
        expect(localStorage.getItem('verso-color-scheme')).toBeNull();
    });

    it('stores light when the system is dark', () => {
        systemDark = true;
        run(clickScript);

        button().click();

        expect(meta().content).toBe('light');
        expect(localStorage.getItem('verso-color-scheme')).toBe('light');
    });

    it('keeps a saved choice when the system later matches it', () => {
        localStorage.setItem('verso-color-scheme', 'dark');
        run(headScript);
        systemDark = true;
        run(clickScript);

        expect(meta().content).toBe('dark');
        expect(localStorage.getItem('verso-color-scheme')).toBe('dark');

        button().click();
        expect(meta().content).toBe('light');
        expect(localStorage.getItem('verso-color-scheme')).toBe('light');
    });

    it('still switches the colors when storage is blocked', () => {
        run(clickScript);
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new DOMException('Blocked', 'SecurityError');
        });

        button().click();

        expect(meta().content).toBe('dark');
        vi.restoreAllMocks();
    });

    it('follows a choice made in another tab', () => {
        run(clickScript);

        window.dispatchEvent(
            new StorageEvent('storage', {
                key: 'verso-color-scheme',
                newValue: 'dark',
            }),
        );
        expect(meta().content).toBe('dark');

        window.dispatchEvent(
            new StorageEvent('storage', {
                key: 'verso-color-scheme',
                newValue: null,
            }),
        );
        expect(meta().content).toBe('light dark');
    });

    it('works for buttons added after the script, like a second switch in the footer', () => {
        run(clickScript);
        document.body.insertAdjacentHTML(
            'beforeend',
            '<footer><button type="button" data-verso-scheme-toggle id="footer-switch">Switch</button></footer>',
        );

        (document.getElementById('footer-switch') as HTMLButtonElement).click();

        expect(meta().content).toBe('dark');
    });
});
