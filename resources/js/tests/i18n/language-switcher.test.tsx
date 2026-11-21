import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { I18nRoot } from '@/components/verso/i18n/i18n-root';
import { LocaleBoundary } from '@/components/verso/i18n/locale-boundary';
import { LanguageMenu } from '@/components/verso/i18n/language-menu';
import { LanguageSelect } from '@/components/verso/i18n/language-select';
import { currentLocale, setMessages, t } from '@/lib/i18n';
import {
    fireRouterEvent,
    resetPageProps,
    router,
    setPageProps,
} from '@/tests/mocks/inertia';
import { pickOption } from '@/tests/pick-option';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const locales = [
    { code: 'en', name: 'English', englishName: 'English', dir: 'ltr' },
    { code: 'es', name: 'Español', englishName: 'Spanish', dir: 'ltr' },
    { code: 'ar', name: 'العربية', englishName: 'Arabic', dir: 'rtl' },
    { code: 'ja', name: '日本語', englishName: 'Japanese', dir: 'ltr' },
];

function i18nProps(overrides: Record<string, unknown> = {}) {
    return {
        locale: 'en',
        dir: 'ltr',
        bundle: null,
        fallback: 'en',
        siteLocale: 'es',
        userLocale: null,
        locales,
        ...overrides,
    };
}

beforeEach(() => {
    setPageProps({ i18n: i18nProps() });
});

afterEach(() => {
    resetPageProps();
    document.documentElement.lang = '';
    document.documentElement.dir = '';
});

describe('LanguageSelect', () => {
    it('lists every language in its own name and language', async () => {
        render(<LanguageSelect />);

        const select = screen.getByRole('combobox', { name: 'Language' });
        expect(select).toHaveTextContent('English');

        await userEvent.click(select);
        const arabic = await screen.findByRole('option', { name: 'العربية' });

        expect(arabic).toHaveAttribute('lang', 'ar');
        expect(screen.getAllByRole('option')).toHaveLength(4);
    });

    it('saves the picked language', async () => {
        render(<LanguageSelect />);

        await pickOption('Language', '日本語');

        expect(router.put).toHaveBeenCalledWith(
            '/locale',
            { locale: 'ja' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('offers the site language on the profile page', () => {
        render(<LanguageSelect withSiteChoice showLabel />);

        expect(
            screen.getByRole('combobox', { name: 'Language' }),
        ).toHaveTextContent('Same as the site (Español)');
    });

    it('shows nothing with only one language', () => {
        setPageProps({ i18n: i18nProps({ locales: [locales[0]] }) });
        const { container } = render(<LanguageSelect />);

        expect(container).toBeEmptyDOMElement();
    });
});

describe('LanguageMenu', () => {
    it('opens a sub menu with the languages and saves a choice', async () => {
        const user = userEvent.setup();
        setPageProps({ i18n: i18nProps({ userLocale: 'es', locale: 'es' }) });

        render(
            <DropdownMenu open>
                <DropdownMenuTrigger>Account</DropdownMenuTrigger>
                <DropdownMenuContent>
                    <LanguageMenu />
                </DropdownMenuContent>
            </DropdownMenu>,
        );

        screen.getByRole('menuitem', { name: /Language/ }).focus();
        await user.keyboard('{ArrowRight}');
        const japanese = await screen.findByRole('menuitemradio', {
            name: '日本語',
        });
        japanese.focus();
        await user.keyboard('{Enter}');

        expect(router.put).toHaveBeenCalledWith(
            '/locale',
            { locale: 'ja' },
            expect.anything(),
        );
    });
});

describe('I18nRoot', () => {
    function navigateTo(i18n: Record<string, unknown>) {
        act(() => {
            fireRouterEvent('navigate', { page: { props: { i18n } } });
        });
    }

    it('loads the strings of a new page language and renders again', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({ Dashboard: 'Panel' }),
            }),
        );
        setMessages('en', {});

        function Title() {
            return <h1>{t('Dashboard')}</h1>;
        }

        render(
            <I18nRoot>
                <LocaleBoundary>
                    <Title />
                </LocaleBoundary>
            </I18nRoot>,
        );

        expect(screen.getByRole('heading')).toHaveTextContent('Dashboard');

        navigateTo(i18nProps({ locale: 'es', bundle: '/i18n/es/abc.json' }));

        await waitFor(() =>
            expect(screen.getByRole('heading')).toHaveTextContent('Panel'),
        );
        expect(currentLocale()).toBe('es');
        expect(document.documentElement.lang).toBe('es');
    });

    it('sets the direction for right to left languages', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockResolvedValue({
                    ok: true,
                    json: () => Promise.resolve({}),
                }),
        );

        render(
            <I18nRoot>
                <p>Text</p>
            </I18nRoot>,
        );

        navigateTo(
            i18nProps({
                locale: 'ar',
                dir: 'rtl',
                bundle: '/i18n/ar/abc.json',
            }),
        );

        await waitFor(() => expect(document.documentElement.dir).toBe('rtl'));
    });

    it('goes back to English after a switch on the same page, without a reload', async () => {
        vi.stubGlobal('fetch', vi.fn());
        setMessages('es', { Dashboard: 'Panel' });

        function Title() {
            return <h1>{t('Dashboard')}</h1>;
        }

        render(
            <I18nRoot>
                <LocaleBoundary>
                    <Title />
                </LocaleBoundary>
            </I18nRoot>,
        );

        expect(screen.getByRole('heading')).toHaveTextContent('Panel');

        // The switch redirects back to the same URL: Inertia fires "success" but no "navigate".
        act(() => {
            fireRouterEvent('success', {
                page: { props: { i18n: i18nProps({ locale: 'en', bundle: null }) } },
            });
        });

        await waitFor(() =>
            expect(screen.getByRole('heading')).toHaveTextContent('Dashboard'),
        );
        expect(currentLocale()).toBe('en');
    });

    it('does nothing when the language stays the same', () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        render(
            <I18nRoot>
                <p>Text</p>
            </I18nRoot>,
        );

        navigateTo(i18nProps());

        expect(fetchMock).not.toHaveBeenCalled();
    });
});
