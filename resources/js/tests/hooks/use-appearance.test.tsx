import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// The module keeps the appearance in module state, so each test loads a fresh copy.
async function loadAppearance() {
    vi.resetModules();

    return import('@/hooks/use-appearance');
}

function clearCookie() {
    document.cookie = 'appearance=;path=/;max-age=0';
}

describe('admin appearance', () => {
    let meta: HTMLMetaElement;

    beforeEach(() => {
        meta = document.createElement('meta');
        meta.name = 'color-scheme';
        meta.content = 'light';
        document.head.append(meta);
        localStorage.clear();
        clearCookie();
    });

    afterEach(() => {
        meta.remove();
        clearCookie();
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = '';
    });

    it('trusts the cookie the server rendered with over local storage', async () => {
        document.cookie = 'appearance=dark;path=/';
        localStorage.setItem('appearance', 'light');
        const { initializeTheme } = await loadAppearance();

        initializeTheme();

        expect(document.documentElement).toHaveClass('dark');
        expect(meta.content).toBe('dark');
        expect(localStorage.getItem('appearance')).toBe('dark');
    });

    it('writes the choice to the color-scheme meta tag', async () => {
        const { initializeTheme, useAppearance } = await loadAppearance();
        initializeTheme();
        const { result } = renderHook(() => useAppearance());

        act(() => result.current.updateAppearance('system'));

        expect(meta.content).toBe('light dark');
        expect(document.cookie).toContain('appearance=system');
    });

    it('follows a change made in another tab', async () => {
        const { initializeTheme, useAppearance } = await loadAppearance();
        initializeTheme();
        const { result } = renderHook(() => useAppearance());

        act(() => {
            window.dispatchEvent(
                new StorageEvent('storage', {
                    key: 'appearance',
                    newValue: 'dark',
                }),
            );
        });

        expect(result.current.appearance).toBe('dark');
        expect(document.documentElement).toHaveClass('dark');
    });

    it('still works when local storage throws', async () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('Site data is blocked');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('Site data is blocked');
        });
        const { initializeTheme, useAppearance } = await loadAppearance();

        expect(() => initializeTheme()).not.toThrow();
        const { result } = renderHook(() => useAppearance());
        act(() => result.current.updateAppearance('dark'));

        expect(document.documentElement).toHaveClass('dark');
        expect(document.cookie).toContain('appearance=dark');
    });
});
