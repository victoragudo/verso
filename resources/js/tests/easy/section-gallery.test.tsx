import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { SectionGallery } from '@/components/verso/easy/section-gallery';
import { coreSections } from './page-fixtures';

function renderGallery(
    props: Partial<Parameters<typeof SectionGallery>[0]> = {},
) {
    const onPick = vi.fn();
    render(
        <SectionGallery
            open
            onOpenChange={vi.fn()}
            sections={coreSections}
            busyKey={null}
            error={null}
            onPick={onPick}
            {...props}
        />,
    );

    return { onPick };
}

describe('SectionGallery', () => {
    it('shows every section with its drawing and description', () => {
        renderGallery();

        expect(
            screen.getByRole('dialog', { name: 'Add a section' }),
        ).toBeInTheDocument();
        expect(
            screen.getAllByRole('button', { name: /for your site/ }),
        ).toHaveLength(5);
        expect(
            document.querySelector('[data-section-drawing="pricing"]'),
        ).not.toBeNull();
        expect(screen.getByText('5 sections')).toBeInTheDocument();
    });

    it('searches and filters by kind of section', async () => {
        const user = userEvent.setup();
        renderGallery();

        await user.type(screen.getByLabelText('Search sections'), 'precios');
        expect(
            screen
                .getAllByRole('button', { name: /for your site/ })
                .map((item) => item.dataset.test),
        ).toEqual(['section-pricing']);
        expect(screen.getByText('1 section')).toBeInTheDocument();

        await user.clear(screen.getByLabelText('Search sections'));
        await user.click(screen.getByRole('radio', { name: 'Contact' }));
        expect(
            screen
                .getAllByRole('button', { name: /for your site/ })
                .map((item) => item.dataset.test),
        ).toEqual(['section-map', 'section-contact']);

        await user.type(screen.getByLabelText('Search sections'), 'fireworks');
        expect(
            screen.getByText(
                'No section matches “fireworks”. Try another word.',
            ),
        ).toBeInTheDocument();
    });

    it('picks a section', async () => {
        const user = userEvent.setup();
        const { onPick } = renderGallery();

        await user.click(
            screen.getByRole('button', { name: /Questions and answers/ }),
        );
        expect(onPick).toHaveBeenCalledWith('faq');
    });

    it('waits while a section is added and shows plain errors', () => {
        renderGallery({
            busyKey: 'map',
            error: 'This section could not be added. Try again in a moment.',
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'This section could not be added.',
        );
        expect(screen.getByRole('button', { name: /^Map/ })).toHaveAttribute(
            'aria-busy',
            'true',
        );
        screen
            .getAllByRole('button', { name: /for your site/ })
            .forEach((item) => expect(item).toBeDisabled());
    });
});
