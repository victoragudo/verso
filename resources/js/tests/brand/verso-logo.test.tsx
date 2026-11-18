import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { VersoLogo } from '@/components/verso/brand/verso-logo';

describe('VersoLogo', () => {
    it('draws only the mark by default and names it Verso', () => {
        render(<VersoLogo className="size-8" />);

        const logo = screen.getByRole('img', { name: 'Verso' });

        expect(logo).toHaveAttribute('viewBox', '0 0 32 32');
        expect(logo).toHaveAttribute('data-variant', 'mark');
        expect(logo).toHaveClass('size-8');
        expect(logo.querySelectorAll('path')).toHaveLength(1);
    });

    it('adds the wordmark in the full variant', () => {
        render(<VersoLogo variant="full" />);

        const logo = screen.getByRole('img', { name: 'Verso' });

        expect(logo).toHaveAttribute('viewBox', '0 0 104 32');
        expect(logo.querySelectorAll('path')).toHaveLength(2);
    });

    it('paints with the current text color so it works in light and dark', () => {
        const { container } = render(<VersoLogo variant="full" />);

        expect(container.querySelector('g')).toHaveAttribute(
            'fill',
            'currentColor',
        );
        expect(container.innerHTML).not.toMatch(/#[0-9a-f]{3,6}/i);
    });

    it('gives each logo its own title id', () => {
        render(
            <>
                <VersoLogo />
                <VersoLogo variant="full" />
            </>,
        );

        const [first, second] = screen.getAllByRole('img', { name: 'Verso' });

        expect(first.getAttribute('aria-labelledby')).not.toBe(
            second.getAttribute('aria-labelledby'),
        );
    });

    it('can be hidden when a nearby text already names it', () => {
        render(<VersoLogo aria-hidden="true" />);

        expect(screen.queryByRole('img')).not.toBeInTheDocument();
    });
});
