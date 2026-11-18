import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { RemoveExtensionDialog } from '@/components/verso/extensions/remove-extension-dialog';
import { router } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

type DeleteOptions = { data: { delete_data: boolean }; onFinish: () => void };

describe('RemoveExtensionDialog', () => {
    beforeEach(() => {
        router.delete.mockReset();
    });

    it('stays closed without an extension', () => {
        render(<RemoveExtensionDialog extension={null} onClose={vi.fn()} />);

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('explains that plugin tables and settings can be kept', () => {
        render(
            <RemoveExtensionDialog
                extension={{
                    kind: 'plugin',
                    slug: 'tour-dates',
                    name: 'Tour Dates',
                }}
                onClose={vi.fn()}
            />,
        );

        expect(screen.getByRole('dialog')).toHaveTextContent(
            'Remove Tour Dates?',
        );
        expect(screen.getByRole('dialog')).toHaveTextContent(
            'its database tables and saved settings',
        );
    });

    it('removes a plugin and keeps its data', async () => {
        const user = userEvent.setup();
        const onClose = vi.fn();
        render(
            <RemoveExtensionDialog
                extension={{
                    kind: 'plugin',
                    slug: 'tour-dates',
                    name: 'Tour Dates',
                }}
                onClose={onClose}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Remove, keep data' }),
        );

        expect(router.delete).toHaveBeenCalledTimes(1);
        const [url, options] = router.delete.mock.calls[0] as [
            string,
            DeleteOptions,
        ];
        expect(url).toBe('/admin/extensions/plugin/tour-dates');
        expect(options.data).toEqual({ delete_data: false });

        options.onFinish();
        expect(onClose).toHaveBeenCalled();
    });

    it('removes a theme with its settings', async () => {
        const user = userEvent.setup();
        render(
            <RemoveExtensionDialog
                extension={{
                    kind: 'theme',
                    slug: 'lighthouse',
                    name: 'Lighthouse',
                }}
                onClose={vi.fn()}
            />,
        );

        expect(screen.getByRole('dialog')).toHaveTextContent(
            'its saved customizer settings and its Site Editor changes',
        );

        await user.click(
            screen.getByRole('button', { name: 'Remove and delete data' }),
        );

        const [url, options] = router.delete.mock.calls[0] as [
            string,
            DeleteOptions,
        ];
        expect(url).toBe('/admin/extensions/theme/lighthouse');
        expect(options.data).toEqual({ delete_data: true });
    });
});
