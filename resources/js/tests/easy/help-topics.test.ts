import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    clearHelpTopics,
    isTopicForRoute,
    loadHelpTopics,
    orderTopics,
    resultsMessage,
    searchTopics,
} from '@/lib/easy/help-topics';
import { helpTopics } from './help-fixtures';

afterEach(() => {
    clearHelpTopics();
    vi.unstubAllGlobals();
});

describe('orderTopics', () => {
    it('puts the topics for the current page first and keeps the rest in order', () => {
        const ordered = orderTopics(helpTopics, 'admin.site.menus.index');

        expect(ordered.map((topic) => topic.key)).toEqual([
            'change-menu',
            'choose-starter',
            'edit-page',
            'write-post',
            'upload-photos',
        ]);
    });

    it('keeps several topics of one page in their own order', () => {
        expect(
            orderTopics(helpTopics, 'admin.entries.index').map(
                (topic) => topic.key,
            ),
        ).toEqual([
            'edit-page',
            'write-post',
            'choose-starter',
            'change-menu',
            'upload-photos',
        ]);
    });

    it('keeps the order on pages without topics', () => {
        expect(orderTopics(helpTopics, 'admin.users.index')).toEqual(
            helpTopics,
        );
        expect(orderTopics(helpTopics, null)).toEqual(helpTopics);
    });

    it('tells when a topic belongs to the page', () => {
        expect(isTopicForRoute(helpTopics[1], 'admin.easy.pages.edit')).toBe(
            true,
        );
        expect(isTopicForRoute(helpTopics[1], null)).toBe(false);
    });
});

describe('searchTopics', () => {
    it('finds topics by title, steps and keywords, without caring about accents or case', () => {
        expect(
            searchTopics(helpTopics, 'NAVIGATION').map((t) => t.key),
        ).toEqual(['change-menu']);
        expect(searchTopics(helpTopics, 'plantílla').map((t) => t.key)).toEqual(
            ['choose-starter'],
        );
        expect(searchTopics(helpTopics, 'menú').map((t) => t.key)).toEqual([
            'choose-starter',
            'change-menu',
        ]);
        expect(
            searchTopics(helpTopics, 'replace image').map((t) => t.key),
        ).toEqual(['edit-page']);
        expect(searchTopics(helpTopics, 'fotos').map((t) => t.key)).toEqual([
            'upload-photos',
        ]);
    });

    it('needs every word and gives all topics for an empty search', () => {
        expect(searchTopics(helpTopics, 'menu photos')).toEqual([]);
        expect(searchTopics(helpTopics, '   ')).toEqual(helpTopics);
    });

    it('says how many topics were found', () => {
        expect(resultsMessage(2, 'page')).toBe('2 topics found.');
        expect(resultsMessage(1, 'menu')).toBe('1 topic found.');
        expect(resultsMessage(0, ' bakery ')).toBe(
            'No help found for “bakery”.',
        );
        expect(resultsMessage(5, '')).toBe('');
    });
});

describe('loadHelpTopics', () => {
    it('loads the topics once', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(
                new Response(JSON.stringify({ topics: helpTopics })),
            );
        vi.stubGlobal('fetch', fetchMock);

        await expect(loadHelpTopics()).resolves.toEqual(helpTopics);
        await loadHelpTopics();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe('/admin/easy/help');
    });

    it('tries again after a failed load', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(new Response('', { status: 500 }))
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ topics: helpTopics })),
            );
        vi.stubGlobal('fetch', fetchMock);

        await expect(loadHelpTopics()).rejects.toThrow(
            'Help could not be loaded.',
        );
        await expect(loadHelpTopics()).resolves.toHaveLength(5);
    });
});
