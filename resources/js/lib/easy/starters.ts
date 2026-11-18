import { formatList, msg, t } from '@/lib/i18n';

// Shapes and plain words for the guided start ("What is your site for?").

export type StarterPage = {
    title: string;
    slug: string;
    type: string;
    front: boolean;
};

export type StarterSummary = {
    key: string;
    label: string;
    description: string;
    icon: string;
    stylePreset: string;
    plugins: string[];
    pages: StarterPage[];
    sections: string[];
};

// How a section looks in the small preview drawing of a starter card.
export type PreviewShape =
    | 'banner'
    | 'split'
    | 'cards'
    | 'grid'
    | 'quotes'
    | 'list'
    | 'form'
    | 'map'
    | 'strip';

const SHAPES: Record<string, PreviewShape> = {
    hero: 'banner',
    about: 'split',
    services: 'cards',
    pricing: 'cards',
    team: 'cards',
    'shop-products': 'grid',
    gallery: 'grid',
    testimonials: 'quotes',
    faq: 'list',
    contact: 'form',
    newsletter: 'form',
    map: 'map',
    cta: 'strip',
};

// Plain names of the plugins a starter turns on.
const PLUGIN_NAMES: Record<string, string> = {
    forms: msg('Contact forms'),
    shop: msg('Online shop'),
    newsletter: msg('Newsletter'),
    memberships: msg('Memberships'),
};

// The preview shape of a section. Sections from plugins that are not listed look like a plain strip.
export function previewShape(section: string): PreviewShape {
    return SHAPES[section] ?? 'strip';
}

// Titles of the pages a starter creates, home page first. Posts are counted apart.
export function pageTitles(starter: StarterSummary): string[] {
    const pages = starter.pages.filter((page) => page.type === 'page');

    return [
        ...pages.filter((page) => page.front),
        ...pages.filter((page) => !page.front),
    ].map((page) => page.title);
}

// "2 sample posts", or null when the starter has none.
export function postsLine(starter: StarterSummary): string | null {
    const posts = starter.pages.filter((page) => page.type === 'post').length;

    if (posts === 0) {
        return null;
    }

    return t('{count, plural, one {# sample post} other {# sample posts}}', {
        count: posts,
    });
}

// "Turns on: Online shop and Contact forms", or null when no plugin is needed.
export function pluginsLine(starter: StarterSummary): string | null {
    const names = starter.plugins.map((slug) =>
        PLUGIN_NAMES[slug] ? t(PLUGIN_NAMES[slug]) : slug,
    );

    if (names.length === 0) {
        return null;
    }

    return t('Turns on: {plugins}', { plugins: formatList(names) });
}
