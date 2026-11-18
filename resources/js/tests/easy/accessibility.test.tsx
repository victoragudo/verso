import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import {
    afterEach,
    beforeAll,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';
import { ConfirmDialog } from '@/components/verso/easy/confirm-dialog';
import { DraftBanner } from '@/components/verso/easy/draft-banner';
import { HelpPanel } from '@/components/verso/easy/help-panel';
import { LaunchChecklistCard } from '@/components/verso/easy/launch-checklist-card';
import { SectionGallery } from '@/components/verso/easy/section-gallery';
import { SimpleModeSwitch } from '@/components/verso/easy/simple-mode-switch';
import { attachBridge, CORE_SECTION_ACTIONS } from '@/lib/easy/frame-bridge';
import { clearHelpTopics } from '@/lib/easy/help-topics';
import SiteDetailsPage from '@/pages/admin/easy/details';
import PageEditor from '@/pages/admin/easy/page-editor';
import GuidedStart from '@/pages/admin/easy/start';
import MakeItYours from '@/pages/admin/easy/style';
import {
    HelperMenu,
    SetupHint,
} from '@/pages/admin/plugins/ai-assistant/page-helper/helper-menu';
import { pageHelper } from '@/pages/admin/plugins/ai-assistant/page-helper/store';
import { axeViolations } from '@/tests/axe';
import { resetPageProps, setPageProps } from '@/tests/mocks/inertia';
import { editorContext } from '../ai-assistant/page-helper-fixtures';
import { blog, restaurant, shop } from './fixtures';
import { helpTopics } from './help-fixtures';
import { bakeryBlocks, coreSections } from './page-fixtures';
import {
    boldPreset,
    calmPreset,
    classicPair,
    currentColors,
    modernPair,
    roles,
} from './style-fixtures';

let formErrors: Record<string, string> = {};

// A small useForm stand-in that keeps data in React state, with the errors of the test.
function useForm<T extends Record<string, unknown>>(initial: T) {
    const [data, setDataState] = useState(initial);

    return {
        data,
        errors: formErrors,
        processing: false,
        isDirty: false,
        setData: (key: keyof T, value: unknown) =>
            setDataState((current) => ({ ...current, [key]: value })),
        transform: () => {},
        put: () => {},
    };
}

vi.mock('@inertiajs/react', async () => ({
    ...(await import('@/tests/mocks/inertia')).inertiaMock,
    useForm,
}));

vi.mock('@/components/verso/easy/editor-frame', () => ({
    EditorFrame: ({ title }: { title: string }) => (
        <iframe title={title} data-test="page-frame" />
    ),
}));

// jsdom has no modal dialogs, so showModal and close only toggle the open attribute.
beforeAll(() => {
    HTMLDialogElement.prototype.showModal = function showModal(
        this: HTMLDialogElement,
    ) {
        this.setAttribute('open', '');
    };
    HTMLDialogElement.prototype.close = function close(
        this: HTMLDialogElement,
    ) {
        this.removeAttribute('open');
        this.dispatchEvent(new Event('close'));
    };
});

beforeEach(() => {
    document.documentElement.lang = 'en';
    document.title = 'Make it yours';
    setPageProps({
        errors: {},
        routeName: 'admin.easy.style',
        simpleMode: true,
    });
    window.localStorage.clear();
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string) =>
            String(url).includes('help')
                ? new Response(JSON.stringify({ topics: helpTopics }))
                : new Response('<main><p>Harbor Lane Bakery</p></main>'),
        ),
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
    resetPageProps();
    clearHelpTopics();
    pageHelper.reset();
    formErrors = {};
});

const styleProps = {
    theme: { name: 'Ashgrove', slug: 'ashgrove' },
    roles,
    colors: currentColors,
    presets: [calmPreset, boldPreset],
    fontPairs: [classicPair, modernPair],
    fontFaces: [],
    preset: 'calm',
    fontPair: 'classic',
    logo: null,
    logoSupported: true,
    canUpload: true,
    previewUrl: '/admin/site-editor/preview',
    previewWidths: { desktop: null, tablet: 768, phone: 390 },
    previewDelay: 0,
    palette: { sampleSize: 96, maxColors: 6 },
};

