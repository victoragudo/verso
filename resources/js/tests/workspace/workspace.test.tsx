import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AdminShell } from '@/components/verso/shell/admin-shell';
import { BuilderWorkspace } from '@/components/verso/workspace/builder-workspace';
import { setFocusMode } from '@/components/verso/workspace/focus-mode';
import { SplitPane } from '@/components/verso/workspace/split-pane';
import { workspaceMode } from '@/components/verso/workspace/workspace-mode';
import { resetPageProps, setPageProps } from '@/tests/mocks/inertia';

vi.mock('@inertiajs/react', async () => {
    const { inertiaMock } = await import('@/tests/mocks/inertia');

    return inertiaMock;
});

const designer = {
    id: 7,
    name: 'Inês Carvalho',
    email: 'ines@harborlanebakery.pt',
};

beforeEach(() => {
    window.localStorage.clear();
    setPageProps({ auth: { user: designer } });
});

afterEach(() => {
    resetPageProps();
    setFocusMode('verso.workspace.7.focus', false);
});

describe('SplitPane', () => {
    const renderSplit = () =>
        render(
            <SplitPane
                name="theme-builder"
                startId="canvas"
                start={<p>Blocks of Spring header</p>}
                end={<p>Preview</p>}
                shown="start"
            />,
        );

    it('is a keyboard separator that controls the canvas', () => {
        renderSplit();
        const separator = screen.getByRole('separator', {
            name: 'Resize the editor and the preview',
        });

        expect(separator).toHaveAttribute('aria-orientation', 'vertical');
        expect(separator).toHaveAttribute('aria-controls', 'canvas');
        expect(separator).toHaveAttribute('tabindex', '0');
        expect(Number(separator.getAttribute('aria-valuenow'))).toBeGreaterThan(
            0,
        );
    });

    it('moves with the arrow keys and keeps the size per user', () => {
        renderSplit();
        const separator = screen.getByRole('separator');
        const before = Number(separator.getAttribute('aria-valuenow'));

        fireEvent.keyDown(separator, { key: 'ArrowRight' });

        expect(Number(separator.getAttribute('aria-valuenow'))).toBe(
            before + 2,
        );
        expect(
            Number(
                window.localStorage.getItem(
                    'verso.workspace.7.split.theme-builder',
                ),
            ),
        ).toBeCloseTo((before + 2) / 100);

        fireEvent.keyDown(separator, { key: 'Enter' });

        expect(Number(separator.getAttribute('aria-valuenow'))).toBe(before);
        expect(
            window.localStorage.getItem(
                'verso.workspace.7.split.theme-builder',
            ),
        ).toBeNull();
    });

    it('starts from the saved size', () => {
        window.localStorage.setItem(
            'verso.workspace.7.split.theme-builder',
            '0.6',
        );
        renderSplit();

        expect(screen.getByRole('separator')).toHaveAttribute(
            'aria-valuenow',
            '60',
        );
    });

    it('still works when the browser blocks storage', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('blocked');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('blocked');
        });
        renderSplit();
        const separator = screen.getByRole('separator');

        expect(() =>
            fireEvent.keyDown(separator, { key: 'ArrowLeft' }),
        ).not.toThrow();
        vi.restoreAllMocks();
    });
});

describe('workspaceMode', () => {
    it('shows every pane on ultrawide screens and tabs on small ones', () => {
        expect(workspaceMode(3440)).toBe('wide');
        expect(workspaceMode(2000)).toBe('large');
        expect(workspaceMode(1200)).toBe('medium');
        expect(workspaceMode(390)).toBe('narrow');
    });
});

describe('BuilderWorkspace', () => {
    const renderWorkspace = () =>
        render(
            <AdminShell breadcrumbs={[]} workspace>
                <BuilderWorkspace
                    name="theme-builder"
                    backHref="/admin/theme-builder"
                    backLabel="Back to the theme builder"
                    status={<h1>Spring header</h1>}
                    actions={<button type="button">Publish</button>}
                    menu={null}
                    canvasLabel="Blocks of Spring header"
                    canvas={<p>Logo and menu</p>}
                    preview={<p>Live preview</p>}
                    settings={<p>Where it shows</p>}
                    settingsDescription="Name, priority, where it shows, options and the selected block."
                    tip={{
                        id: 'layout-part',
                        text: 'Add, move and change blocks.',
                    }}
                />
            </AdminShell>,
        );

    it('turns focus mode on and off and remembers it', async () => {
        renderWorkspace();
        const focus = screen.getByRole('button', { name: 'Focus mode' });

        expect(focus).toHaveAttribute('aria-pressed', 'false');
        expect(
            document.querySelector('[data-slot="admin-sidebar"]'),
        ).toHaveClass('lg:block');

        await userEvent.click(focus);

        expect(focus).toHaveAttribute('aria-pressed', 'true');
        expect(
            document.querySelector('[data-slot="admin-sidebar"]'),
        ).not.toHaveClass('lg:block');
        expect(window.localStorage.getItem('verso.workspace.7.focus')).toBe(
            'on',
        );
    });

    it('shows a back arrow, and the Verso logo only in focus mode', async () => {
        renderWorkspace();
        const back = () =>
            screen.getByRole('link', { name: 'Back to the theme builder' });

        expect(back().querySelector('svg.lucide-arrow-left')).not.toBeNull();

        await userEvent.click(
            screen.getByRole('button', { name: 'Focus mode' }),
        );

        expect(back().querySelector('svg.lucide-arrow-left')).toBeNull();
        expect(back()).toHaveClass('bg-zinc-900');
    });

    it('offers Edit and Preview tabs for small screens', async () => {
        renderWorkspace();
        const preview = screen.getByRole('tab', { name: 'Preview' });

        expect(screen.getByRole('tab', { name: 'Edit' })).toHaveAttribute(
            'aria-selected',
            'true',
        );
        await userEvent.click(preview);
        expect(preview).toHaveAttribute('aria-selected', 'true');
    });

    it('closes the tip for good', async () => {
        const { unmount } = renderWorkspace();

        await userEvent.click(
            screen.getByRole('button', { name: 'Close this tip' }),
        );
        expect(
            screen.queryByText('Add, move and change blocks.'),
        ).not.toBeInTheDocument();
        unmount();

        renderWorkspace();
        expect(
            screen.queryByText('Add, move and change blocks.'),
        ).not.toBeInTheDocument();
    });
});
