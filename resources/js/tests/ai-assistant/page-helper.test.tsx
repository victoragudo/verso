import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { SlotItem } from '@/components/verso/slots';
import { setMessages } from '@/lib/i18n';
import { PageHelperHost } from '@/pages/admin/plugins/ai-assistant/page-helper/page-helper-host';
import { pageHelper } from '@/pages/admin/plugins/ai-assistant/page-helper/store';
import type {
    PageHelperProps,
    PageHelperRequest,
} from '@/pages/admin/plugins/ai-assistant/page-helper/types';
import type { Block } from '@/types';
import {
    bakeryPage,
    editorContext,
    gallerySection,
    helperProps,
    heroSection,
    jsonResponse,
} from './page-helper-fixtures';
import { pickOption } from '@/tests/pick-option';

const fetchMock = vi.fn();

const item: SlotItem = {
    key: 'ai-helper',
    component: 'ai-assistant.page-helper',
    label: null,
    order: 100,
    props: {},
};

function renderHost(props: PageHelperProps = helperProps()) {
    return render(<PageHelperHost item={item} props={props} context={{}} />);
}

function open(request: PageHelperRequest) {
    act(() => pageHelper.open(request));
}

// The written hero section the server sends back: same ids, new texts.
function writtenHero(): Block {
    const hero = heroSection();

    return {
        ...hero,
        children: [
            { ...hero.children![0], content: 'Sourdough worth the walk' },
            {
                ...hero.children![1],
                content:
                    '<p>We bake <strong>every morning at five</strong>.</p>',
            },
            {
                ...hero.children![2],
                attrs: { ...hero.children![2].attrs, label: 'See the bread' },
            },
            hero.children![3],
        ],
    };
}

function sentBody(call: number): Record<string, unknown> {
    return JSON.parse(String(fetchMock.mock.calls[call][1].body));
}

beforeEach(() => {
    pageHelper.reset();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    vi.unstubAllGlobals();
    pageHelper.reset();
});

