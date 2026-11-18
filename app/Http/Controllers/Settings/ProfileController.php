<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'canViewAdminBar' => $request->user()->hasCapability(Capability::ViewAdminBar),
            'publicProfile' => $this->publicProfile($request),
        ]);
    }

    /**
     * Bio, photo and social links for the public profile form, with the limits.
     *
     * @return array<string, mixed>
     */
    protected function publicProfile(Request $request): array
    {
        $user = $request->user()->loadMissing('avatar');
        $avatar = $user->avatar;
        $hasPosts = $user->slug !== null && Entry::query()->where('author_id', $user->id)->published()->exists();

        return [
            'bio' => $user->bio ?? '',
            'socialLinks' => array_values(array_filter((array) $user->social_links, is_string(...))),
            'avatar' => $avatar === null ? null : [
                'id' => $avatar->id,
                'url' => $avatar->isResizable() ? $avatar->imageUrl(['w' => 192, 'h' => 192, 'fit' => 'crop']) : $avatar->url(),
            ],
            'canUploadAvatar' => $user->hasCapability(Capability::UploadFiles),
            'authorUrl' => $hasPosts ? $user->authorUrl() : null,
            'limits' => [
                'bioMax' => (int) config('verso.users.profile.bio_max'),
                'socialLinksMax' => (int) config('verso.users.profile.social_links_max'),
                'avatarMaxKb' => min((int) config('verso.users.profile.avatar_max_kb'), (int) config('verso.media.max_upload_kb')),
            ],
        ];
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
