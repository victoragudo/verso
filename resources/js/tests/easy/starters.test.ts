import { describe, expect, it } from 'vitest';
import {
    pageTitles,
    pluginsLine,
    postsLine,
    previewShape,
} from '@/lib/easy/starters';
import { blog, restaurant, shop } from './fixtures';

describe('starter helpers', () => {
    it('lists page titles with the home page first and leaves posts out', () => {
        expect(pageTitles(restaurant)).toEqual(['Home', 'Menu', 'Visit us']);
        expect(
            pageTitles({
                ...restaurant,
                pages: [...restaurant.pages].reverse(),
            }),
        ).toEqual(['Home', 'Visit us', 'Menu']);
        expect(pageTitles(blog)).toEqual(['About', 'Contact']);
    });

    it('counts sample posts in plain words', () => {
        expect(postsLine(blog)).toBe('2 sample posts');
        expect(postsLine({ ...blog, pages: blog.pages.slice(0, 3) })).toBe(
            '1 sample post',
        );
        expect(postsLine(restaurant)).toBeNull();
    });

    it('names the plugins a starter turns on', () => {
        expect(pluginsLine(restaurant)).toBe('Turns on: Contact forms');
        expect(pluginsLine(shop)).toBe(
            'Turns on: Online shop, Contact forms, and loyalty-cards',
        );
        expect(pluginsLine({ ...restaurant, plugins: [] })).toBeNull();
    });

    it('draws known sections with their own shape and others as a strip', () => {
        expect(previewShape('hero')).toBe('banner');
        expect(previewShape('services')).toBe('cards');
        expect(previewShape('shop-products')).toBe('grid');
        expect(previewShape('map')).toBe('map');
        expect(previewShape('reviews-carousel')).toBe('strip');
    });
});
