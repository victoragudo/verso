<?php

namespace App\Verso\Easy\Sections;

// The ready sections that come with Verso. They use only core blocks and the map block, styled with theme tokens.
class CoreSections
{
    // Grid columns of card sections on wide screens.
    protected const CARD_COLUMNS = 3;

    // Gallery columns on wide screens.
    protected const GALLERY_COLUMNS = 3;

    public function register(SectionLibrary $library): void
    {
        foreach ($this->sections() as $section) {
            $library->register($section);
        }
    }

    /**
     * @return list<SectionTemplate>
     */
    public function sections(): array
    {
        $samples = $this->samples();

        return [
            new SectionTemplate(
                key: 'hero',
                label: 'Welcome banner',
                description: 'A big title, a short line and a button at the top of a page.',
                builder: $this->hero(...),
                icon: 'site',
                category: 'intro',
                keywords: ['hero', 'banner', 'header', 'title'],
                defaults: $samples['hero'],
            ),
            new SectionTemplate(
                key: 'about',
                label: 'Text and picture',
                description: 'A title with a few paragraphs about you, with a picture on the side.',
                builder: $this->about(...),
                icon: 'pages',
                category: 'content',
                keywords: ['about', 'text', 'story', 'intro'],
                defaults: $samples['about'],
            ),
            new SectionTemplate(
                key: 'services',
                label: 'Services',
                description: 'Cards that list what you offer, each with a title and a short text.',
                builder: $this->services(...),
                icon: 'entries',
                category: 'content',
                keywords: ['services', 'features', 'offer', 'cards'],
                defaults: $samples['services'],
            ),
            new SectionTemplate(
                key: 'gallery',
                label: 'Picture gallery',
                description: 'A grid of pictures, like your work, your place or your products.',
                builder: $this->gallery(...),
                icon: 'media',
                category: 'media',
                keywords: ['gallery', 'photos', 'pictures', 'portfolio'],
                defaults: $samples['gallery'],
            ),
            new SectionTemplate(
                key: 'testimonials',
                label: 'Testimonials',
                description: 'Short quotes from happy customers, with their names.',
                builder: $this->testimonials(...),
                icon: 'comments',
                category: 'trust',
                keywords: ['testimonials', 'reviews', 'quotes'],
                defaults: $samples['testimonials'],
            ),
            new SectionTemplate(
                key: 'pricing',
                label: 'Prices',
                description: 'Price cards with what each choice includes and a button.',
                builder: $this->pricing(...),
                icon: 'shop',
                category: 'sales',
                keywords: ['pricing', 'prices', 'plans', 'tickets'],
                defaults: $samples['pricing'],
            ),
            new SectionTemplate(
                key: 'team',
                label: 'Team',
                description: 'The people behind your work, with a photo, a role and a short line.',
                builder: $this->team(...),
                icon: 'users',
                category: 'people',
                keywords: ['team', 'people', 'staff', 'about us'],
                defaults: $samples['team'],
            ),
            new SectionTemplate(
                key: 'faq',
                label: 'Questions and answers',
                description: 'Common questions with short answers, so visitors find help fast.',
                builder: $this->faq(...),
                icon: 'search',
                category: 'help',
                keywords: ['faq', 'questions', 'help', 'answers'],
                defaults: $samples['faq'],
            ),
            new SectionTemplate(
                key: 'contact',
                label: 'Contact',
                description: 'Your address, phone and email, with a contact form when the Forms plugin is on.',
                builder: $this->contact(...),
                icon: 'forms',
                category: 'contact',
                keywords: ['contact', 'form', 'email', 'phone', 'address'],
                defaults: $samples['contact'],
            ),
            new SectionTemplate(
                key: 'map',
                label: 'Map',
                description: 'A map with a pin where you are, from OpenStreetMap, without tracking.',
                builder: $this->map(...),
                icon: 'site',
                category: 'contact',
                keywords: ['map', 'location', 'address', 'directions'],
                defaults: $samples['map'],
            ),
            new SectionTemplate(
                key: 'cta',
                label: 'Call to action',
                description: 'A short closing line with one clear button, like "Book a table".',
                builder: $this->cta(...),
                icon: 'sparkles',
                category: 'action',
                keywords: ['call to action', 'cta', 'button', 'banner'],
                defaults: $samples['cta'],
            ),
        ];
    }

