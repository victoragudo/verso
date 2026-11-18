<?php

namespace App\Verso\Users\Actions;

use App\Models\User;
use App\Verso\Editor\Support\UrlSanitizer;

// Saves the public part of a profile: the bio and the social links that themes show next to posts.
class UpdatePublicProfile
{
    public function __construct(protected UrlSanitizer $urls) {}

    /**
     * @param  list<string|null>  $socialLinks
     */
    public function handle(User $user, ?string $bio, array $socialLinks): User
    {
        $bio = trim((string) $bio);

        $user->forceFill([
            'bio' => $bio === '' ? null : $bio,
            'social_links' => $this->links($socialLinks) ?: null,
        ])->save();

        return $user;
    }

    /**
     * Safe, unique links without empty lines, in the order given.
     *
     * @param  list<string|null>  $links
     * @return list<string>
     */
    protected function links(array $links): array
    {
        $clean = [];

        foreach ($links as $link) {
            $url = $this->urls->cleanHttp($link);

            if ($url !== null && ! in_array($url, $clean, true)) {
                $clean[] = $url;
            }
        }

        return $clean;
    }
}
