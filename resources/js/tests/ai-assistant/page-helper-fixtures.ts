import { vi } from 'vitest';
import type { PageEditorContext } from '@/lib/easy/page-editor-registry';
import type { PageHelperProps } from '@/pages/admin/plugins/ai-assistant/page-helper/types';
import type { Block } from '@/types';

// Server props of the page editor helper for a site with a saved key.
export function helperProps(
    overrides: Partial<PageHelperProps> = {},
): PageHelperProps {
    return {
        ready: true,
        settingsUrl: null,
        endpoints: {
            sectionText: '/admin/ai-assistant/actions/section-text',
            headlines: '/admin/ai-assistant/actions/headlines',
            translate: '/admin/ai-assistant/actions/translate',
            altText: '/admin/ai-assistant/actions/alt-text',
            business: '/admin/ai-assistant/business',
        },
        locales: [
            { code: 'es', name: 'Spanish' },
            { code: 'fr', name: 'French' },
        ],
        business:
            'Family bakery in Harbor Lane. Sourdough and coffee from 7am.',
        businessMax: 1000,
        maxImages: 12,
        ...overrides,
    };
}

// The welcome section of the bakery page: heading, text, button and a picture without a description.
export function heroSection(): Block {
    return {
        id: 'b_hero0001',
        type: 'section',
        attrs: { tag: 'section', label: 'Welcome' },
        content: '',
        children: [
            {
                id: 'b_title001',
                type: 'heading',
                attrs: { level: 1 },
                content: 'Welcome to our shop',
                children: [],
            },
            {
                id: 'b_text0001',
                type: 'paragraph',
                attrs: {},
                content: '<p>We sell <strong>good things</strong>.</p>',
                children: [],
            },
            {
                id: 'b_button01',
                type: 'button',
                attrs: { label: 'Learn more', url: '/menu', style: 'primary' },
                content: '',
                children: [],
            },
            {
                id: 'b_photo001',
                type: 'image',
                attrs: { mediaId: 7, src: '/storage/loaves.jpg', alt: '' },
                content: '',
                children: [],
            },
        ],
    };
}

// A gallery section: one picture has a description, two do not.
export function gallerySection(): Block {
    return {
        id: 'b_gall0001',
        type: 'section',
        attrs: { tag: 'section' },
        content: '',
        children: [
            {
                id: 'b_gallery1',
                type: 'gallery',
                attrs: {
                    mediaIds: [11, 12, 13],
                    images: [
                        { mediaId: 11, src: '/storage/buns.jpg', alt: '' },
                        {
                            mediaId: 12,
                            src: '/storage/oven.jpg',
                            alt: 'The stone oven at dawn',
                        },
                        { mediaId: 13, src: '/storage/coffee.jpg' },
                    ],
                },
                content: '',
                children: [],
            },
        ],
    };
}

export function bakeryPage(): Block[] {
    return [heroSection(), gallerySection()];
}

// A page editor context with spies for change and announce.
export function editorContext(blocks: Block[] = bakeryPage()) {
    return {
        entry: { id: 12, title: 'Harbor Lane Bakery', type: 'page' },
        blocks,
        change: vi.fn(),
        announce: vi.fn(),
    } satisfies PageEditorContext;
}

// A JSON answer of the assistant endpoints.
export function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}
