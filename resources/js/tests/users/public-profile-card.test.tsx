import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    PublicProfileCard,
    linkErrors,
    linkRows,
} from '@/components/verso/users/public-profile-card';
import type { PublicProfile } from '@/components/verso/users/public-profile-card';
import { destroy, store } from '@/routes/profile/avatar';
import { update } from '@/routes/profile/public';
import { router } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const profile: PublicProfile = {
    bio: 'Clara cooks and bakes bread every Sunday.',
    socialLinks: ['https://bsky.app/profile/clara.example'],
    avatar: null,
    canUploadAvatar: true,
    authorUrl: '/author/clara-moreno',
    limits: { bioMax: 600, socialLinksMax: 3, avatarMaxKb: 1024 },
};

describe('PublicProfileCard', () => {
    beforeEach(() => {
        vi.mocked(router.put).mockReset();
        vi.mocked(router.post).mockReset();
        vi.mocked(router.delete).mockReset();
    });

    it('shows the bio, the links with one empty row and the author page link', () => {
        render(<PublicProfileCard profile={profile} />);

        expect(screen.getByLabelText('Bio')).toHaveValue(profile.bio);
        expect(screen.getByLabelText('Social link 1')).toHaveValue(
            'https://bsky.app/profile/clara.example',
        );
        expect(screen.getByLabelText('Social link 2')).toHaveValue('');
        expect(
            screen.getByRole('link', { name: 'View your author page' }),
        ).toHaveAttribute('href', '/author/clara-moreno');
        expect(screen.getByText(/41 of 600 characters/)).toBeInTheDocument();
    });

    it('adds a row while typing, up to the limit, and saves filled links only', async () => {
        const user = userEvent.setup();
        render(<PublicProfileCard profile={profile} />);

        await user.type(
            screen.getByLabelText('Social link 2'),
            'https://instagram.com/clara.cooks',
        );
        expect(screen.getByLabelText('Social link 3')).toHaveValue('');
        await user.type(
            screen.getByLabelText('Social link 3'),
            'https://clara.example',
        );
        expect(
            screen.queryByLabelText('Social link 4'),
        ).not.toBeInTheDocument();

        await user.click(
            screen.getByRole('button', { name: 'Save public profile' }),
        );

        expect(router.put).toHaveBeenCalledWith(
            update.url(),
            {
                bio: profile.bio,
                social_links: [
                    'https://bsky.app/profile/clara.example',
                    'https://instagram.com/clara.cooks',
                    'https://clara.example',
                ],
            },
            expect.any(Object),
        );
    });

    it('uploads a photo as form data and refuses one that is too big', () => {
        render(<PublicProfileCard profile={profile} />);
        const input = screen.getByLabelText('Profile photo');
        const photo = new File(['portrait'], 'clara.jpg', {
            type: 'image/jpeg',
        });

        fireEvent.change(input, { target: { files: [photo] } });

        expect(router.post).toHaveBeenCalledWith(
            store.url(),
            { avatar: photo },
            expect.objectContaining({ forceFormData: true }),
        );

        const huge = new File([new Uint8Array(2 * 1024 * 1024)], 'huge.jpg', {
            type: 'image/jpeg',
        });
        fireEvent.change(input, { target: { files: [huge] } });

        expect(router.post).toHaveBeenCalledTimes(1);
        expect(screen.getByText(/The photo is too big/)).toBeInTheDocument();
    });

    it('removes the photo and explains when the role cannot upload', async () => {
        render(
            <PublicProfileCard
                profile={{
                    ...profile,
                    canUploadAvatar: false,
                    avatar: { id: 4, url: '/img/clara.jpg?w=192' },
                }}
            />,
        );

        expect(screen.getByAltText('Your profile photo')).toHaveAttribute(
            'src',
            '/img/clara.jpg?w=192',
        );
        expect(
            screen.getByText(/ask an admin to add a photo/),
        ).toBeInTheDocument();

        const confirm = vi
            .spyOn(window, 'confirm')
            .mockReturnValueOnce(false)
            .mockReturnValueOnce(true);

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove photo' }),
        );
        expect(confirm).toHaveBeenCalledWith('Remove your profile photo?');
        expect(router.delete).not.toHaveBeenCalled();

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove photo' }),
        );

        expect(router.delete).toHaveBeenCalledWith(destroy.url(), {
            preserveScroll: true,
        });
    });
});

describe('profile helpers', () => {
    it('adds one empty row below the limit', () => {
        expect(linkRows([], 2)).toEqual(['']);
        expect(linkRows(['https://a.example', 'https://b.example'], 2)).toEqual(
            ['https://a.example', 'https://b.example'],
        );
    });

    it('maps link errors to their row', () => {
        expect(
            linkErrors({
                'social_links.1': 'Use a full address.',
                bio: 'Too long.',
            }),
        ).toEqual({ 1: 'Use a full address.' });
    });
});
