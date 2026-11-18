<?php

namespace App\Verso\Easy\Help;

use App\Verso\Core\Enums\Capability;

// The core topics of the help panel, in plain words, each with its own drawing.
class CoreHelpTopics
{
    public function register(HelpTopics $topics): void
    {
        foreach ($this->topics() as $topic) {
            $topics->register($topic);
        }
    }

    /** @return list<HelpTopic> */
    protected function topics(): array
    {
        return [
            new HelpTopic(
                key: 'choose-starter',
                title: 'Choose a ready made site',
                summary: 'Get pages, menus and sample text for your kind of site in one click.',
                steps: [
                    'Open "Guided start" in the menu.',
                    'Pick the kind of site that is closest to yours, like a restaurant or a portfolio.',
                    'Press "Build my site". Your own pages stay as they are.',
                    'Change the sample texts and pictures to your own.',
                ],
                illustration: 'starter',
                routes: ['admin.easy.start', 'admin.dashboard'],
                keywords: ['starter', 'template', 'start'],
                order: 10,
                capability: Capability::ManageSettings,
            ),
            new HelpTopic(
                key: 'make-it-yours',
                title: 'Make it yours',
                summary: 'Add your logo and choose colors and fonts that fit your brand.',
                steps: [
                    'Open "Make it yours" in the menu.',
                    'Drop your logo in the box. We take colors from it.',
                    'Pick a color set and a font pair. The preview shows your site as you go.',
                    'Press "Save" when you like what you see.',
                ],
                illustration: 'style',
                routes: ['admin.easy.style', 'admin.site-editor'],
                keywords: ['logo', 'colors', 'fonts', 'brand'],
                order: 20,
                capability: Capability::ManageThemes,
            ),
            new HelpTopic(
                key: 'edit-page',
                title: 'Change a page',
                summary: 'Click any text or picture on the page and change it right there.',
                steps: [
                    'Open "Pages" and choose "Edit on the page", or press "Edit page" on your site.',
                    'Click a title or a text and type. Press Escape to stop.',
                    'Click a picture and choose "Replace image".',
                    'Press "Save". Visitors see the change right away.',
                ],
                illustration: 'edit-page',
                routes: ['admin.easy.pages.edit', 'admin.entries.index', 'admin.entries.edit'],
                keywords: ['edit', 'text', 'change'],
                order: 30,
            ),
            new HelpTopic(
                key: 'add-section',
                title: 'Add a section',
                summary: 'Add a ready block like prices, a gallery or a map to a page.',
                steps: [
                    'Open the page with "Edit on the page".',
                    'Move the mouse between two sections and press "Add section".',
                    'Pick a section from the gallery. It comes with sample text.',
                    'Change the texts, then press "Save".',
                ],
                illustration: 'add-section',
                routes: ['admin.easy.pages.edit'],
                keywords: ['section', 'block', 'gallery', 'map', 'prices'],
                order: 40,
            ),
            new HelpTopic(
                key: 'change-menu',
                title: 'Change the menu',
                summary: 'Choose which pages show in the menu at the top of your site, and in which order.',
                steps: [
                    'Open "Menus".',
                    'Pick the main menu.',
                    'Add a page from the panels next to the menu, or drag a link by its handle to move it.',
                    'Press "Save items".',
                ],
                illustration: 'menu',
                routes: ['admin.site.menus.index'],
                keywords: ['menu', 'navigation', 'links'],
                order: 50,
                capability: Capability::ManageThemes,
            ),
            new HelpTopic(
                key: 'write-post',
                title: 'Write a blog post',
                summary: 'Share news, tips or stories. New posts show on your blog page.',
                steps: [
                    'Open "Blog posts" and press "New post".',
                    'Give the post a title and write your text.',
                    'Add a picture with the "+" button.',
                    'Press "Publish" when you are ready.',
                ],
                illustration: 'blog-post',
                routes: ['admin.entries.index', 'admin.entries.create', 'admin.entries.edit'],
                keywords: ['post', 'blog', 'news', 'article'],
                order: 60,
            ),
            new HelpTopic(
                key: 'upload-photos',
                title: 'Upload photos',
                summary: 'Keep your pictures in one place and use them on any page.',
                steps: [
                    'Open "Photos and files".',
                    'Drag pictures from your computer into the window, or press "Upload files".',
                    'Add a short description of each picture for people who cannot see it.',
                    'Use the pictures on your pages with "Replace image".',
                ],
                illustration: 'photos',
                routes: ['admin.media.index'],
                keywords: ['photos', 'pictures', 'images', 'upload', 'media'],
                order: 70,
            ),
            new HelpTopic(
                key: 'go-live',
                title: 'Go live',
                summary: 'Let search engines find your site when it is ready.',
                steps: [
                    'Work through the "Launch checklist" on the dashboard.',
                    'Check your pages, contact details and privacy page.',
                    'Press "Go live". Search engines may now show your site.',
                    'Share your address with friends and customers.',
                ],
                illustration: 'go-live',
                routes: ['admin.dashboard', 'admin.easy.details'],
                keywords: ['launch', 'publish', 'search engines', 'google'],
                order: 80,
                capability: Capability::ManageSettings,
            ),
        ];
    }
}
