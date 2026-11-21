import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    ChildThemeDialog,
    slugFromName,
} from '@/components/verso/site/child-theme-dialog';
import { childTheme } from '@/routes/admin/site-editor';
import { router } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

describe('ChildThemeDialog', () => {
    beforeEach(() => vi.mocked(router.post).mockReset());

    it('suggests a name and a folder and sends the choice', async () => {
        const user = userEvent.setup();
        render(<ChildThemeDialog themeName="Ashgrove" />);

        await user.click(
            screen.getByRole('button', { name: 'Create child theme' }),
        );

        expect(screen.getByLabelText('Theme name')).toHaveValue(
            'Ashgrove custom',
        );
        expect(
            screen.getByText('The theme is saved in themes/ashgrove-custom.'),
        ).toBeInTheDocument();

        await user.clear(screen.getByLabelText('Theme name'));
        await user.type(screen.getByLabelText('Theme name'), 'Café Søndag');
        await user.click(screen.getByLabelText('Use the new theme now'));
        await user.click(screen.getByRole('button', { name: 'Create theme' }));

        expect(router.post).toHaveBeenCalledWith(
            childTheme.url(),
            { name: 'Café Søndag', slug: '', activate: true },
            expect.any(Object),
        );
    });

    it('turns names into folder names', () => {
        expect(slugFromName('Harbor Light 2')).toBe('harbor-light-2');
        expect(slugFromName('  Café  ')).toBe('cafe');
        expect(slugFromName('!!!')).toBe('');
    });
});
