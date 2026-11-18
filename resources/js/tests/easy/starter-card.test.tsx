import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { StarterCard } from '@/components/verso/easy/starter-card';
import { StarterPreview } from '@/components/verso/easy/starter-preview';
import { blog, restaurant } from './fixtures';

describe('StarterCard', () => {
    it('is a radio named by the starter and described by its pages', () => {
        render(
            <StarterCard
                starter={restaurant}
                checked={false}
                current={false}
                onSelect={() => {}}
            />,
        );

        const radio = screen.getByRole('radio', { name: 'Restaurant or cafe' });

        expect(radio).toHaveAccessibleDescription(
            expect.stringContaining('Pages: Home, Menu, Visit us'),
        );
        expect(radio).toHaveAccessibleDescription(
            expect.stringContaining('Turns on: Contact forms'),
        );
        expect(screen.queryByText('Your site now')).toBeNull();
    });

    it('selects the starter when the card is clicked', async () => {
        const onSelect = vi.fn();
        render(
            <StarterCard
                starter={blog}
                checked={false}
                current
                onSelect={onSelect}
            />,
        );

        await userEvent.click(screen.getByText('Write about what you love.'));

        expect(onSelect).toHaveBeenCalledWith('blog');
        expect(screen.getByText('Your site now')).toBeInTheDocument();
        expect(screen.getByText('2 sample posts')).toBeInTheDocument();
    });

    it('cannot be picked while a site is being built', () => {
        render(
            <StarterCard
                starter={blog}
                checked
                current={false}
                disabled
                onSelect={() => {}}
            />,
        );

        expect(screen.getByRole('radio', { name: 'Blog' })).toBeDisabled();
    });
});

describe('StarterPreview', () => {
    it('draws the first sections of the home page and hides the drawing from screen readers', () => {
        render(
            <StarterPreview
                sections={['hero', 'services', 'map', 'faq', 'cta']}
            />,
        );

        const preview = screen.getByTestId('starter-preview');
        const shapes = [...preview.querySelectorAll('[data-shape]')].map(
            (element) => element.getAttribute('data-shape'),
        );

        expect(preview).toHaveAttribute('aria-hidden', 'true');
        expect(shapes).toEqual(['banner', 'cards', 'map', 'list']);
    });
});