describe('readiness', () => {
    it('shares the server props with the tools and clears them on leave', () => {
        const { unmount } = renderHost();

        expect(pageHelper.get().config?.ready).toBe(true);

        unmount();

        expect(pageHelper.get().config).toBeNull();
    });

    it('shows nothing until a tool opens a dialog', () => {
        renderHost();

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('tells admins where to add the key', () => {
        renderHost(
            helperProps({ ready: false, settingsUrl: '/admin/ai-assistant' }),
        );
        open({ kind: 'setup', context: editorContext() });

        const dialog = screen.getByRole('dialog', {
            name: 'Set up the AI helper',
        });

        expect(
            within(dialog).getByRole('link', {
                name: 'Open AI Assistant settings',
            }),
        ).toHaveAttribute('href', '/admin/ai-assistant');
    });

    it('shows the helper in the admin language', () => {
        setMessages('es', {
            'ai-assistant::AI helper': 'Asistente de IA',
            'ai-assistant::Translate this page': 'Traducir esta página',
            Close: 'Cerrar',
        });
        renderHost();
        open({ kind: 'menu', context: editorContext() });

        const dialog = screen.getByRole('dialog', { name: 'Asistente de IA' });

        expect(
            within(dialog).getByRole('button', {
                name: /Traducir esta página/,
            }),
        ).toBeInTheDocument();
        expect(
            within(dialog).getAllByRole('button', { name: 'Cerrar' }).length,
        ).toBeGreaterThan(0);
    });
});

describe('write this section', () => {
    it('shows the new texts and changes nothing until Accept', async () => {
        const user = userEvent.setup();
        const context = editorContext();
        fetchMock.mockResolvedValueOnce(
            jsonResponse({
                result: { section: writtenHero() },
                model: 'claude-sonnet-5-5',
            }),
        );
        renderHost();
        open({ kind: 'section-text', context, section: heroSection() });

        const dialog = screen.getByRole('dialog', {
            name: 'Write this section with AI',
        });
        expect(
            within(dialog).getByLabelText('What does your business do?'),
        ).toHaveValue(
            'Family bakery in Harbor Lane. Sourdough and coffee from 7am.',
        );

        await user.click(
            within(dialog).getByRole('button', { name: 'Write the texts' }),
        );

        const changes = await within(dialog).findByRole('list', {
            name: 'Changes',
        });
        expect(within(changes).getAllByRole('listitem')).toHaveLength(3);
        expect(changes).toHaveTextContent('Welcome to our shop');
        expect(changes).toHaveTextContent('Sourdough worth the walk');
        expect(changes).toHaveTextContent('See the bread');
        expect(within(dialog).getByRole('status')).toHaveTextContent(
            '3 new texts are ready',
        );
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe(
            '/admin/ai-assistant/actions/section-text',
        );
        expect(sentBody(0)).toEqual({
            section: heroSection(),
            business:
                'Family bakery in Harbor Lane. Sourdough and coffee from 7am.',
        });
        expect(context.change).not.toHaveBeenCalled();

        await user.click(
            within(dialog).getByRole('button', { name: 'Accept new texts' }),
        );

        expect(context.change).toHaveBeenCalledTimes(1);
        const [blocks, message] = context.change.mock.calls[0];
        expect(blocks[0]).toEqual(writtenHero());
        expect(blocks[1]).toEqual(gallerySection());
        expect(message).toBe('New texts added to Section 1: Welcome');
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
    });

    it('does nothing on Discard', async () => {
        const user = userEvent.setup();
        const context = editorContext();
        fetchMock.mockResolvedValueOnce(
            jsonResponse({ result: { section: writtenHero() } }),
        );
        renderHost();
        open({ kind: 'section-text', context, section: heroSection() });

        await user.click(
            screen.getByRole('button', { name: 'Write the texts' }),
        );
        await screen.findByRole('list', { name: 'Changes' });
        await user.click(screen.getByRole('button', { name: 'Discard' }));

        expect(context.change).not.toHaveBeenCalled();
        expect(pageHelper.get().request).toBeNull();
    });

    it('saves a new business description first and keeps it for later', async () => {
        const user = userEvent.setup();
        const business = 'Seaside bakery with a wood oven and a small cafe.';
        fetchMock
            .mockResolvedValueOnce(jsonResponse({ result: { business } }))
            .mockResolvedValueOnce(
                jsonResponse({ result: { section: writtenHero() } }),
            );
        renderHost();
        open({
            kind: 'section-text',
            context: editorContext(),
            section: heroSection(),
        });

        const field = screen.getByLabelText('What does your business do?');
        await user.clear(field);
        await user.type(field, business);
        await user.click(
            screen.getByRole('button', { name: 'Write the texts' }),
        );
        await screen.findByRole('list', { name: 'Changes' });

        expect(fetchMock.mock.calls[0][0]).toBe('/admin/ai-assistant/business');
        expect(fetchMock.mock.calls[0][1].method).toBe('PUT');
        expect(sentBody(0)).toEqual({ business });
        expect(sentBody(1).business).toBe(business);
        expect(pageHelper.get().config?.business).toBe(business);
    });

    it('needs a business description', () => {
        renderHost(helperProps({ business: '' }));
        open({
            kind: 'section-text',
            context: editorContext(),
            section: heroSection(),
        });

        expect(
            screen.getByRole('button', { name: 'Write the texts' }),
        ).toBeDisabled();
    });

    it('shows a busy bar while the helper writes', async () => {
        const user = userEvent.setup();
        fetchMock.mockReturnValueOnce(new Promise(() => {}));
        renderHost();
        open({
            kind: 'section-text',
            context: editorContext(),
            section: heroSection(),
        });

        await user.click(
            screen.getByRole('button', { name: 'Write the texts' }),
        );

        expect(
            screen.getByRole('progressbar', { name: 'Writing new texts…' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Write the texts' }),
        ).toBeDisabled();
    });

    it('explains a failure in plain words', async () => {
        const user = userEvent.setup();
        const context = editorContext();
        fetchMock.mockResolvedValueOnce(
            jsonResponse(
                { message: 'The provider rejected the API key.' },
                502,
            ),
        );
        renderHost();
        open({ kind: 'section-text', context, section: heroSection() });

        await user.click(
            screen.getByRole('button', { name: 'Write the texts' }),
        );

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'The AI helper is busy. Try again in a minute.',
        );
        expect(context.change).not.toHaveBeenCalled();
    });

    it('keeps the rate limit message of the server', async () => {
        const user = userEvent.setup();
        fetchMock.mockResolvedValueOnce(
            jsonResponse(
                {
                    message:
                        'You have used all your AI requests for now. Try again in 12 min.',
                },
                429,
            ),
        );
        renderHost();
        open({
            kind: 'section-text',
            context: editorContext(),
            section: heroSection(),
        });

        await user.click(
            screen.getByRole('button', { name: 'Write the texts' }),
        );

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Try again in 12 min.',
        );
    });
});

describe('headlines', () => {
    it('lets people pick one headline and applies it as one change', async () => {
        const user = userEvent.setup();
        const context = editorContext();
        fetchMock.mockResolvedValueOnce(
            jsonResponse({
                result: {
                    headlines: [
                        'Sourdough worth the walk',
                        'Bread & coffee by the harbor',
                        'Your morning bakery on Harbor Lane',
                    ],
                },
            }),
        );
        renderHost();
        open({ kind: 'headlines', context, section: heroSection() });

        await user.click(
            screen.getByRole('button', { name: 'Suggest headlines' }),
        );
        const ideas = await screen.findByRole('group', {
            name: 'Headline ideas',
        });

        expect(within(ideas).getAllByRole('radio')).toHaveLength(3);
        expect(
            within(ideas).getByRole('radio', {
                name: 'Sourdough worth the walk',
            }),
        ).toBeChecked();
        expect(sentBody(0)).toMatchObject({
            headline: 'Welcome to our shop',
            context: 'Welcome to our shop\nWe sell good things .',
        });

        await user.click(
            within(ideas).getByRole('radio', {
                name: 'Bread & coffee by the harbor',
            }),
        );
        expect(screen.getByRole('list', { name: 'Changes' })).toHaveTextContent(
            'Bread & coffee by the harbor',
        );
        await user.click(
            screen.getByRole('button', { name: 'Use this headline' }),
        );

        expect(context.change).toHaveBeenCalledTimes(1);
        const [blocks, message] = context.change.mock.calls[0];
        expect(blocks[0].children[0].content).toBe(
            'Bread &amp; coffee by the harbor',
        );
        expect(message).toBe(
            'Headline changed to Bread & coffee by the harbor',
        );
    });
});

describe('describe images', () => {
    it('describes each picture and applies only the kept ones', async () => {
        const user = userEvent.setup();
        const context = editorContext();
        fetchMock
            .mockResolvedValueOnce(
                jsonResponse({ result: { alt: 'Cinnamon buns on a tray' } }),
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    result: { alt: 'A flat white on the counter' },
                }),
            );
        renderHost();
        open({ kind: 'images', context, section: gallerySection() });

        await user.click(
            screen.getByRole('button', { name: 'Describe 2 pictures' }),
        );
        const list = await screen.findByRole('list', { name: 'Descriptions' });
        await waitFor(() =>
            expect(within(list).getAllByRole('listitem')).toHaveLength(2),
        );

        expect(sentBody(0)).toEqual({ media_id: 11 });
        expect(sentBody(1)).toEqual({ media_id: 13 });
        expect(screen.getByLabelText('Description of picture 2')).toHaveValue(
            'A flat white on the counter',
        );

        await user.click(within(list).getAllByRole('checkbox')[0]);
        await user.click(
            screen.getByRole('button', { name: 'Use 1 description' }),
        );

        expect(context.change).toHaveBeenCalledTimes(1);
        const [blocks, message] = context.change.mock.calls[0];
        expect(
            blocks[1].children[0].attrs.images.map(
                (image: { alt?: string }) => image.alt,
            ),
        ).toEqual([
            '',
            'The stone oven at dawn',
            'A flat white on the counter',
        ]);
        expect(message).toBe('1 picture description added');
    });
});