    /**
     * Sample content of each section. It is site content, so it stays in English like the starter sites.
     *
     * @return array<string, SectionContent>
     */
    protected function samples(): array
    {
        // i18n-ignore-start
        return [
            'hero' => new SectionContent(
                heading: 'Welcome to our place',
                text: 'Tell visitors in one or two short lines what you do and why they will like it.',
                button: ['label' => 'Get in touch', 'url' => '/contact'],
            ),
            'about' => new SectionContent(
                heading: 'About us',
                text: "Share your story: how you started, what you care about and what makes you different.\n\nKeep it short and friendly. People read the first lines most.",
            ),
            'services' => new SectionContent(
                heading: 'What we offer',
                items: [
                    ['title' => 'First service', 'text' => 'One or two lines about this service.'],
                    ['title' => 'Second service', 'text' => 'One or two lines about this service.'],
                    ['title' => 'Third service', 'text' => 'One or two lines about this service.'],
                ],
            ),
            'gallery' => new SectionContent(heading: 'Gallery'),
            'testimonials' => new SectionContent(
                heading: 'What people say',
                items: [
                    ['text' => 'Friendly, quick and exactly what we needed.', 'title' => 'Laura Gómez', 'meta' => 'Customer since 2021'],
                    ['text' => 'We have recommended them to all our friends.', 'title' => 'Tom Becker', 'meta' => 'Regular customer'],
                ],
            ),
            'pricing' => new SectionContent(
                heading: 'Prices',
                items: [
                    ['title' => 'Basic', 'price' => '€29', 'text' => 'For a first try.', 'features' => ['One visit', 'Email support']],
                    ['title' => 'Plus', 'price' => '€59', 'text' => 'Our most chosen option.', 'features' => ['Three visits', 'Phone support'], 'highlight' => true],
                ],
                button: ['label' => 'Ask us', 'url' => '/contact'],
            ),
            'team' => new SectionContent(
                heading: 'Meet the team',
                items: [
                    ['title' => 'Ana Ribeiro', 'meta' => 'Founder', 'text' => 'Started it all in 2015.'],
                    ['title' => 'Pablo Ortega', 'meta' => 'Customer care', 'text' => 'Answers every message within a day.'],
                ],
            ),
            'faq' => new SectionContent(
                heading: 'Questions and answers',
                items: [
                    ['title' => 'How can I contact you?', 'text' => 'Write to us with the contact form or call us during opening hours.'],
                    ['title' => 'Where are you?', 'text' => 'You find our address and a map on the contact page.'],
                ],
            ),
            'contact' => new SectionContent(
                heading: 'Get in touch',
                text: 'We are happy to hear from you. We answer within one working day.',
            ),
            'map' => new SectionContent(
                heading: 'Find us',
                extra: ['latitude' => 40.4168, 'longitude' => -3.7038, 'label' => 'Our place'],
            ),
            'cta' => new SectionContent(
                heading: 'Ready to start?',
                text: 'Send us a message and we will get back to you soon.',
                button: ['label' => 'Contact us', 'url' => '/contact'],
            ),
        ];
        // i18n-ignore-end
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function hero(SectionContent $content, SectionKit $kit): array
    {
        $text = [
            $kit->heading($content->heading, 2, ['typography' => ['fontSize' => ['base' => '2.75rem', 'mobile' => '2rem'], 'lineHeight' => 1.15]]),
            $content->text !== '' ? $kit->paragraph($content->text, ['typography' => ['fontSize' => '1.25rem']]) : null,
            $kit->button($content->button),
        ];
        $image = $kit->image($content->images[0] ?? null);

        return [$kit->section(
            [$image === null ? $kit->block('section', ['tag' => 'div'], children: array_values(array_filter($text))) : $kit->columns([$text, [$image]])],
            ['color' => ['background' => SectionKit::COLOR_SURFACE], 'border' => ['radius' => SectionKit::RADIUS], 'spacing' => ['padding' => [
                'left' => SectionKit::SPACE_LARGE, 'right' => SectionKit::SPACE_LARGE,
            ]]],
            $content->heading,
        )];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function about(SectionContent $content, SectionKit $kit): array
    {
        $text = [$kit->heading($content->heading), ...$kit->paragraphs($content->text), $kit->button($content->button, 'secondary')];
        $image = $kit->image($content->images[0] ?? null);

        return [$kit->section($image === null ? $text : [$kit->columns([$text, [$image]])], label: $content->heading)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function services(SectionContent $content, SectionKit $kit): array
    {
        $cards = array_map(fn (array $item): array => $kit->card([
            $kit->image($this->itemImage($item)),
            $kit->heading($this->itemText($item, 'title'), 3),
            $this->itemText($item, 'text') !== '' ? $kit->paragraph($this->itemText($item, 'text')) : null,
        ]), $content->items);

        return [$kit->section([
            $kit->heading($content->heading),
            $content->text !== '' ? $kit->paragraph($content->text) : null,
            $kit->grid($cards, self::CARD_COLUMNS),
            $kit->button($content->button, 'secondary'),
        ], label: $content->heading)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function gallery(SectionContent $content, SectionKit $kit): array
    {
        return [$kit->section([
            $kit->heading($content->heading),
            $content->text !== '' ? $kit->paragraph($content->text) : null,
            $kit->gallery($content->images, (int) ($content->extra['columns'] ?? self::GALLERY_COLUMNS)),
        ], label: $content->heading)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function testimonials(SectionContent $content, SectionKit $kit): array
    {
        $cards = array_map(fn (array $item): array => $kit->card([
            $kit->paragraph('“'.$this->itemText($item, 'text').'”', ['typography' => ['fontSize' => '1.125rem']]),
            $kit->paragraph(trim($this->itemText($item, 'title').($this->itemText($item, 'meta') !== '' ? ', '.$this->itemText($item, 'meta') : '')), [
                'typography' => ['fontWeight' => 600],
            ]),
        ]), $content->items);

        return [$kit->section([$kit->heading($content->heading), $kit->grid($cards, min(self::CARD_COLUMNS, max(1, count($cards))))], label: $content->heading)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function pricing(SectionContent $content, SectionKit $kit): array
    {
        $cards = array_map(fn (array $item): array => $kit->card([
            $kit->heading($this->itemText($item, 'title'), 3),
            $kit->paragraph($this->itemText($item, 'price'), ['typography' => ['fontSize' => '2rem', 'fontWeight' => 700]]),
            $this->itemText($item, 'text') !== '' ? $kit->paragraph($this->itemText($item, 'text')) : null,
            $kit->list($this->itemList($item, 'features')),
            $kit->button($this->itemButton($item) ?? $content->button, ($item['highlight'] ?? false) === true ? 'primary' : 'secondary'),
        ], ($item['highlight'] ?? false) === true), $content->items);

        return [$kit->section([
            $kit->heading($content->heading),
            $content->text !== '' ? $kit->paragraph($content->text) : null,
            $kit->grid($cards, min(self::CARD_COLUMNS, max(1, count($cards)))),
        ], label: $content->heading)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function team(SectionContent $content, SectionKit $kit): array
    {
        $cards = array_map(fn (array $item): array => $kit->card([
            $kit->image($this->itemImage($item)),
            $kit->heading($this->itemText($item, 'title'), 3),
            $this->itemText($item, 'meta') !== '' ? $kit->paragraph($this->itemText($item, 'meta'), ['typography' => ['fontWeight' => 600]]) : null,
            $this->itemText($item, 'text') !== '' ? $kit->paragraph($this->itemText($item, 'text')) : null,
        ]), $content->items);

        return [$kit->section([$kit->heading($content->heading), $kit->grid($cards, self::CARD_COLUMNS)], label: $content->heading)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function faq(SectionContent $content, SectionKit $kit): array
    {
        $questions = [];

        foreach ($content->items as $item) {
            $questions[] = $kit->heading($this->itemText($item, 'title'), 3);
            $questions[] = $kit->paragraph($this->itemText($item, 'text'));
        }

        return [$kit->section([$kit->heading($content->heading), ...$questions], label: $content->heading)];
    }

    /**
     * The form block only shows when the Forms plugin is on and the contact form exists. Otherwise the details stay alone.
     *
     * @return list<array<string, mixed>>
     */
    protected function contact(SectionContent $content, SectionKit $kit): array
    {
        $details = array_values(array_filter([
            $content->extraText('address') !== '' ? 'Address: '.$content->extraText('address') : '',
            $content->extraText('phone') !== '' ? 'Phone: '.$content->extraText('phone') : '',
            $content->extraText('email') !== '' ? 'Email: '.$content->extraText('email') : '',
            $content->extraText('hours') !== '' ? 'Opening hours: '.$content->extraText('hours') : '',
        ]));
        $info = [
            $kit->heading($content->heading),
            $content->text !== '' ? $kit->paragraph($content->text) : null,
            $kit->list($details),
        ];

        $form = [
            'form' => (string) config('verso.easy.contact_form'),
            'title' => $content->extraText('form_title', 'Send us a message'),
            'text' => $content->extraText('form_text', 'Fields marked with * are required.'),
        ];

        if (! $kit->hasPlugin('forms') || ! $kit->accepts('form', $form)) {
            return [$kit->section([...$info, $kit->button($content->button)], label: $content->heading)];
        }

        return [$kit->section([$kit->columns([$info, [$kit->block('form', $form)]])], label: $content->heading)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function map(SectionContent $content, SectionKit $kit): array
    {
        $map = $kit->block('map', array_filter([
            'latitude' => is_numeric($content->extra['latitude'] ?? null) ? (float) $content->extra['latitude'] : null,
            'longitude' => is_numeric($content->extra['longitude'] ?? null) ? (float) $content->extra['longitude'] : null,
            'zoom' => is_numeric($content->extra['zoom'] ?? null) ? (int) $content->extra['zoom'] : (int) config('verso.easy.map.default_zoom'),
            'label' => $content->extraText('label'),
            'design' => ['border' => ['radius' => SectionKit::RADIUS]],
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        return [$kit->section([
            $kit->heading($content->heading),
            $content->text !== '' ? $kit->paragraph($content->text) : null,
            $map,
            $content->extraText('address') !== '' ? $kit->paragraph($content->extraText('address')) : null,
        ], label: $content->heading)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function cta(SectionContent $content, SectionKit $kit): array
    {
        return [$kit->section([
            $kit->heading($content->heading),
            $content->text !== '' ? $kit->paragraph($content->text) : null,
            $kit->button($content->button),
        ], [
            'color' => ['background' => SectionKit::COLOR_SURFACE],
            'border' => ['radius' => SectionKit::RADIUS],
            'layout' => ['align' => 'start'],
            'spacing' => ['padding' => ['left' => SectionKit::SPACE_LARGE, 'right' => SectionKit::SPACE_LARGE]],
        ], $content->heading)];
    }

    /** @param array<string, mixed> $item */
    protected function itemText(array $item, string $key): string
    {
        return is_string($item[$key] ?? null) ? trim($item[$key]) : '';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function itemList(array $item, string $key): array
    {
        return array_values(array_filter(is_array($item[$key] ?? null) ? $item[$key] : [], 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    protected function itemImage(array $item): ?array
    {
        return is_array($item['image'] ?? null) ? $item['image'] : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{label: string, url: string}|null
     */
    protected function itemButton(array $item): ?array
    {
        $button = $item['button'] ?? null;

        return is_array($button) && is_string($button['label'] ?? null) && is_string($button['url'] ?? null)
            ? ['label' => $button['label'], 'url' => $button['url']]
            : null;
    }
}
