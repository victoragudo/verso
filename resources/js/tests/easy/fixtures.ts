import type { StarterSummary } from '@/lib/easy/starters';

// Starter sites like the server sends them to the guided start.
export const restaurant: StarterSummary = {
    key: 'restaurant',
    label: 'Restaurant or cafe',
    description: 'Your menu, opening hours, a map and a way to book a table.',
    icon: 'restaurant',
    stylePreset: 'playful',
    plugins: ['forms'],
    pages: [
        { title: 'Home', slug: 'home', type: 'page', front: true },
        { title: 'Menu', slug: 'menu', type: 'page', front: false },
        { title: 'Visit us', slug: 'visit-us', type: 'page', front: false },
    ],
    sections: ['hero', 'about', 'gallery', 'testimonials', 'cta'],
};

export const blog: StarterSummary = {
    key: 'blog',
    label: 'Blog',
    description: 'Write about what you love.',
    icon: 'posts',
    stylePreset: 'calm',
    plugins: ['newsletter'],
    pages: [
        { title: 'About', slug: 'about', type: 'page', front: false },
        { title: 'Contact', slug: 'contact', type: 'page', front: false },
        {
            title: 'Three days in Lisbon by tram',
            slug: 'lisbon',
            type: 'post',
            front: false,
        },
        {
            title: 'A Saturday at the market',
            slug: 'market',
            type: 'post',
            front: false,
        },
    ],
    sections: ['about', 'newsletter'],
};

export const shop: StarterSummary = {
    key: 'shop',
    label: 'Online shop',
    description: 'Sell your products.',
    icon: 'shop',
    stylePreset: 'bold',
    plugins: ['shop', 'forms', 'loyalty-cards'],
    pages: [{ title: 'Home', slug: 'home', type: 'page', front: true }],
    sections: ['hero', 'shop-products', 'reviews-carousel'],
};