const editorProps = {
    entry: {
        id: 12,
        title: 'Harbor Lane Bakery',
        type: 'page',
        typeLabel: 'Page',
        status: 'draft',
        url: null,
        backUrl: '/admin/entries/page',
        advancedUrl: '/admin/entries/page/12/edit',
        canPublish: true,
    },
    blocks: bakeryBlocks(),
    sections: coreSections,
    previewWidths: { desktop: null, tablet: 768, phone: 390 },
    renderDelay: 0,
    undoSteps: 50,
    undoSeconds: 8,
    autosaveDelay: 60_000,
};

describe('Easy mode screens pass axe (WCAG 2.2 AA)', () => {
    it('guided start, with an error and the confirm dialog open', async () => {
        setPageProps({
            errors: { starter: 'Pick one kind of site first.' },
        });
        render(
            <GuidedStart
                starters={[blog, restaurant, shop]}
                current="restaurant"
                hasPages
            />,
        );
        await userEvent.click(screen.getByRole('radio', { name: 'Blog' }));
        await userEvent.click(
            screen.getByRole('button', { name: 'Build my site' }),
        );

        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(await axeViolations()).toEqual([]);
    });

    it('make it yours, with contrast warnings and a bad color code', async () => {
        setPageProps({
            errors: { 'colors.text': 'Use a color code like #2e5e4e.' },
        });
        render(
            <MakeItYours
                {...styleProps}
                colors={{ ...currentColors, text: '#d4d4d4' }}
            />,
        );
        await userEvent.type(
            screen.getByRole('textbox', { name: 'Accent' }),
            'zz',
        );

        expect(
            document.querySelector('[data-test="contrast-warnings"]'),
        ).toHaveTextContent('hard to read');
        expect(await axeViolations()).toEqual([]);
    });

    it('site details, with field errors', async () => {
        formErrors = {
            email: 'Enter an email address like hello@harborlane.example.',
        };
        render(
            <SiteDetailsPage
                details={{
                    email: 'hello@',
                    phone: '+44 20 7946 0958',
                    address: '12 Harbor Lane, Bristol BS1 4RN',
                    hours: 'Monday to Saturday, 7:00 to 15:00',
                    tagline: 'Sourdough worth the walk',
                    description: 'Family bakery by the harbor.',
                    social: ['https://instagram.com/harborlanebakery'],
                }}
                limits={{
                    phone: 40,
                    address: 300,
                    hours: 120,
                    description: 160,
                    social: 10,
                }}
            />,
        );

        expect(screen.getByLabelText('Email address')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        expect(screen.getByLabelText('Address')).toHaveAccessibleDescription(
            'The street, number, postcode and town, like on a letter.',
        );
        expect(await axeViolations()).toEqual([]);
    });

    it('dashboard launch checklist', async () => {
        render(
            <LaunchChecklistCard
                data={{
                    items: [
                        {
                            key: 'colors',
                            label: 'Choose your colors',
                            description:
                                'Pick colors and fonts that fit your brand.',
                            href: '/admin/easy/style',
                            action: 'Do it',
                            method: 'get',
                            done: true,
                        },
                        {
                            key: 'readable-colors',
                            label: 'Make your colors easy to read',
                            description:
                                'Text, links and buttons need to stand out from the background, so everyone can read them.',
                            href: '/admin/easy/style#colors',
                            action: 'Do it',
                            method: 'get',
                            done: false,
                        },
                        {
                            key: 'go-live',
                            label: 'Go live',
                            description:
                                'Let search engines like Google find your site.',
                            href: '/admin/easy/launch/go-live',
                            action: 'Go live',
                            method: 'post',
                            done: false,
                        },
                    ],
                    done: 1,
                    total: 3,
                    dismissUrl: '/admin/easy/launch/dismiss',
                }}
            />,
        );

        expect(
            screen.getByText('Make your colors easy to read'),
        ).toHaveTextContent('(not done yet)');
        expect(await axeViolations()).toEqual([]);
    });

    it('help panel with the simple mode switch', async () => {
        render(<HelpPanel open onOpenChange={() => {}} />);
        await screen.findByRole('list', { name: 'Help topics' });

        expect(await axeViolations()).toEqual([]);
    });

    it('simple mode switch on its own', async () => {
        render(<SimpleModeSwitch enabled />);

        expect(
            screen.getByRole('switch', { name: 'Simple mode' }),
        ).toBeChecked();
        expect(await axeViolations()).toEqual([]);
    });

    it('page editor with the draft banner', async () => {
        render(
            <>
                <PageEditor {...editorProps} />
                <DraftBanner
                    savedAt="2026-11-17T09:30:00+00:00"
                    onKeep={() => {}}
                    onDiscard={() => {}}
                />
            </>,
        );
        await screen.findByTitle(/Your page “Harbor Lane Bakery”/);

        expect(await axeViolations()).toEqual([]);
    });

    it('section gallery and confirm dialogs', async () => {
        render(
            <>
                <SectionGallery
                    open
                    onOpenChange={() => {}}
                    sections={coreSections}
                    busyKey={null}
                    error="This section could not be added. Try again."
                    onPick={() => {}}
                />
                <ConfirmDialog
                    open
                    title="Start over?"
                    confirmLabel="Start over"
                    danger
                    onConfirm={() => {}}
                    onClose={() => {}}
                >
                    This puts back the last saved version of the page.
                </ConfirmDialog>
            </>,
        );

        expect(await axeViolations()).toEqual([]);
    });

    it('AI helper menu and setup dialogs', async () => {
        const { unmount } = render(
            <HelperMenu context={editorContext()} onClose={() => {}} />,
        );
        await screen.findByRole('dialog', { name: 'AI helper' });
        expect(await axeViolations()).toEqual([]);
        unmount();

        render(
            <SetupHint
                settingsUrl="/admin/plugins/ai-assistant/settings"
                onClose={() => {}}
            />,
        );
        await screen.findByRole('dialog', { name: 'Set up the AI helper' });
        expect(await axeViolations()).toEqual([]);
    });

    it('the page in the editor frame, with toolbars, badges and add buttons', async () => {
        cleanup();
        document.body.innerHTML = `
<header><a href="/">Harbor Lane Bakery</a></header>
<main>
  <section data-verso-block="b_hero0001" data-verso-type="section">
    <h2 data-verso-block="b_title001" data-verso-type="heading">Fresh bread on Harbour Lane</h2>
    <p data-verso-block="b_text0001" data-verso-type="paragraph">Baked at five, gone by noon.</p>
  </section>
  <div data-verso-block="b_serv0001" data-verso-type="section">
    <figure data-verso-block="b_image001" data-verso-type="image"><img src="/storage/loaves.jpg" alt="Loaves"></figure>
  </div>
  <hr data-verso-block="b_line0001" data-verso-type="separator">
</main>`;
        const bridge = attachBridge(document, {
            sections: [
                {
                    id: 'b_hero0001',
                    name: 'Section 1: Fresh bread',
                    hidden: false,
                },
                {
                    id: 'b_serv0001',
                    name: 'Section 2: What we bake',
                    hidden: true,
                    warning:
                        'Some text in this section is hard to read on its background.',
                },
                { id: 'b_line0001', name: 'Section 3', hidden: false },
            ],
            actions: CORE_SECTION_ACTIONS,
            onText: () => {},
            onImage: () => {},
            onSectionAction: () => {},
            onAddSection: () => {},
        });

        expect(
            document.querySelector('[data-verso-section="b_serv0001"]'),
        ).toHaveAttribute('role', 'group');
        expect(await axeViolations()).toEqual([]);
        bridge.detach();
        document.body.innerHTML = '';
    });
});
