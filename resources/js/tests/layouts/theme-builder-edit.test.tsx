import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { PreviewTarget } from '@/components/verso/workspace/preview-target';
import ThemeBuilderEdit from '@/pages/admin/site-editor/layouts/edit';
import { resetPageProps, setPageProps } from '@/tests/mocks/inertia';

vi.mock('@inertiajs/react', async () => {
    const { inertiaMock } = await import('@/tests/mocks/inertia');

    return inertiaMock;
});

const targets: PreviewTarget[] = [
    { value: 'home', label: 'Home page', usesLayout: false },
    {
        value: 'entry:12',
        label: 'A Slow Weekend in Porto (Post)',
        usesLayout: true,
    },
    { value: 'search', label: 'Search results', usesLayout: false },
];

function renderEditor(previewTargets = targets) {
    return render(
        <ThemeBuilderEdit
            layout={{
                id: 3,
                kind: 'template',
                kindLabel: 'Template',
                name: 'Single post',
                status: 'draft',
                live: false,
                priority: 0,
                conditions: [
                    { type: 'page_kind', value: 'entry', exclude: false },
                ],
                updatedAt: null,
                editUrl: '/admin/theme-builder/3',
                blocks: [],
                settings: {},
            }}
            siteBlocks={[]}
            patterns={[]}
            conditions={[]}
            previewTargets={previewTargets}
            previewUrl="/admin/theme-builder/3/preview"
            previewWidths={{ desktop: null, tablet: 768, phone: 390 }}
            autosaveDelay={1200}
            revisions={[]}
        />,
    );
}

beforeEach(() => {
    window.localStorage.clear();
    setPageProps({ auth: { user: { id: 7, name: 'Inês Carvalho' } } });
});

afterEach(() => resetPageProps());

describe('Theme builder editor', () => {
    it('previews a template on a page it applies to, not on the home page', () => {
        renderEditor();

        expect(screen.getByTitle('Preview of Single post')).toHaveAttribute(
            'src',
            '/admin/theme-builder/3/preview?target=entry%3A12&v=0',
        );
        expect(
            screen.queryByText(/This page does not use this layout/),
        ).not.toBeInTheDocument();
    });

    it('says when the picked page does not use the layout and offers one that does', async () => {
        renderEditor([
            { value: 'home', label: 'Home page', usesLayout: false },
            {
                value: 'entry:12',
                label: 'A Slow Weekend in Porto (Post)',
                usesLayout: true,
            },
        ]);
        await userEvent.click(
            screen.getByRole('combobox', { name: 'Preview on' }),
        );
        await userEvent.click(
            screen.getByRole('option', { name: 'Home page' }),
        );

        expect(
            screen.getByText(/This page does not use this layout/),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Show A Slow Weekend in Porto (Post)',
            }),
        );

        expect(
            screen.queryByText(/This page does not use this layout/),
        ).not.toBeInTheDocument();
    });

    it('puts the help text in a tip that can be closed', async () => {
        renderEditor();

        await userEvent.click(
            screen.getByRole('button', { name: 'Close this tip' }),
        );

        expect(
            screen.queryByText(/Use the Page data blocks/),
        ).not.toBeInTheDocument();
    });
});
