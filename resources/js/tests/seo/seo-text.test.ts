import { describe, expect, it } from 'vitest';
import {
    breadcrumbUrl,
    formatTitle,
    slugOf,
    truncate,
} from '@/components/verso/seo/seo-text';
import type { SiteInfo } from '@/components/verso/seo/seo-text';

const site: SiteInfo = {
    name: 'Panadería del Puerto',
    tagline: 'Pan de masa madre desde 1987',
    url: 'https://panaderia.example.es',
    host: 'panaderia.example.es',
    locale: 'es',
    titleFormat: '{title}{sep}{site}',
    separator: '·',
    defaultDescription: null,
    defaultImage: null,
    twitterSite: '',
};

describe('formatTitle', () => {
    it('follows the title format', () => {
        expect(formatTitle('Cómo hacer pan de centeno', site)).toBe(
            'Cómo hacer pan de centeno · Panadería del Puerto',
        );
        expect(
            formatTitle('Horario', {
                ...site,
                titleFormat: '{site}{sep}{title}{sep}{tagline}',
            }),
        ).toBe('Panadería del Puerto · Horario · Pan de masa madre desde 1987');
    });

    it('drops a separator left by an empty part', () => {
        expect(
            formatTitle('Contacto', {
                ...site,
                titleFormat: '{title}{sep}{tagline}',
                tagline: '',
            }),
        ).toBe('Contacto');
        expect(formatTitle('', { ...site, titleFormat: '{title}' })).toBe('');
    });
});

describe('text helpers', () => {
    it('cuts long text at a word', () => {
        expect(
            truncate('Riding Lisbon tram 28 from Martim Moniz to Prazeres', 30),
        ).toBe('Riding Lisbon tram 28 from…');
        expect(truncate('  Short   text ', 30)).toBe('Short text');
    });

    it('shows addresses like Google', () => {
        expect(
            breadcrumbUrl(
                'https://panaderia.example.es/recetas/pan-de-ma%C3%ADz',
            ),
        ).toBe('panaderia.example.es › recetas › pan-de-maíz');
        expect(breadcrumbUrl('https://…')).toBe('https://…');
    });

    it('reads the slug of an address', () => {
        expect(
            slugOf('https://panaderia.example.es/recetas/pan-de-centeno/'),
        ).toBe('pan-de-centeno');
        expect(slugOf(null)).toBe('');
    });
});
