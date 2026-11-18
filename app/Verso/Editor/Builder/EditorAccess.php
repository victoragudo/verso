<?php

namespace App\Verso\Editor\Builder;

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Closure;

// Answers who may use which editor feature and which blocks a content type may hold.
// Choices saved on the "Editor settings" page win over the defaults in config/verso.php.
class EditorAccess
{
    // Setting with the saved feature choices: feature => {roles: [...], types: [...] or null for all}.
    public const string FEATURES_KEY = 'editor.features';

    // Setting with the blocks each content type allows: type => [block names]. A missing type allows every block.
    public const string BLOCKS_KEY = 'editor.blocks';

    public function __construct(
        protected Settings $settings,
        protected EditorFeatureRegistry $features,
        protected ThemeEditorLimits $theme,
    ) {}

    /** @return list<string> roles that have the feature */
    public function roles(string $feature): array
    {
        $saved = $this->saved()[$feature]['roles'] ?? null;

        if (is_array($saved)) {
            return $saved;
        }

        return $this->features->find($feature)->roles ?? [];
    }

    /** @return list<string>|null content types where the feature is on, null for every type */
    public function types(string $feature): ?array
    {
        $saved = $this->saved()[$feature]['types'] ?? null;

        return is_array($saved) ? $saved : null;
    }

    // True when the user may use the feature while editing this content type.
    public function enabled(string $feature, ?User $user, ?string $type = null): bool
    {
        if ($user === null || ! $this->features->has($feature)) {
            return false;
        }

        $types = $this->types($feature);

        return array_intersect($user->roleSlugs(), $this->roles($feature)) !== []
            && ($type === null || $types === null || in_array($type, $types, true));
    }

    /** @return list<string> keys of the features the user may use for this content type */
    public function enabledFeatures(?User $user, ?string $type = null): array
    {
        return array_values(array_filter(
            $this->features->keys(),
            fn (string $feature): bool => $this->enabled($feature, $user, $type),
        ));
    }

    /** @return list<string>|null blocks the content type may hold, null when every block is allowed */
    public function allowedBlocks(?string $type): ?array
    {
        $saved = $type === null ? null : ($this->savedBlocks()[$type] ?? null);
        $theme = $this->theme->blocks();

        if ($saved === null || $theme === null) {
            return $saved ?? $theme;
        }

        return array_values(array_intersect($saved, $theme));
    }

    /**
     * Saves the choices of the "Editor settings" page.
     *
     * @param  array<string, array{roles: list<string>, types: list<string>|null}>  $features
     * @param  array<string, list<string>>  $blocks
     */
    public function save(array $features, array $blocks): void
    {
        $this->settings->setMany([self::FEATURES_KEY => $features, self::BLOCKS_KEY => $blocks]);
    }

    // A new role copied from another role gets the same editor tools.
    public function copyRole(string $from, string $to): void
    {
        $this->changeRoles(fn (array $roles): array => in_array($from, $roles, true) ? [...array_values(array_diff($roles, [$to])), $to] : $roles);
    }

    // A deleted role leaves every tool list.
    public function forgetRole(string $role): void
    {
        $this->changeRoles(fn (array $roles): array => array_values(array_diff($roles, [$role])));
    }

    /** @param  Closure(list<string>): list<string>  $change */
    protected function changeRoles(Closure $change): void
    {
        $features = [];

        foreach ($this->features->all() as $feature) {
            $features[$feature->key] = ['roles' => $change($this->roles($feature->key)), 'types' => $this->types($feature->key)];
        }

        $this->settings->set(self::FEATURES_KEY, $features);
    }

    /** @return array<string, list<string>> the saved block lists, by content type */
    public function savedBlocks(): array
    {
        $saved = $this->settings->get(self::BLOCKS_KEY);

        if (! is_array($saved)) {
            return [];
        }

        $blocks = [];

        foreach ($saved as $type => $names) {
            if (is_string($type) && is_array($names)) {
                $blocks[$type] = array_values(array_filter($names, is_string(...)));
            }
        }

        return $blocks;
    }

    /** @return array<string, array{roles?: list<string>, types?: list<string>|null}> */
    protected function saved(): array
    {
        $saved = $this->settings->get(self::FEATURES_KEY);

        return is_array($saved) ? $saved : [];
    }
}
