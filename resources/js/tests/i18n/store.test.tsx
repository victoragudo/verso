import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    currentDir,
    currentLocale,
    formatBytes,
    formatCurrency,
    formatDateTime,
    formatList,
    formatNumber,
    formatPercent,
    formatRelativeTime,
    hasTranslation,
    loadLocale,
    msg,
    setMessages,
    t,
    Trans,
} from '@/lib/i18n';

afterEach(() => {
    document.documentElement.lang = '';
    document.documentElement.dir = '';
});

describe('t', () => {
    it('shows the English key when there is no translation', () => {
        expect(t('Publish now')).toBe('Publish now');
    });

    it('drops the plugin prefix of an untranslated key', () => {
        expect(t('shop::Orders')).toBe('Orders');
    });

    it('uses the loaded translation and fills values', () => {
        setMessages('es', {
            'Saved “{title}”.': 'Guardado «{title}».',
            'shop::Orders': 'Pedidos',
        });

        expect(t('Saved “{title}”.', { title: 'Carta de otoño' })).toBe(
            'Guardado «Carta de otoño».',
        );
        expect(t('shop::Orders')).toBe('Pedidos');
        expect(hasTranslation('shop::Orders')).toBe(true);
        expect(currentLocale()).toBe('es');
    });

    it('marks module constants with msg()', () => {
        const label = msg('Drafts');
        setMessages('de', { Drafts: 'Entwürfe' });

        expect(label).toBe('Drafts');
        expect(t(label)).toBe('Entwürfe');
    });
});

describe('loadLocale', () => {
    it('fetches the bundle and sets lang and dir on the page', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ Dashboard: 'لوحة التحكم' }),
        });
        vi.stubGlobal('fetch', fetchMock);

        await loadLocale('ar', '/i18n/ar/1a2b.json', 'rtl');

        expect(fetchMock).toHaveBeenCalledWith(
            '/i18n/ar/1a2b.json',
            expect.objectContaining({ credentials: 'same-origin' }),
        );
        expect(t('Dashboard')).toBe('لوحة التحكم');
        expect(currentDir()).toBe('rtl');
        expect(document.documentElement.lang).toBe('ar');
        expect(document.documentElement.dir).toBe('rtl');
    });

    it('falls back to English text when the request fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        await loadLocale('ja', '/i18n/ja/9f.json');

        expect(t('Dashboard')).toBe('Dashboard');
        expect(currentLocale()).toBe('ja');
    });
});

describe('Intl helpers', () => {
    it('format numbers, money and shares in the admin language', () => {
        setMessages('de', {});

        expect(formatNumber(1234.5)).toBe('1.234,5');
        expect(formatCurrency(19.9, 'EUR').replace(/\s/gu, ' ')).toBe(
            '19,90 €',
        );
        expect(formatPercent(0.25).replace(/\s/gu, ' ')).toBe('25 %');
    });

    it('format dates in the admin language', () => {
        setMessages('ja', {});

        expect(
            formatDateTime('2026-03-05T10:00:00Z', {
                dateStyle: 'long',
                timeZone: 'UTC',
            }),
        ).toBe('2026年3月5日');
    });

    it('format relative times', () => {
        setMessages('es', {});
        const now = new Date('2026-11-17T12:00:00Z');

        expect(formatRelativeTime('2026-11-17T11:57:00Z', now)).toBe(
            'hace 3 minutos',
        );
        expect(formatRelativeTime('2026-11-16T12:00:00Z', now)).toBe('ayer');
    });

    it('format lists and file sizes', () => {
        setMessages('fr', {});

        expect(formatList(['Posts', 'Pages', 'Media'])).toBe(
            'Posts, Pages et Media',
        );
        expect(formatBytes(1536)).toContain('1,5');
    });
});

describe('Trans', () => {
    it('puts elements into a translated text', () => {
        render(
            <p>
                <Trans
                    text="Read the <link>guide</link> before you start."
                    components={{
                        link: (children) => <a href="/guide">{children}</a>,
                    }}
                />
            </p>,
        );

        expect(screen.getByRole('link', { name: 'guide' })).toHaveAttribute(
            'href',
            '/guide',
        );
        expect(screen.getByText(/before you start/)).toBeInTheDocument();
    });
});
