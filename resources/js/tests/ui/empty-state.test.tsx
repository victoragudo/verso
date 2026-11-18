import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { EmptyState } from '@/components/verso/ui/empty-state';
import { iconMarkup } from './icon-markup';

describe('EmptyState', () => {
    it('shows the title', () => {
        render(<EmptyState title="No posts yet" />);

        expect(screen.getByText('No posts yet')).toBeInTheDocument();
    });

    it('draws the requested Verso icon', () => {
        const expected = iconMarkup('media');
        const { container } = render(
            <EmptyState icon="media" title="Your library is empty" />,
        );

        const svg = container.querySelector('svg');

        expect(svg?.innerHTML).toBe(expected);
        expect(svg).toHaveAttribute('width', '20');
        expect(svg).toHaveAttribute('aria-hidden', 'true');
    });

    it('draws the dot icon by default', () => {
        const expected = iconMarkup('dot');
        const { container } = render(<EmptyState title="Nothing here" />);

        expect(container.querySelector('svg')?.innerHTML).toBe(expected);
    });

    it('draws the dot icon for an unknown key', () => {
        const expected = iconMarkup('dot');
        const { container } = render(
            <EmptyState icon="weather" title="No forecasts" />,
        );

        expect(container.querySelector('svg')?.innerHTML).toBe(expected);
    });

    it('shows the description when given', () => {
        render(
            <EmptyState
                title="No comments"
                description="Comments from readers will show up here."
            />,
        );

        expect(
            screen.getByText('Comments from readers will show up here.'),
        ).toBeInTheDocument();
    });

    it('leaves out the description when missing', () => {
        const { container } = render(<EmptyState title="No comments" />);

        expect(container.querySelectorAll('p')).toHaveLength(1);
    });

    it('shows one action', () => {
        render(
            <EmptyState
                title="No pages yet"
                action={<a href="/admin/entries/page/create">New page</a>}
            />,
        );

        expect(screen.getByRole('link', { name: 'New page' })).toHaveAttribute(
            'href',
            '/admin/entries/page/create',
        );
    });

    it('leaves out the action wrapper when there is no action', () => {
        const { container } = render(<EmptyState title="No tags" />);

        expect(container.querySelector('.mt-4')).toBeNull();
    });

    it('adds extra classes to the frame', () => {
        const { container } = render(
            <EmptyState title="No redirects" className="py-24" />,
        );

        expect(container.firstChild).toHaveClass('py-24', 'text-center');
    });
});
