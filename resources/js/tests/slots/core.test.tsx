import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    CommentsSwitchSlot,
    SeoSlot,
    clearSlotRegistry,
    registerCoreSlots,
    slotComponent,
    slotComponentOptions,
} from '@/components/verso/slots';
import { editorContext, slotItem } from './fixtures';

vi.mock('@/components/verso/media/media-api', () => ({
    fetchLibrary: vi.fn(),
    uploadMedia: vi.fn(),
    acceptAttribute: () => undefined,
}));

afterEach(() => clearSlotRegistry());

describe('core slot components', () => {
    it('registers the SEO card and the comments switch', () => {
        registerCoreSlots();

        expect(slotComponent('site.seo')).toBe(SeoSlot);
        expect(slotComponent('comments.switch')).toBe(CommentsSwitchSlot);
    });

    it('seeds the editor meta with clean SEO fields and the comments value', () => {
        registerCoreSlots();

        const seo = slotComponentOptions('site.seo').initialMeta?.(
            { seo: { title: 'Winter Menu', noindex: 'yes' } },
            {},
        );
        const comments = slotComponentOptions('comments.switch').initialMeta?.(
            {},
            { open: true },
        );

        expect(seo).toEqual({
            seo: {
                title: 'Winter Menu',
                description: null,
                canonical: null,
                noindex: false,
                og_image: null,
                sitemap_exclude: false,
                focus_keyphrase: null,
                nofollow: false,
                og_title: null,
                og_description: null,
                score: null,
            },
        });
        expect(comments).toEqual({ comments_open: true });
    });

    it('turns comments on and off through the editor meta', () => {
        const context = editorContext({ meta: { comments_open: true } });

        render(
            <CommentsSwitchSlot
                item={slotItem({ component: 'comments.switch' })}
                props={{ open: true }}
                context={context}
            />,
        );

        const checkbox = screen.getByRole('checkbox', {
            name: 'Allow comments',
        });
        expect(checkbox).toBeChecked();

        fireEvent.click(checkbox);

        expect(context.setMeta).toHaveBeenCalledWith('comments_open', false);
    });

    it('edits the SEO title through the editor meta', () => {
        const context = editorContext({
            meta: { seo: { title: 'Winter Menu', noindex: false } },
        });

        render(
            <SeoSlot
                item={slotItem({ component: 'site.seo' })}
                props={{}}
                context={context}
            />,
        );

        fireEvent.change(screen.getByLabelText('SEO title'), {
            target: { value: 'Winter Menu 2026' },
        });

        expect(context.setMeta).toHaveBeenCalledWith(
            'seo',
            expect.objectContaining({ title: 'Winter Menu 2026' }),
        );
    });
});
