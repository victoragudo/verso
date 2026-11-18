import { describe, expect, it } from 'vitest';
import { parseBlocks } from '@/lib/seo-analysis';
import type { Block } from '@/types';
import {
    button,
    code,
    columns,
    element,
    gallery,
    heading,
    html,
    image,
    list,
    paragraph,
    quote,
    section,
    table,
} from './fixtures/posts';

const HOST = 'harborlane.test';

describe('parseBlocks', () => {
    it('reads paragraphs, the first paragraph and the words', () => {
        const content = parseBlocks(
            [
                paragraph(
                    'Lisbon has seven hills. Tram 28 climbs most of them.',
                ),
                paragraph('Bring good shoes.'),
            ],
            HOST,
        );

        expect(content.paragraphs).toEqual([
            'Lisbon has seven hills. Tram 28 climbs most of them.',
            'Bring good shoes.',
        ]);
        expect(content.firstParagraph).toBe(
            'Lisbon has seven hills. Tram 28 climbs most of them.',
        );
        expect(content.words).toHaveLength(13);
        expect(content.sentences).toHaveLength(3);
    });

    it('reads headings with their level and counts their words', () => {
        const content = parseBlocks(
            [heading('Where to catch Tram 28'), heading('Tickets', 3)],
            HOST,
        );

        expect(content.headings).toEqual([
            { level: 2, text: 'Where to catch Tram 28' },
            { level: 3, text: 'Tickets' },
        ]);
        expect(content.words).toHaveLength(6);
        expect(content.sentences).toEqual([]);
    });

    it('treats each list item as its own paragraph', () => {
        const content = parseBlocks(
            [list(['Mezcla la harina con el agua.', 'Añade la sal.'], true)],
            HOST,
        );

        expect(content.paragraphs).toEqual([
            'Mezcla la harina con el agua.',
            'Añade la sal.',
        ]);
    });

    it('reads quotes as paragraphs without the citation', () => {
        const content = parseBlocks(
            [quote('The tram is the soul of Lisbon.', 'Fernando Pessoa')],
            HOST,
        );

        expect(content.paragraphs).toEqual(['The tram is the soul of Lisbon.']);
        expect(content.text).not.toContain('Pessoa');
    });

    it('skips the text of code and html blocks', () => {
        const content = parseBlocks(
            [
                code('echo "Lisbon tram schedule";'),
                html('<div class="map">Opening hours: daily</div>'),
            ],
            HOST,
        );

        expect(content.words).toEqual([]);
        expect(content.paragraphs).toEqual([]);
    });

    it('still reads links and images inside html blocks', () => {
        const content = parseBlocks(
            [
                html(
                    '<figure><img src="/img/graca.jpg" alt="View from Graça"><a href="https://www.visitlisboa.com/">Visit Lisboa</a></figure>',
                ),
            ],
            HOST,
        );

        expect(content.images).toEqual([
            { src: '/img/graca.jpg', alt: 'View from Graça' },
        ]);
        expect(content.links).toEqual([
            {
                href: 'https://www.visitlisboa.com/',
                text: 'Visit Lisboa',
                internal: false,
                nofollow: false,
            },
        ]);
    });

    it('reads image and gallery alt text and skips empty image blocks', () => {
        const content = parseBlocks(
            [
                image('/img/tram.jpg', 'Yellow tram on a hill'),
                image('', '', null),
                gallery([
                    { src: '/img/se.jpg', alt: 'Lisbon cathedral' },
                    { src: '/img/luzia.jpg', alt: '' },
                ]),
            ],
            HOST,
        );

        expect(content.images).toEqual([
            { src: '/img/tram.jpg', alt: 'Yellow tram on a hill' },
            { src: '/img/se.jpg', alt: 'Lisbon cathedral' },
            { src: '/img/luzia.jpg', alt: '' },
        ]);
        expect(content.words).toEqual([]);
    });

    it('reads button links but not their label as text', () => {
        const content = parseBlocks(
            [button('Book a walking tour', '/tours/alfama')],
            HOST,
        );

        expect(content.links).toEqual([
            {
                href: '/tours/alfama',
                text: 'Book a walking tour',
                internal: true,
                nofollow: false,
            },
        ]);
        expect(content.words).toEqual([]);
    });

    it('reads table cells as text and finds their links', () => {
        const content = parseBlocks(
            [
                table([
                    ['Stop', 'Sight'],
                    ['Graça', '<a href="/sights/graca">Miradouro da Graça</a>'],
                ]),
            ],
            HOST,
        );

        expect(content.words).toEqual([
            'Stop',
            'Sight',
            'Graça',
            'Miradouro',
            'da',
            'Graça',
        ]);
        expect(content.paragraphs).toEqual([]);
        expect(content.links[0].internal).toBe(true);
    });

    it('walks nested columns, sections and elements in order', () => {
        const blocks: Block[] = [
            section(
                heading('Plan your day'),
                columns(
                    [
                        paragraph('Morning: ride the tram.'),
                        image('/img/a.jpg', 'Tram stop'),
                    ],
                    [paragraph('Evening: eat grilled sardines.')],
                ),
                element('Open every day', paragraph('Closed on Christmas.')),
            ),
        ];
        const content = parseBlocks(blocks, HOST);

        expect(content.headings).toEqual([{ level: 2, text: 'Plan your day' }]);
        expect(content.paragraphs).toEqual([
            'Morning: ride the tram.',
            'Evening: eat grilled sardines.',
            'Closed on Christmas.',
        ]);
        expect(content.images).toHaveLength(1);
        expect(content.text).toContain('Open every day');
    });

    it('counts words between headings', () => {
        const content = parseBlocks(
            [
                paragraph('One two three.'),
                heading('Middle part'),
                paragraph('Four five.'),
                heading('Last part'),
            ],
            HOST,
        );

        expect(content.wordsBetweenHeadings).toEqual([3, 2, 0]);
    });

    it('returns empty content for no blocks', () => {
        const content = parseBlocks([], HOST);

        expect(content.text).toBe('');
        expect(content.firstParagraph).toBe('');
        expect(content.words).toEqual([]);
    });
});

