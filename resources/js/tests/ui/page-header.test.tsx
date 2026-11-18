import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PageHeader } from '@/components/verso/ui/page-header';
import { iconMarkup } from './icon-markup';

describe('PageHeader', () => {
    it('shows the title as the page heading', () => {
        render(<PageHeader title="Posts" />);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Posts' }),
        ).toBeInTheDocument();
    });

    it('shows the short description', () => {
        render(
            <PageHeader
                title="Media"
                description="Images, videos and files used on the site."
            />,
        );

        expect(
            screen.getByText('Images, videos and files used on the site.'),
        ).toBeInTheDocument();
    });

    it('leaves out the description when missing', () => {
        const { container } = render(<PageHeader title="Users" />);

        expect(container.querySelector('p')).toBeNull();
    });

    it('draws the Verso icon for the key', () => {
        const expected = iconMarkup('settings');
        const { container } = render(
            <PageHeader title="Settings" icon="settings" />,
        );

        expect(container.querySelector('svg')?.innerHTML).toBe(expected);
    });

    it('draws no icon without a key', () => {
        const { container } = render(<PageHeader title="Settings" />);

        expect(container.querySelector('svg')).toBeNull();
    });

    it('draws the fallback ring for an unknown key', () => {
        const expected = iconMarkup('dot');
        const { container } = render(
            <PageHeader title="Podcasts" icon="podcasts" />,
        );

        expect(container.querySelector('svg')?.innerHTML).toBe(expected);
    });

    it('places the actions after the title', () => {
        render(
            <PageHeader
                title="Pages"
                actions={
                    <>
                        <button type="button">Import</button>
                        <button type="button">New page</button>
                    </>
                }
            />,
        );

        const heading = screen.getByRole('heading', { name: 'Pages' });
        const button = screen.getByRole('button', { name: 'New page' });

        expect(
            heading.compareDocumentPosition(button) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(screen.getAllByRole('button')).toHaveLength(2);
    });

    it('accepts rich titles', () => {
        render(
            <PageHeader
                title={
                    <>
                        Edit <em>Spring menu</em>
                    </>
                }
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Edit Spring menu' }),
        ).toBeInTheDocument();
    });

    it('adds extra classes', () => {
        const { container } = render(
            <PageHeader title="Comments" className="mb-2" />,
        );

        expect(container.firstChild).toHaveClass('mb-2');
    });
});
