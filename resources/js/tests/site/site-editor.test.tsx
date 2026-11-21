import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SiteEditorEdit from '@/pages/admin/site-editor/edit';
import SiteEditorIndex from '@/pages/admin/site-editor/index';
import SiteEditorTemplates from '@/pages/admin/site-editor/templates';
import { resetPageProps, router, setPageProps } from '@/tests/mocks/inertia';
import type {
    Block,
    GlobalStylesData,
    PluginBlockDefinition,
    SitePartSummary,
} from '@/types';
import { pickOption } from '@/tests/pick-option';

vi.mock('@inertiajs/react', async () => {
    const { inertiaMock } = await import('@/tests/mocks/inertia');

    return inertiaMock;
});

const noTokens: GlobalStylesData = { groups: [], values: {}, fonts: [] };

const header: SitePartSummary = {
    key: 'header',
    label: 'Header',
    status: 'original',
    editedAt: null,
    revisions: 0,
};

const blocks: Block[] = [
    {
        id: 'bar',
        type: 'announcement-bar',
        attrs: { text: 'Saffron buns are back for Lucia' },
    },
];

const siteBlocks: PluginBlockDefinition[] = [
    {
        name: 'announcement-bar',
        label: 'Announcement bar',
        description: 'A short message across the top of the page.',
        fields: [
            {
                name: 'text',
                label: 'Message',
                type: 'text',
                default: '',
                options: [],
                help: 'Keep it short, one line on phones.',
                required: true,
                min: null,
                max: 200,
            },
        ],
        children: false,
    },
];

function renderEditor(
    part: SitePartSummary = header,
    previewUrl = '/admin/site-editor/preview',
    shownWith: { inUse: string; thisStyle: string } | null = null,
) {
    return render(
        <SiteEditorEdit
            theme={{ name: 'Ashgrove', slug: 'ashgrove' }}
            part={part}
            blocks={blocks}
            siteBlocks={siteBlocks}
            patterns={[]}
            previewUrl={previewUrl}
            shownWith={shownWith}
            previewWidths={{ desktop: null, tablet: 768, phone: 390 }}
            autosaveDelay={1200}
            undoSteps={50}
        />,
    );
}

beforeEach(() => {
    setPageProps({ auth: { user: { id: 3, name: 'Ingrid Solberg' } } });
    vi.mocked(router.put).mockClear();
    vi.mocked(router.post).mockClear();
    vi.mocked(router.delete).mockClear();
});

afterEach(() => {
    resetPageProps();
    vi.useRealTimers();
});

describe('Site Editor list', () => {
    it('shows each part with its state in plain words', () => {
        render(
            <SiteEditorIndex
                theme={{ name: 'Ashgrove', slug: 'ashgrove' }}
                parts={[
                    header,
                    {
                        key: 'footer',
                        label: 'Footer',
                        status: 'unpublished',
                        editedAt: '2026-11-17T09:30:00+00:00',
                        revisions: 2,
                    },
                ]}
                styles={[]}
                previewUrl="/admin/site-editor/preview"
                globalStyles={noTokens}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Header' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Theme original')).toBeInTheDocument();
        expect(screen.getByText('Changes not published')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Edit footer' }),
        ).toHaveAttribute('href', '/admin/site-editor/footer');
        expect(screen.getByText(/The top of every page/)).toBeInTheDocument();
    });

    it('explains what to do when the theme has no parts', () => {
        render(
            <SiteEditorIndex
                theme={{ name: 'Plain', slug: 'plain' }}
                parts={[]}
                styles={[]}
                previewUrl="/admin/site-editor/preview"
                globalStyles={noTokens}
            />,
        );

        expect(
            screen.getByText('This theme has no parts to edit'),
        ).toBeInTheDocument();
    });
});

