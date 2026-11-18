<?php

namespace App\Verso\Access\Capabilities;

use App\Verso\Access\Enums\RiskLevel;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\RoleRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use App\Verso\Theme\Theme;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

// Reads the "capabilities" and "roles" parts of verso.json and theme.json and registers them.
class ManifestAccess
{
    public function __construct(
        protected CapabilityRegistry $capabilities,
        protected RoleRegistry $roles,
    ) {}

    /**
     * Validation rules for the two manifest parts.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $risks = implode(',', array_column(RiskLevel::cases(), 'value'));
        $builtIn = implode(',', array_column(Role::cases(), 'value'));

        return [
            'capabilities' => ['nullable', 'array', 'list', 'max:50'],
            'capabilities.*' => ['array'],
            'capabilities.*.key' => ['required', 'string', 'max:120', 'distinct', 'regex:'.CapabilityDefinition::KEY_PATTERN],
            'capabilities.*.label' => ['required', 'string', 'max:120'],
            'capabilities.*.description' => ['nullable', 'string', 'max:300'],
            'capabilities.*.group' => ['nullable', 'string', 'max:60'],
            'capabilities.*.section' => ['nullable', 'string', 'max:60'],
            'capabilities.*.risk' => ['nullable', 'string', 'in:'.$risks],
            'capabilities.*.roles' => ['nullable', 'array', 'list'],
            'capabilities.*.roles.*' => ['string', 'in:'.$builtIn],
            'capabilities.*.implies' => ['nullable', 'array', 'list'],
            'capabilities.*.implies.*' => ['string', 'max:120', 'regex:'.CapabilityDefinition::KEY_PATTERN],
            'roles' => ['nullable', 'array', 'list', 'max:10'],
            'roles.*' => ['array'],
            'roles.*.slug' => ['required', 'string', 'max:64', 'distinct', 'regex:'.RoleDefinition::SLUG_PATTERN, 'not_in:'.$builtIn],
            'roles.*.name' => ['required', 'string', 'max:120'],
            'roles.*.description' => ['nullable', 'string', 'max:300'],
            'roles.*.capabilities' => ['required', 'array', 'list'],
            'roles.*.capabilities.*' => ['string', 'max:120', 'regex:'.CapabilityDefinition::KEY_PATTERN],
        ];
    }

    /**
     * Rules the validator cannot check: own capabilities start with "<slug>.", so two plugins never clash.
     *
     * @param  array<mixed>  $data
     * @return list<string>
     */
    public static function problems(array $data, string $slug): array
    {
        $problems = [];

        foreach ((array) ($data['capabilities'] ?? []) as $capability) {
            $key = is_array($capability) ? ($capability['key'] ?? null) : null;

            if (is_string($key) && ! str_starts_with($key, $slug.'.')) {
                $problems[] = __('The capability ":key" must start with ":slug.".', ['key' => $key, 'slug' => $slug]);
            }
        }

        // Admin links may use core capabilities, their own ones, or ones another plugin already declared.
        $own = array_column(array_filter((array) ($data['capabilities'] ?? []), 'is_array'), 'key');
        // The registry is only asked once it exists: plugins are often read while it is being built.
        $registry = app()->resolved(CapabilityRegistry::class) ? app(CapabilityRegistry::class) : null;
        $items = is_array($data['admin'] ?? null) ? (array) ($data['admin']['items'] ?? []) : [];

        foreach ($items as $index => $item) {
            $capability = is_array($item) ? ($item['capability'] ?? null) : null;

            if (is_string($capability) && ! in_array($capability, $own, true) && Capability::tryFrom($capability) === null && ! ($registry?->has($capability) ?? false)) {
                $problems[] = __('The admin.items.:index.capability ":capability" is not a known capability.', ['index' => $index, 'capability' => $capability]);
            }
        }

        return $problems;
    }

    /**
     * Register what a plugin or theme declares. $source is like "plugin:forms" or "theme:ashgrove".
     *
     * @param  list<array<string, mixed>>  $capabilities
     * @param  list<array<string, mixed>>  $roles
     */
    public function register(string $source, string $group, array $capabilities, array $roles): void
    {
        foreach ($capabilities as $data) {
            $this->capabilities->register(new CapabilityDefinition(
                key: (string) $data['key'],
                label: (string) $data['label'],
                description: (string) ($data['description'] ?? ''),
                group: (string) ($data['group'] ?? $group),
                risk: RiskLevel::tryFrom((string) ($data['risk'] ?? '')) ?? RiskLevel::Medium,
                source: $source,
                section: isset($data['section']) ? (string) $data['section'] : null,
                implies: array_values(array_map('strval', (array) ($data['implies'] ?? []))),
                roles: array_values(array_map('strval', (array) ($data['roles'] ?? []))),
            ));
        }

        foreach ($roles as $data) {
            $this->roles->register(new RoleDefinition(
                slug: (string) $data['slug'],
                name: (string) $data['name'],
                description: (string) ($data['description'] ?? ''),
                capabilities: array_values(array_unique(array_map('strval', (array) $data['capabilities']))),
                source: $source,
                builtIn: true,
            ));
        }
    }

    // Capabilities and roles from theme.json. A broken part is logged, the site keeps working.
    public function registerTheme(Theme $theme): void
    {
        try {
            $manifest = Theme::readManifest($theme->path);

            if (! isset($manifest['capabilities']) && ! isset($manifest['roles'])) {
                return;
            }

            $errors = Validator::make($manifest, self::rules())->errors()->all();

            if ($errors !== []) {
                throw new InvalidArgumentException(implode(' ', $errors));
            }

            $this->register(
                'theme:'.$theme->slug,
                $theme->name,
                array_values(array_filter((array) ($manifest['capabilities'] ?? []), 'is_array')),
                array_values(array_filter((array) ($manifest['roles'] ?? []), 'is_array')),
            );
        } catch (Throwable $exception) {
            Log::warning("The capabilities of the theme \"{$theme->slug}\" could not be read.", ['exception' => $exception]);
        }
    }
}
