import { render, renderHook, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    focusPageStart,
    pathOf,
    usePageChangeFocus,
} from '@/hooks/use-page-change-focus';
import { fireRouterEvent } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

function navigate(url: string): void {
    fireRouterEvent('navigate', { page: { url } });
}

function page(heading: string | null) {
    return (
        <>
            <input aria-label="Search posts" />
            <main id="admin-content" tabIndex={-1}>
                {heading && <h1>{heading}</h1>}
                <p>Recent posts about the autumn harvest.</p>
            </main>
        </>
    );
}

describe('usePageChangeFocus', () => {
    beforeEach(() => {
        vi.spyOn(window, 'requestAnimationFrame').mockImplementation(
            (callback) => {
                callback(0);

                return 1;
            },
        );
        window.history.replaceState({}, '', '/admin/entries/post');
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('moves focus to the new page heading after a visit to another page', () => {
        render(page('Pages'));
        const { unmount } = renderHook(() =>
            usePageChangeFocus('admin-content'),
        );

        navigate('/admin/entries/page');

        const heading = screen.getByRole('heading', { name: 'Pages' });
        expect(heading).toHaveFocus();
        expect(heading).toHaveAttribute('tabindex', '-1');
        unmount();
    });

    it('keeps focus in a search box when only the query changes', () => {
        render(page('Posts'));
        const { unmount } = renderHook(() =>
            usePageChangeFocus('admin-content'),
        );
        const search = screen.getByLabelText('Search posts');
        search.focus();

        navigate('/admin/entries/post?search=harvest');

        expect(search).toHaveFocus();
        unmount();
    });

    it('leaves focus alone while a dialog is open', () => {
        render(
            <>
                {page('Media')}
                <div role="dialog" aria-label="Upload files">
                    <button type="button">Choose files</button>
                </div>
            </>,
        );
        const { unmount } = renderHook(() =>
            usePageChangeFocus('admin-content'),
        );
        const button = screen.getByRole('button', { name: 'Choose files' });
        button.focus();

        navigate('/admin/media');

        expect(button).toHaveFocus();
        unmount();
    });

    it('falls back to the content area when the page has no heading', () => {
        render(page(null));
        const { unmount } = renderHook(() =>
            usePageChangeFocus('admin-content'),
        );

        navigate('/admin/site-editor/header');

        expect(document.getElementById('admin-content')).toHaveFocus();
        unmount();
    });

    it('stops listening when unmounted', () => {
        render(page('Comments'));
        const search = screen.getByLabelText('Search posts');
        const { unmount } = renderHook(() =>
            usePageChangeFocus('admin-content'),
        );
        unmount();
        search.focus();

        navigate('/admin/comments');

        expect(search).toHaveFocus();
    });
});

describe('pathOf', () => {
    it('drops the query and the fragment', () => {
        expect(pathOf('/admin/entries/post?search=harvest#list')).toBe(
            '/admin/entries/post',
        );
        expect(pathOf('https://verso.test/admin/users?page=2')).toBe(
            '/admin/users',
        );
    });
});

describe('focusPageStart', () => {
    it('keeps a tabindex the element already has', () => {
        render(page(null));
        const main = document.getElementById('admin-content') as HTMLElement;

        focusPageStart(main);

        expect(main).toHaveAttribute('tabindex', '-1');
        expect(main).toHaveFocus();
    });
});
