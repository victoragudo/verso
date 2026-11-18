import type { SectionInfo } from '@/lib/easy/sections';
import type { Block } from '@/types';

// A small bakery page: a welcome banner, services and a gallery.
export function bakeryBlocks(): Block[] {
    return [
        {
            id: 'b_hero0001',
            type: 'section',
            attrs: {
                tag: 'section',
                label: 'Fresh bread on Harbour Lane',
                design: {
                    spacing: { padding: { top: 'var:spacing.x-large' } },
                },
            },
            content: '',
            children: [
                {
                    id: 'b_title001',
                    type: 'heading',
                    attrs: { level: 2 },
                    content: 'Fresh bread on Harbour Lane',
                    children: [],
                },
                {
                    id: 'b_text0001',
                    type: 'paragraph',
                    attrs: {},
                    content: '<p>Baked at five, gone by noon.</p>',
                    children: [],
                },
                {
                    id: 'b_button01',
                    type: 'button',
                    attrs: {
                        label: 'See the menu',
                        url: '/menu',
                        style: 'primary',
                    },
                    content: '',
                    children: [],
                },
            ],
        },
        {
            id: 'b_serv0001',
            type: 'section',
            attrs: { tag: 'section' },
            content: '',
            children: [
                {
                    id: 'b_title002',
                    type: 'heading',
                    attrs: { level: 2 },
                    content: 'What we <strong>bake</strong>',
                    children: [],
                },
                {
                    id: 'b_image001',
                    type: 'image',
                    attrs: {
                        mediaId: 4,
                        src: '/storage/loaves.jpg',
                        alt: 'Loaves',
                        width: 1200,
                        height: 800,
                    },
                    content: '',
                    children: [],
                },
            ],
        },
        {
            id: 'b_gall0001',
            type: 'section',
            attrs: { tag: 'section', label: 'Gallery' },
            content: '',
            children: [
                {
                    id: 'b_gallery1',
                    type: 'gallery',
                    attrs: {
                        columns: 2,
                        mediaIds: [7, 8],
                        images: [
                            {
                                mediaId: 7,
                                src: '/storage/terrace.jpg',
                                alt: 'Terrace',
                            },
                            {
                                mediaId: 8,
                                src: '/storage/oven.jpg',
                                alt: 'Oven',
                            },
                        ],
                    },
                    content: '',
                    children: [],
                },
            ],
        },
    ];
}

export const croissantPhoto = {
    id: 21,
    url: '/storage/croissants.jpg',
    alt: 'Croissants on a tray',
    width: 1600,
    height: 1067,
};

const section = (
    key: string,
    label: string,
    category: string,
    keywords: string[] = [],
): SectionInfo => ({
    key,
    label,
    description: `${label} for your site.`,
    icon: 'site',
    category,
    keywords,
    plugin: null,
});

// Core sections as SectionLibrary::toArray() lists them.
export const coreSections: SectionInfo[] = [
    section('hero', 'Welcome banner', 'intro', ['hero', 'portada']),
    section('pricing', 'Prices', 'sales', ['plans', 'precios']),
    section('faq', 'Questions and answers', 'help', ['preguntas']),
    section('map', 'Map', 'contact', ['location', 'mapa']),
    section('contact', 'Contact', 'contact', ['form']),
];
