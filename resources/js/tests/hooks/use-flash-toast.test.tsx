import { renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { OPEN_NAV_EVENT, useFlashToast } from '@/hooks/use-flash-toast';
import { fireRouterEvent } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const toastMock = vi.hoisted(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
}));

vi.mock('sonner', () => ({ toast: toastMock }));

afterEach(() => vi.clearAllMocks());

describe('useFlashToast', () => {
    it('shows a plain toast', () => {
        renderHook(() => useFlashToast());

        fireRouterEvent('flash', {
            flash: {
                toast: { type: 'success', message: 'Newsletter disabled.' },
            },
        });

        expect(toastMock.success).toHaveBeenCalledWith(
            'Newsletter disabled.',
            undefined,
        );
    });

    it('adds an Open button that asks the shell to open a sidebar item', () => {
        const opened = vi.fn();
        window.addEventListener(OPEN_NAV_EVENT, (event) =>
            opened((event as CustomEvent).detail),
        );
        renderHook(() => useFlashToast());

        fireRouterEvent('flash', {
            flash: {
                toast: {
                    type: 'success',
                    message: 'Shop is ready. You will find it in Tools > Shop.',
                    action: { label: 'Open', navKey: 'plugin.shop' },
                },
            },
        });

        const options = toastMock.success.mock.calls[0][1];
        expect(options.action.label).toBe('Open');

        options.action.onClick();

        expect(opened).toHaveBeenCalledWith('plugin.shop');
    });
});