describe('translate the page', () => {
    it('translates every text to the chosen language as a preview', async () => {
        const user = userEvent.setup();
        const context = editorContext();
        const page = bakeryPage();
        const translated = [
            {
                ...page[0],
                children: [
                    { ...page[0].children![0], content: 'Bienvenue chez nous' },
                    ...page[0].children!.slice(1),
                ],
            },
            page[1],
        ];
        fetchMock.mockResolvedValueOnce(
            jsonResponse({
                result: {
                    title: 'Boulangerie Harbor Lane',
                    blocks: translated,
                    locale: 'fr',
                },
            }),
        );
        renderHost();
        open({ kind: 'menu', context });

        await user.click(
            screen.getByRole('button', { name: /Translate this page/ }),
        );
        await pickOption('Language', 'French');
        await user.click(
            screen.getByRole('button', { name: 'Translate the page' }),
        );

        expect(
            await screen.findByRole('list', { name: 'Changes' }),
        ).toHaveTextContent('Bienvenue chez nous');
        expect(sentBody(0)).toMatchObject({
            title: 'Harbor Lane Bakery',
            locale: 'fr',
        });

        await user.click(
            screen.getByRole('button', { name: 'Use the French texts' }),
        );

        expect(context.change).toHaveBeenCalledWith(
            translated,
            'Page translated to French',
        );
    });

    it('offers headlines for the top of the page from the menu', async () => {
        const user = userEvent.setup();
        renderHost();
        open({ kind: 'menu', context: editorContext() });

        await user.click(
            screen.getByRole('button', {
                name: /New headline for the top of the page/,
            }),
        );

        expect(
            screen.getByRole('dialog', { name: 'Suggest headlines' }),
        ).toBeInTheDocument();
        const request = pageHelper.get().request;
        expect(request && 'section' in request && request.section.id).toBe(
            'b_hero0001',
        );
    });
});
