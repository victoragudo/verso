import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { EntryCustomCode } from '@/components/verso/editor/entry-custom-code';
import CustomCodePage from '@/pages/admin/editor/custom-code';

vi.mock('@/components/verso/editor/code-view', () => ({
    default: ({
        value,
        onChange,
        label,
    }: {
        value: string;
        onChange: (value: string) => void;
        label: string;
    }) => (
        <textarea
            aria-label={label}
            value={value}
            onChange={(event) => onChange(event.target.value)}
        />
    ),
}));

const put = vi.fn();
const setData = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useForm: (initial: Record<string, string>) => ({
        data: initial,
        setData,
        put,
        processing: false,
        errors: {},
    }),
}));

const fetchMock = vi.fn();

beforeEach(() => {
    put.mockClear();
    setData.mockClear();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
});

describe('Custom code page', () => {
    it('shows only the editors the user may change and saves', async () => {
        render(
            <CustomCodePage
                css="body { margin: 0; }"
                js=""
                can={{ css: true, js: false }}
            />,
        );

        expect(await screen.findByLabelText('Custom CSS')).toHaveValue(
            'body { margin: 0; }',
        );
        expect(
            screen.queryByLabelText('Custom JavaScript'),
        ).not.toBeInTheDocument();
        fireEvent.change(screen.getByLabelText('Custom CSS'), {
            target: { value: 'h1 { color: navy; }' },
        });
        expect(setData).toHaveBeenCalledWith({
            css: 'h1 { color: navy; }',
            js: '',
        });
        await userEvent.click(
            screen.getByRole('button', { name: 'Save custom code' }),
        );
        expect(put).toHaveBeenCalledWith('/admin/editor/code', {
            preserveScroll: true,
        });
    });
});

describe('EntryCustomCode', () => {
    it('loads and saves the code of one page', async () => {
        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    css: '.stall { color: red; }',
                    js: '',
                    can: { css: true, js: true },
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    css: '.stall { color: green; }',
                    js: 'go();',
                }),
            });
        render(<EntryCustomCode entryId={42} />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Custom CSS and JavaScript' }),
        );

        expect(await screen.findByLabelText('Custom CSS')).toHaveValue(
            '.stall { color: red; }',
        );
        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/editor/code/entries/42',
            expect.objectContaining({ method: 'GET' }),
        );
        fireEvent.change(screen.getByLabelText('Custom JavaScript'), {
            target: { value: 'go();' },
        });
        await act(async () => {
            await userEvent.click(
                screen.getByRole('button', { name: 'Save custom code' }),
            );
        });

        expect(fetchMock).toHaveBeenLastCalledWith(
            '/admin/editor/code/entries/42',
            expect.objectContaining({
                method: 'PUT',
                body: JSON.stringify({
                    css: '.stall { color: red; }',
                    js: 'go();',
                }),
            }),
        );
        expect(screen.getByRole('status')).toHaveTextContent(
            'Custom code saved.',
        );
    });

    it('says so when the user may not change it', async () => {
        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 403,
            json: async () => ({}),
        });
        render(<EntryCustomCode entryId={7} />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Custom CSS and JavaScript' }),
        );

        expect(
            await screen.findByText(
                'You can not change the custom code of this page.',
            ),
        ).toBeInTheDocument();
    });
});