describe('Header and footer styles', () => {
    const styles = [
        {
            name: 'header',
            label: 'Header',
            chosen: 'classic',
            variants: [
                {
                    key: 'classic',
                    label: 'Classic',
                    description: 'Logo on the left.',
                    part: 'header',
                },
                {
                    key: 'centered',
                    label: 'Centered',
                    description: 'Logo in the middle.',
                    part: 'header-centered',
                },
            ],
        },
    ];

    function renderStyles() {
        render(
            <SiteEditorIndex
                theme={{ name: 'Ashgrove', slug: 'ashgrove' }}
                parts={[header]}
                styles={styles}
                previewUrl="/admin/site-editor/preview"
                globalStyles={noTokens}
            />,
        );
    }

    it('previews a picked style before using it', async () => {
        renderStyles();
        const frame = screen.getByTitle(
            'Preview of the picked styles on your home page',
        );
        const use = screen.getByRole('button', { name: 'Use these styles' });

        expect(
            screen.getByRole('radio', { name: /Classic \(in use\)/ }),
        ).toBeChecked();
        expect(use).toBeDisabled();
        expect(frame.getAttribute('src')).toContain(
            'styles%5Bheader%5D=classic',
        );

        await userEvent.click(screen.getByRole('radio', { name: /Centered/ }));

        expect(frame.getAttribute('src')).toContain(
            'styles%5Bheader%5D=centered',
        );
        expect(router.put).not.toHaveBeenCalled();

        await userEvent.click(use);
        expect(router.put).toHaveBeenCalledWith(
            '/admin/site-editor/styles',
            { part: 'header', style: 'centered' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

describe('Templates and customization files', () => {
    it('lists the template of each kind of page and the part styles', () => {
        render(
            <SiteEditorTemplates
                theme={{ name: 'Ashgrove', slug: 'ashgrove' }}
                rows={[
                    {
                        label: 'One Post',
                        kind: 'entry',
                        template: 'entry',
                        tried: ['entry-post', 'entry'],
                    },
                    {
                        label: 'Page template: Wide page',
                        kind: 'custom',
                        template: null,
                        tried: ['templates.wide'],
                    },
                ]}
                parts={[
                    { name: 'header', label: 'Header', style: 'Centered' },
                    { name: 'footer', label: 'Footer', style: 'Simple' },
                ]}
            />,
        );

        expect(
            screen.getByRole('rowheader', { name: 'One Post' }),
        ).toBeInTheDocument();
        expect(screen.getByText('entry-post, entry')).toBeInTheDocument();
        expect(screen.getByText('Built-in fallback')).toBeInTheDocument();
        expect(
            screen.getByText(/the Centered header and the Simple footer/),
        ).toBeInTheDocument();
    });

    it('offers export, import and the templates view', async () => {
        render(
            <SiteEditorIndex
                theme={{ name: 'Ashgrove', slug: 'ashgrove' }}
                parts={[header]}
                styles={[]}
                previewUrl="/admin/site-editor/preview"
                globalStyles={noTokens}
            />,
        );

        expect(screen.getByRole('link', { name: 'Export' })).toHaveAttribute(
            'href',
            '/admin/site-editor/export',
        );
        expect(screen.getByRole('link', { name: 'Templates' })).toHaveAttribute(
            'href',
            '/admin/site-editor/templates',
        );

        const file = new File(
            ['{"format":"verso-site-customization"}'],
            'ashgrove-customization.json',
            { type: 'application/json' },
        );
        await userEvent.upload(
            screen.getByLabelText('Customization file to import'),
            file,
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/site-editor/import',
            { file },
            { forceFormData: true },
        );
    });

    it('shows why an import failed', () => {
        setPageProps({
            errors: { file: 'This file is not a Verso site customization.' },
        });
        render(
            <SiteEditorIndex
                theme={{ name: 'Ashgrove', slug: 'ashgrove' }}
                parts={[header]}
                styles={[]}
                previewUrl="/admin/site-editor/preview"
                globalStyles={noTokens}
            />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'This file is not a Verso site customization.',
        );
    });
});

describe('Colors and fonts', () => {
    const tokens: GlobalStylesData = {
        groups: [
            {
                name: 'color',
                tokens: [
                    {
                        id: 'color.accent',
                        kind: 'color',
                        label: 'Accent',
                        help: 'Links and buttons.',
                        default: '#2e5e4e',
                    },
                ],
            },
            {
                name: 'font',
                tokens: [
                    {
                        id: 'font.heading',
                        kind: 'font',
                        label: 'Headings',
                        help: '',
                        default: 'newsreader',
                    },
                ],
            },
            {
                name: 'radius',
                tokens: [
                    {
                        id: 'radius.base',
                        kind: 'length',
                        label: 'Corners',
                        help: 'Roundness of images.',
                        default: '3px',
                    },
                ],
            },
        ],
        values: {
            'color.accent': '#2e5e4e',
            'font.heading': 'newsreader',
            'radius.base': '3px',
        },
        fonts: [
            {
                value: 'newsreader',
                label: 'Newsreader (serif, from the theme)',
                stack: 'Newsreader, serif',
            },
            {
                value: 'system',
                label: 'System (sans serif)',
                stack: 'system-ui, sans-serif',
            },
        ],
    };

    function renderTokens() {
        render(
            <SiteEditorIndex
                theme={{ name: 'Ashgrove', slug: 'ashgrove' }}
                parts={[header]}
                styles={[]}
                previewUrl="/admin/site-editor/preview"
                globalStyles={tokens}
            />,
        );
    }

    it('shows colors and fonts first and hides the rest under Advanced', async () => {
        renderTokens();

        expect(screen.getByLabelText('Accent')).toHaveValue('#2e5e4e');
        expect(screen.getByText('Fresh bread every morning')).toHaveStyle({
            fontFamily: 'Newsreader, serif',
        });
        expect(screen.queryByLabelText('Corners')).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Advanced' }));

        expect(screen.getByLabelText('Corners')).toHaveValue('3');
        expect(
            screen.getByRole('combobox', { name: 'Corners unit' }),
        ).toHaveTextContent('px');
    });

    it('saves a size in another unit or as a custom value', async () => {
        renderTokens();
        await userEvent.click(screen.getByRole('button', { name: 'Advanced' }));
        await userEvent.click(
            screen.getByRole('combobox', { name: 'Corners unit' }),
        );
        await userEvent.type(
            screen.getByRole('combobox', { name: 'Search units' }),
            'rem{Enter}',
        );
        const frame = screen.getByTitle(
            'Preview of your colors and fonts on the home page',
        );

        expect(frame.getAttribute('src')).toContain(
            'tokens%5Bradius.base%5D=3rem',
        );

        fireEvent.change(screen.getByLabelText('Corners'), {
            target: { value: 'clamp(2px,1vw,12px)' },
        });
        await userEvent.click(
            screen.getByRole('button', { name: 'Save colors and fonts' }),
        );

        expect(router.put).toHaveBeenLastCalledWith(
            '/admin/site-editor/global-styles',
            { values: { 'radius.base': 'clamp(2px, 1vw, 12px)' } },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('previews changed values and saves only those', async () => {
        renderTokens();
        const frame = screen.getByTitle(
            'Preview of your colors and fonts on the home page',
        );
        const save = screen.getByRole('button', {
            name: 'Save colors and fonts',
        });

        expect(frame).toHaveAttribute('src', '/admin/site-editor/preview');
        expect(save).toBeDisabled();

        fireEvent.change(screen.getByLabelText('Accent color code'), {
            target: { value: '#8a3b12' },
        });
        await pickOption('Headings', 'System (sans serif)');

        expect(frame.getAttribute('src')).toContain(
            'tokens%5Bcolor.accent%5D=%238a3b12',
        );
        expect(screen.getByText('Fresh bread every morning')).toHaveStyle({
            fontFamily: 'system-ui, sans-serif',
        });

        await userEvent.click(save);
        expect(router.put).toHaveBeenCalledWith(
            '/admin/site-editor/global-styles',
            { values: { 'color.accent': '#8a3b12', 'font.heading': 'system' } },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('undoes unsaved changes', async () => {
        renderTokens();

        fireEvent.change(screen.getByLabelText('Accent color code'), {
            target: { value: '#8a3b12' },
        });
        await userEvent.click(
            screen.getByRole('button', { name: 'Undo changes' }),
        );

        expect(screen.getByLabelText('Accent')).toHaveValue('#2e5e4e');
    });
});

describe('Site Editor part editor', () => {
    it('previews the home page and switches between device sizes', async () => {
        renderEditor();
        const frame = screen.getByTitle(
            'Preview of the header on your home page',
        );

        expect(frame).toHaveAttribute('src', '/admin/site-editor/preview?v=0');
        // The computer page is drawn at least 1280 pixels wide and shrunk to fit the pane.
        expect(frame).toHaveStyle({ width: '1280px' });

        await userEvent.click(screen.getByRole('radio', { name: 'Phone' }));
        expect(frame).toHaveStyle({ width: '390px' });

        await userEvent.click(screen.getByRole('radio', { name: 'Tablet' }));
        expect(frame).toHaveStyle({ width: '768px' });
    });

    it('previews the style of the part and says when visitors see another one', () => {
        renderEditor(
            header,
            '/admin/site-editor/preview?styles%5Bheader%5D=classic',
            { inUse: 'Centered', thisStyle: 'Classic' },
        );

        expect(
            screen.getByTitle('Preview of the header on your home page'),
        ).toHaveAttribute(
            'src',
            '/admin/site-editor/preview?styles%5Bheader%5D=classic&v=0',
        );
        expect(
            screen.getByText(
                'Visitors see the Centered style now. Pick Classic in Header and footer style to show this one.',
            ),
        ).toHaveAttribute('role', 'note');
    });

    it('publishes the current blocks', async () => {
        renderEditor();

        await userEvent.click(
            screen.getByRole('button', { name: 'Publish changes' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/site-editor/header/publish',
            { blocks },
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('saves a draft on request', async () => {
        renderEditor();

        await userEvent.click(
            screen.getByRole('button', { name: 'Save draft' }),
        );

        expect(router.put).toHaveBeenCalledWith(
            '/admin/site-editor/header',
            { blocks },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('saves a draft by itself after a change and can undo it', () => {
        vi.useFakeTimers();
        renderEditor();
        const undo = screen.getByRole('button', { name: 'Undo' });

        expect(undo).toBeDisabled();

        fireEvent.change(
            screen.getByDisplayValue('Saffron buns are back for Lucia'),
            {
                target: { value: 'Closed on 24 December' },
            },
        );

        expect(router.put).not.toHaveBeenCalled();
        act(() => {
            vi.advanceTimersByTime(1200);
        });

        const [, payload] = vi.mocked(router.put).mock.calls[0];
        expect(JSON.stringify(payload)).toContain('Closed on 24 December');
        expect(undo).toBeEnabled();

        fireEvent.click(undo);
        expect(
            screen.getByDisplayValue('Saffron buns are back for Lucia'),
        ).toBeInTheDocument();
    });

    it('does not autosave after a quick publish, so the publish is not cancelled', () => {
        vi.useFakeTimers();
        renderEditor();

        fireEvent.change(
            screen.getByDisplayValue('Saffron buns are back for Lucia'),
            {
                target: { value: 'Open until 20:00 on Fridays' },
            },
        );
        fireEvent.click(
            screen.getByRole('button', { name: 'Publish changes' }),
        );
        act(() => {
            vi.advanceTimersByTime(1200);
        });

        const [, payload] = vi.mocked(router.post).mock.calls[0];
        expect(JSON.stringify(payload)).toContain(
            'Open until 20:00 on Fridays',
        );
        expect(router.put).not.toHaveBeenCalled();
    });

    it('asks before restoring the original', async () => {
        renderEditor({ ...header, status: 'published', revisions: 3 });

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Restore original' }),
        );

        expect(router.delete).not.toHaveBeenCalled();
        await userEvent.click(
            screen.getByRole('button', { name: 'Restore original' }),
        );
        expect(router.delete).toHaveBeenCalledWith(
            '/admin/site-editor/header',
            expect.any(Object),
        );
    });

    it('cannot restore a part that was never changed', async () => {
        renderEditor();

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );

        expect(
            await screen.findByRole('menuitem', { name: 'Restore original' }),
        ).toHaveAttribute('data-disabled');
    });
});
