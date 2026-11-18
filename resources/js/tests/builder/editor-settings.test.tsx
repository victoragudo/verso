import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { resetBuilder } from '@/builder/setup';
import EditorSettingsPage, {
    blockLabel,
    toggleValue,
} from '@/pages/admin/editor/settings';

const put = vi.fn();
const setData = vi.fn();
let formData: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useForm: (initial: Record<string, unknown>) => {
        formData = initial;

        return { data: initial, setData, put, processing: false };
    },
}));

const props = {
    features: [
        {
            key: 'code_editor',
            label: 'Code view',
            description: 'Edit the whole page as HTML code.',
            roles: ['admin', 'editor'],
            types: null,
        },
    ],
    roles: [
        { value: 'admin', label: 'Admin' },
        { value: 'editor', label: 'Editor' },
        { value: 'author', label: 'Author' },
    ],
    contentTypes: [
        { name: 'post', label: 'Posts', blocks: ['paragraph', 'heading'] },
        { name: 'page', label: 'Pages', blocks: null },
    ],
    blocks: ['paragraph', 'heading', 'image', 'shop/product-grid'],
    themeBlocks: null,
};

beforeEach(() => {
    resetBuilder();
    setData.mockClear();
    put.mockClear();
});

describe('helpers', () => {
    it('toggles a value in a list', () => {
        expect(toggleValue(['admin'], 'editor')).toEqual(['admin', 'editor']);
        expect(toggleValue(['admin', 'editor'], 'admin')).toEqual(['editor']);
    });

    it('names blocks with their registered label or a readable name', () => {
        expect(blockLabel('image')).toBe('Image');
        expect(blockLabel('shop/product-grid')).toBe('Shop product grid');
    });
});

describe('EditorSettingsPage', () => {
    it('shows the roles of each tool and the blocks of limited types', () => {
        render(<EditorSettingsPage {...props} />);

        expect(
            screen.getByRole('checkbox', { name: 'Editor' }),
        ).toHaveAttribute('data-state', 'checked');
        expect(
            screen.getByRole('checkbox', { name: 'Author' }),
        ).toHaveAttribute('data-state', 'unchecked');
        expect(
            screen.getByRole('checkbox', { name: 'Everywhere' }),
        ).toHaveAttribute('data-state', 'checked');
        expect(screen.getAllByRole('checkbox', { name: 'Image' })).toHaveLength(
            1,
        );
        expect(formData).toEqual({
            features: {
                code_editor: { roles: ['admin', 'editor'], types: null },
            },
            blocks: { post: ['paragraph', 'heading'], page: null },
        });
    });

    it('gives a tool to a role when its box is ticked', async () => {
        render(<EditorSettingsPage {...props} />);
        await userEvent.click(screen.getByRole('checkbox', { name: 'Author' }));

        expect(setData).toHaveBeenCalledWith('features', {
            code_editor: { roles: ['admin', 'editor', 'author'], types: null },
        });
    });

    it('limits a tool to some content types', async () => {
        render(<EditorSettingsPage {...props} />);
        await userEvent.click(
            screen.getByRole('checkbox', { name: 'Everywhere' }),
        );

        expect(setData).toHaveBeenCalledWith('features', {
            code_editor: {
                roles: ['admin', 'editor'],
                types: ['post', 'page'],
            },
        });
    });

    it('turns a block off for a content type', async () => {
        render(<EditorSettingsPage {...props} />);
        await userEvent.click(
            screen.getByRole('checkbox', { name: 'Heading' }),
        );

        expect(setData).toHaveBeenCalledWith('blocks', {
            post: ['paragraph'],
            page: null,
        });
    });

    it('saves with a PUT request', async () => {
        render(<EditorSettingsPage {...props} />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Save settings' }),
        );

        expect(put).toHaveBeenCalledWith('/admin/editor/settings', {
            preserveScroll: true,
        });
    });
});