describe('link classification', () => {
    const linksOf = (anchors: string, host = HOST) =>
        parseBlocks([paragraph(anchors)], host).links;

    it('treats relative links and links to the site host as internal', () => {
        const links = linksOf(
            '<a href="/blog">Blog</a> <a href="guides/porto">Porto</a> <a href="https://harborlane.test/about">About</a> <a href="https://www.harborlane.test/shop">Shop</a> <a href="//harborlane.test/map">Map</a>',
        );

        expect(links.map((link) => link.internal)).toEqual([
            true,
            true,
            true,
            true,
            true,
        ]);
    });

    it('treats other hosts and subdomains as outbound', () => {
        const links = linksOf(
            '<a href="https://www.carris.pt/">Carris</a> <a href="http://blog.harborlane.test/">Blog</a> <a href="//cdn.example.org/map.pdf">Map</a>',
        );

        expect(links.every((link) => !link.internal)).toBe(true);
    });

    it('ignores mailto, tel and anchor links', () => {
        expect(
            linksOf(
                '<a href="mailto:hola@harborlane.test">Mail</a> <a href="tel:+351210000000">Call</a> <a href="#tickets">Tickets</a> <a>Empty</a>',
            ),
        ).toEqual([]);
    });

    it('reads nofollow from rel and ignores the port of the site host', () => {
        const links = linksOf(
            '<a href="https://booking.example.com/" rel="nofollow sponsored">Hotels</a> <a href=\'http://harborlane.test:8000/tours\'>Tours</a>',
            'harborlane.test:8000',
        );

        expect(links[0]).toMatchObject({ nofollow: true, internal: false });
        expect(links[1]).toMatchObject({ nofollow: false, internal: true });
    });

    it('accepts a site host given with a scheme', () => {
        expect(
            linksOf(
                '<a href="https://harborlane.test/guides">Guides</a>',
                'https://harborlane.test/',
            )[0].internal,
        ).toBe(true);
    });
});
