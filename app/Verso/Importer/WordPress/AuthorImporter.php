<?php

namespace App\Verso\Importer\WordPress;

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\Wxr\WxrAuthor;
use Illuminate\Support\Str;

// Finds or creates a user for each WordPress author. Existing users are never changed.
class AuthorImporter
{
    public function __construct(
        protected Role $role = Role::Author,
        protected int $passwordLength = 32,
        protected string $fallbackDomain = 'imported.invalid',
    ) {}

    public function import(WxrAuthor $author, ImportState $state): User
    {
        $email = $this->emailFor($author, $state);
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            $state->report->add('authors', ImportReport::SKIPPED);
        } else {
            $user = new User([
                'name' => $author->name(),
                'email' => $email,
                'password' => Str::password($this->passwordLength),
            ]);
            $user->role = $this->role;
            $user->save();

            $state->report->add('authors', ImportReport::CREATED);
        }

        $state->rememberAuthor($author->login, $author->id, $user);

        return $user;
    }

    // Authors without an email get "login@<old site domain>".
    protected function emailFor(WxrAuthor $author, ImportState $state): string
    {
        if (filter_var($author->email, FILTER_VALIDATE_EMAIL) !== false) {
            return strtolower($author->email);
        }

        $host = (string) parse_url($state->channel->baseSiteUrl, PHP_URL_HOST);
        $local = Str::slug($author->login !== '' ? $author->login : $author->name(), '.');

        return strtolower(($local !== '' ? $local : 'author'.$author->id).'@'.($host !== '' ? $host : $this->fallbackDomain));
    }
}
