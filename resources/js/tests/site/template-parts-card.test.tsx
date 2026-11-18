import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { TemplatePartsCard } from '@/components/verso/site/template-parts-card';
import { router } from '@/tests/mocks/inertia';
import { parts as saveParts } from '@/routes/admin/site-editor/templates';
import type { TemplatePartsRow } from '@/types';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const parts = [
    {
        name: 'header',
        label: 'Header',
        default: 'classic',
        options: [
            { value: 'classic', label: 'Classic', description: '' },
            { value: 'minimal', label: 'Minimal', description: '' },
        ],
    },
];

const templates: TemplatePartsRow[] = [
    {
        name: 'wide',
        label: 'Wide page',
        themeParts: {},
        saved: {},
        styles: {},
    },
    {
        name: 'landing',
        label: 'Landing page',
        themeParts: { header: 'minimal' },
        saved: { header: 'classic' },
        styles: { header: 'classic' },
    },
];

describe('TemplatePartsCard', () => {
    beforeEach(() => {
        vi.mocked(router.put).mockReset();
    });

    it('shows nothing when the theme has no part styles', () => {
        const { container } = render(
            <TemplatePartsCard parts={[]} templates={templates} />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('lists each page template with the theme style and the saved one', () => {
        render(<TemplatePartsCard parts={parts} templates={templates} />);

        expect(
            screen.getByRole('heading', { name: 'Landing page' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('The theme gives it the Minimal header.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('The theme uses the site styles.'),
        ).toBeInTheDocument();
        expect(screen.getAllByLabelText('Header style')[1]).toHaveTextContent(
            'Classic',
        );
        expect(screen.getAllByLabelText('Header style')[0]).toHaveTextContent(
            'As the theme says',
        );
    });

    it('keeps Save off until something changes', async () => {
        render(<TemplatePartsCard parts={parts} templates={templates} />);

        const buttons = screen.getAllByRole('button', { name: 'Save' });
        expect(buttons[0]).toBeDisabled();
        expect(buttons[1]).toBeDisabled();
        await userEvent.click(buttons[1]);
        expect(router.put).not.toHaveBeenCalled();
        expect(saveParts.url('landing')).toBe(
            '/admin/site-editor/templates/landing/parts',
        );
    });
});
