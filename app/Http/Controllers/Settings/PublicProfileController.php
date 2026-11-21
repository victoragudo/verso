<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Users\Actions\ChangeAvatar;
use App\Verso\Users\Actions\UpdatePublicProfile;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

// The public profile on the account page: bio, photo and social links, shown by themes in the author box and author page.
class PublicProfileController extends Controller
{
    public function update(Request $request, #[CurrentUser] User $user, UpdatePublicProfile $update): RedirectResponse
    {
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:'.(int) config('verso.users.profile.bio_max')],
            'social_links' => ['present', 'array', 'max:'.(int) config('verso.users.profile.social_links_max')],
            'social_links.*' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ], [
            'social_links.*.url' => __('Use a full address that starts with https://.'),
            'social_links.max' => __('Add at most :max links.'),
        ]);

        $update->handle($user, $data['bio'] ?? null, array_values($data['social_links']));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public profile saved.')]);

        return to_route('profile.edit');
    }

    public function uploadAvatar(Request $request, #[CurrentUser] User $user, ChangeAvatar $avatar): RedirectResponse
    {
        abort_unless($user->hasCapability(Capability::UploadFiles), 403);

        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.(int) config('verso.users.profile.avatar_max_kb')],
        ]);

        $avatar->upload($user, $data['avatar']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile photo saved.')]);

        return to_route('profile.edit');
    }

    public function removeAvatar(#[CurrentUser] User $user, ChangeAvatar $avatar): RedirectResponse
    {
        $avatar->remove($user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile photo removed.')]);

        return to_route('profile.edit');
    }
}
