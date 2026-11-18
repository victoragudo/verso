<?php

namespace App\Models;

use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Permissions\UserPermissions;
use App\Verso\Access\Support\ViewAs;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use App\Verso\I18n\AdminLocale;
use App\Verso\Media\Models\Media;
use App\Verso\Site\Blocks\SocialLinksBlock;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property string|null $admin_locale
 * @property bool $show_admin_bar
 * @property array<string, mixed>|null $admin_preferences
 * @property string|null $bio
 * @property int|null $avatar_media_id
 * @property array<mixed>|null $social_links
 * @property string|null $slug
 * @property-read Media|null $avatar
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'show_admin_bar', 'bio', 'avatar_media_id', 'social_links'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'admin_preferences'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    // Longest part of a slug taken from the name, so a number still fits in the column.
    public const SLUG_BASE_LENGTH = 180;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Default values for new users.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'subscriber',
        'show_admin_bar' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
            'show_admin_bar' => 'boolean',
            'admin_preferences' => 'array',
            'social_links' => 'array',
        ];
    }

    // Every user gets a slug for the public author page. It is kept when the name changes, so links stay valid.
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->slug === null || $user->slug === '') {
                $user->slug = $user->uniqueSlug();
            }
        });
    }

    // A free slug made from the name, like "clara-moreno" or "clara-moreno-2".
    public function uniqueSlug(): string
    {
        $base = Str::limit(Str::slug($this->name), self::SLUG_BASE_LENGTH, '') ?: 'author';
        $slug = $base;

        for ($number = 2; static::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists(); $number++) {
            $slug = $base.'-'.$number;
        }

        return $slug;
    }

    /**
     * Social profile links with the network name, like [["url" => "https://bsky.app/...", "label" => "Bluesky"]].
     *
     * @return list<array{url: string, label: string}>
     */
    public function socialProfiles(): array
    {
        $profiles = [];

        foreach (is_array($this->social_links) ? $this->social_links : [] as $url) {
            if (is_string($url) && $url !== '') {
                $profiles[] = ['url' => $url, 'label' => SocialLinksBlock::networkOf($url) ?? (string) parse_url($url, PHP_URL_HOST)];
            }
        }

        return $profiles;
    }

    // The public page with the posts of this author.
    public function authorUrl(): string
    {
        return route('theme.author', $this->slug ?? $this->uniqueSlug());
    }

    // Mail and notifications to this user use the admin language.
    public function preferredLocale(): string
    {
        return app(AdminLocale::class)->forUser($this);
    }

    /** @var list<string>|null */
    protected ?array $extraRoleCache = null;

    /** @var array<string, bool>|null */
    protected ?array $overrideCache = null;

    // True when the roles and single grants of the user give this capability.
    public function hasCapability(Capability|string $capability): bool
    {
        return app(UserPermissions::class)->allows($this, $capability instanceof Capability ? $capability->value : $capability);
    }

    /**
     * The main role first, then the extra roles.
     *
     * @return list<string>
     */
    public function roleSlugs(): array
    {
        // While an administrator previews a role, the admin answers as that role only.
        $previewed = $this->exists ? app(ViewAs::class)->roleFor((int) $this->getKey()) : null;

        return $previewed !== null ? [$previewed] : array_values(array_unique([$this->role->value, ...$this->extraRoles()]));
    }

    public function hasRole(Role|string $role): bool
    {
        return in_array($role instanceof Role ? $role->value : $role, $this->roleSlugs(), true);
    }

    /**
     * Roles the user has on top of the main role.
     *
     * @return list<string>
     */
    public function extraRoles(): array
    {
        if (! $this->exists) {
            return [];
        }

        return $this->extraRoleCache ??= array_values(DB::table('role_user')->where('user_id', $this->getKey())->orderBy('role')->pluck('role')->map(fn (mixed $role): string => (string) $role)->all());
    }

    /**
     * Single capabilities given (true) or taken away (false) for this user only.
     *
     * @return array<string, bool>
     */
    public function capabilityOverrides(): array
    {
        if (! $this->exists) {
            return [];
        }

        return $this->overrideCache ??= DB::table('user_capabilities')->where('user_id', $this->getKey())->orderBy('capability')
            ->pluck('granted', 'capability')->map(fn (mixed $granted): bool => (bool) $granted)->all();
    }

    // Forget the loaded roles and capabilities, after they changed.
    public function refreshAccess(): void
    {
        $this->extraRoleCache = null;
        $this->overrideCache = null;
        app(UserPermissions::class)->forget($this);
    }

    /**
     * Users with this role, as main role or as an extra role.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithRole(Builder $query, Role|string $role): Builder
    {
        $slug = $role instanceof Role ? $role->value : $role;

        return $query->where(fn (Builder $query) => $query
            ->where('role', $slug)
            ->orWhereIn('id', DB::table('role_user')->select('user_id')->where('role', $slug)));
    }

    // True when the user may see the admin bar on the site and did not turn it off.
    public function seesAdminBar(): bool
    {
        return $this->show_admin_bar !== false && $this->hasCapability(Capability::ViewAdminBar);
    }

    // Photo shown next to the bio in the author box of themes.
    /** @return BelongsTo<Media, $this> */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_media_id');
    }

    // True when the user is an administrator, as main role or as an extra role.
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin);
    }

    /**
     * Every capability the user has right now, for the admin UI.
     *
     * @return list<string>
     */
    public function capabilityNames(): array
    {
        return array_values(array_filter(app(CapabilityRegistry::class)->keys(), fn (string $capability): bool => $this->hasCapability($capability)));
    }
}
