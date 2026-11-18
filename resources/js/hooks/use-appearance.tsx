import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const STORAGE_KEY = 'appearance';
const APPEARANCES: readonly Appearance[] = ['light', 'dark', 'system'];
const listeners = new Set<() => void>();
const defaultAppearance: Appearance = 'light';
let currentAppearance: Appearance = defaultAppearance;

function isAppearance(value: unknown): value is Appearance {
    return APPEARANCES.includes(value as Appearance);
}

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const readCookie = (name: string): string | null => {
    const match = document.cookie
        .split('; ')
        .find((part) => part.startsWith(`${name}=`));

    return match ? match.slice(name.length + 1) : null;
};

// Storage can throw when site data is blocked; the choice then lives only in the cookie.
const readStorage = (): string | null => {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
};

const writeStorage = (value: Appearance): void => {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // The cookie still keeps the choice.
    }
};

// The cookie wins: the server used it to render the page, so reading it first avoids a flash.
const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return defaultAppearance;
    }

    const cookie = readCookie(STORAGE_KEY);

    if (isAppearance(cookie)) {
        return cookie;
    }

    const stored = readStorage();

    return isAppearance(stored) ? stored : defaultAppearance;
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

// Keeps the color-scheme meta tag in step: CSS maps it to color-scheme, so light-dark() follows it.
const updateMetaTag = (appearance: Appearance): void => {
    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="color-scheme"]',
    );

    if (meta) {
        meta.content = appearance === 'system' ? 'light dark' : appearance;
    }
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
    updateMetaTag(appearance);
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

// Another tab changed the appearance: follow it.
const handleStorage = (event: StorageEvent): void => {
    if (event.key !== STORAGE_KEY || !isAppearance(event.newValue)) {
        return;
    }

    currentAppearance = event.newValue;
    applyTheme(currentAppearance);
    notify();
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentAppearance = getStoredAppearance();
    writeStorage(currentAppearance);
    setCookie(STORAGE_KEY, currentAppearance);
    applyTheme(currentAppearance);

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
    window.addEventListener('storage', handleStorage);
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => defaultAppearance,
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;
        writeStorage(mode);
        setCookie(STORAGE_KEY, mode);
        applyTheme(mode);
        notify();
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
