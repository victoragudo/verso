import type { HelpTopic } from '@/lib/easy/help-topics';

// Help topics for the help panel tests, in the order the server sends them.
export const helpTopics: HelpTopic[] = [
    {
        key: 'choose-starter',
        title: 'Choose a ready made site',
        summary: 'Get pages, menus and sample text in one click.',
        steps: ['Open "Guided start".', 'Press "Build my site".'],
        illustration: 'starter',
        routes: ['admin.easy.start', 'admin.dashboard'],
        keywords: ['template', 'plantilla'],
        video: null,
    },
    {
        key: 'edit-page',
        title: 'Change a page',
        summary: 'Click any text or picture on the page and change it.',
        steps: [
            'Click a title and type.',
            'Click a picture and choose "Replace image".',
        ],
        illustration: 'edit-page',
        routes: ['admin.easy.pages.edit', 'admin.entries.index'],
        keywords: ['editar'],
        video: null,
    },
    {
        key: 'change-menu',
        title: 'Change the menu',
        summary: 'Choose which pages show in the menu at the top.',
        steps: ['Open "Menus".', 'Press "Save items".'],
        illustration: 'menu',
        routes: ['admin.site.menus.index'],
        keywords: ['navigation', 'menú'],
        video: 'https://videos.harborlane.example/menu.mp4',
    },
    {
        key: 'write-post',
        title: 'Write a blog post',
        summary: 'Share news and stories.',
        steps: ['Open "Blog posts".', 'Press "Publish".'],
        illustration: 'blog-post',
        routes: ['admin.entries.index', 'admin.entries.create'],
        keywords: ['news'],
        video: null,
    },
    {
        key: 'upload-photos',
        title: 'Upload photos',
        summary: 'Keep your pictures in one place.',
        steps: ['Open "Photos and files".', 'Press "Upload files".'],
        illustration: 'bakery-oven',
        routes: ['admin.media.index'],
        keywords: ['pictures', 'fotos'],
        video: null,
    },
];
