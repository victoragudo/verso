import { router } from '@inertiajs/react';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CampaignEdit from '@/pages/admin/plugins/newsletter/campaign-edit';

vi.mock('@inertiajs/react', async () => {
    const base = (await import('@/tests/mocks/inertia')).inertiaMock;
    const react = await import('react');

    return {
        ...base,
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setState] = react.useState(initial);

            return {
                data,
                setData: (key: keyof T, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                transform: vi.fn(),
                errors: {},
                processing: false,
                reset: vi.fn(),
                post: vi.fn(),
                put: vi.fn(),
            };
        },
    };
});

vi.mock('@/components/verso/editor/block-editor', () => ({
    BlockEditor: () => <div data-testid="block-editor" />,
}));

afterEach(() => {
    vi.restoreAllMocks();
    vi.mocked(router.post).mockReset();
});

function renderCampaign() {
    return render(
        <CampaignEdit
            campaign={{
                id: 12,
                subject: 'Harvest market dates for November',
                preheader: null,
                blocks: [],
                list_id: null,
                track_opens: true,
                track_clicks: false,
                recipients: 418,
            }}
            defaults={{ track_opens: true, track_clicks: false }}
            lists={[]}
            pluginBlocks={[]}
            urls={{
                index: '/admin/newsletter/campaigns',
                save: '/admin/newsletter/campaigns/12',
                test: '/admin/newsletter/campaigns/12/test',
                send: '/admin/newsletter/campaigns/12/send',
                destroy: null,
            }}
        />,
    );
}

describe('Campaign editor', () => {
    it('locks the send button while the campaign is being sent', async () => {
        const user = userEvent.setup();
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        renderCampaign();

        const send = screen.getByRole('button', { name: 'Send campaign' });
        await user.click(send);

        expect(router.post).toHaveBeenCalledTimes(1);
        const options = vi.mocked(router.post).mock.calls[0][2] as {
            onStart: () => void;
            onFinish: () => void;
        };

        act(() => options.onStart());
        expect(
            screen.getByRole('button', { name: /Send campaign/ }),
        ).toBeDisabled();

        act(() => options.onFinish());
        expect(
            screen.getByRole('button', { name: 'Send campaign' }),
        ).toBeEnabled();
    });
});
