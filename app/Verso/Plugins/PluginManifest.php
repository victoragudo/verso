<?php

namespace App\Verso\Plugins;

use App\Verso\Access\Capabilities\ManifestAccess;
use App\Verso\Core\Enums\Capability;
use App\Verso\Plugins\Admin\PluginAdminMenu;
use App\Verso\Plugins\Exceptions\InvalidManifest;
use App\Verso\Plugins\Support\VersionConstraint;
use Illuminate\Support\Facades\Validator;
use JsonException;

// The data of a verso.json file, checked and typed.
final readonly class PluginManifest
{
    // Slugs are lowercase words joined by dashes, like "reading-time".
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    // Network hosts are domain names, optionally starting with "*." to allow every sub domain.
    public const HOST_PATTERN = '/^(\*\.)?[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)*$/';

    // Versions follow semantic versioning, like "1.4.0" or "2.0.0-beta.1".
    public const VERSION_PATTERN = '/^\d+\.\d+\.\d+([-+][0-9A-Za-z.-]+)?$/';

    /**
     * @param  list<Capability>  $permissions
     * @param  array<string, string>  $autoload  PSR-4 namespace => folder inside the plugin
     * @param  list<string>  $network  hosts the plugin may connect to, like "api.stripe.com" or "*.example.org"
     * @param  list<array<string, mixed>>  $capabilities  capabilities the plugin declares (checked by ManifestAccess::rules())
     * @param  list<array<string, mixed>>  $roles  roles the plugin declares
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $version,
        public string $description,
        public string $provider,
        public string $requires,
        public array $permissions,
        public array $autoload,
        public string $path,
        public array $network = [],
        public ?PluginAdminMenu $admin = null,
        public array $capabilities = [],
        public array $roles = [],
    ) {}

    // Read and check the manifest file inside a plugin folder.
    public static function fromFile(string $file, bool $matchFolder = true): self
    {
        return self::fromArray(self::decode($file), dirname($file), $matchFolder);
    }

    /**
     * The decoded manifest file. Throws when it is missing, not JSON or not an object.
     *
     * @return array<mixed>
     */
    public static function decode(string $file): array
    {
        if (! is_file($file)) {
            throw new InvalidManifest($file, [__('The manifest file does not exist.')]);
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidManifest($file, [__('The manifest is not valid JSON (:error).', ['error' => $exception->getMessage()])]);
        }

        if (! is_array($data)) {
            throw new InvalidManifest($file, [__('The manifest must be a JSON object.')]);
        }

        return $data;
    }

    /**
     * Check raw manifest data. The plugin folder name must match the slug unless $matchFolder is false.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data, string $path, bool $matchFolder = true): self
    {
        $errors = self::validate($data, $path, $matchFolder);

        if ($errors !== []) {
            throw new InvalidManifest($path, $errors);
        }

        /** @var array<string, string> $psr4 */
        $psr4 = (array) ($data['autoload']['psr-4'] ?? []);

        return new self(
            name: (string) $data['name'],
            slug: (string) $data['slug'],
            version: (string) $data['version'],
            description: (string) ($data['description'] ?? ''),
            provider: ltrim((string) $data['provider'], '\\'),
            requires: (string) ($data['requires'] ?? '*'),
            permissions: array_values(array_map(
                fn (mixed $value): Capability => Capability::from((string) $value),
                array_unique((array) ($data['permissions'] ?? [])),
            )),
            autoload: $psr4,
            path: rtrim($path, DIRECTORY_SEPARATOR),
            network: array_values(array_unique(array_map(
                fn (mixed $host): string => strtolower((string) $host),
                (array) ($data['network'] ?? []),
            ))),
            admin: is_array($data['admin'] ?? null) ? PluginAdminMenu::fromArray($data['admin']) : null,
            capabilities: array_values(array_filter((array) ($data['capabilities'] ?? []), 'is_array')),
            roles: array_values(array_filter((array) ($data['roles'] ?? []), 'is_array')),
        );
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    protected static function validate(array $data, string $path, bool $matchFolder): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:'.self::SLUG_PATTERN],
            'version' => ['required', 'string', 'regex:'.self::VERSION_PATTERN],
            'description' => ['nullable', 'string', 'max:500'],
            'provider' => ['required', 'string', 'regex:/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/'],
            'requires' => ['nullable', 'string', 'max:60'],
            'permissions' => ['nullable', 'array', 'list'],
            'permissions.*' => ['string', 'in:'.implode(',', array_column(Capability::cases(), 'value'))],
            'network' => ['nullable', 'array', 'list'],
            'network.*' => ['string', 'max:253', 'regex:'.self::HOST_PATTERN],
            'autoload' => ['nullable', 'array'],
            'autoload.psr-4' => ['nullable', 'array'],
            ...PluginAdminMenu::rules(),
            ...ManifestAccess::rules(),
        ]);

        /** @var list<string> $errors */
        $errors = array_values($validator->errors()->all());

        if (is_string($data['requires'] ?? null) && ! VersionConstraint::isValid($data['requires'])) {
            $errors[] = __('The requires field is not a valid version constraint.');
        }

        foreach ((array) ($data['autoload']['psr-4'] ?? []) as $namespace => $folder) {
            if (! is_string($namespace) || ! str_ends_with($namespace, '\\') || ! is_string($folder) || str_contains($folder, '..')) {
                $errors[] = __('Each autoload.psr-4 entry must map a namespace ending in "\\" to a folder inside the plugin.');
                break;
            }
        }

        if (is_string($data['slug'] ?? null)) {
            array_push($errors, ...ManifestAccess::problems($data, $data['slug']));
        }

        if ($matchFolder && is_string($data['slug'] ?? null) && basename($path) !== $data['slug']) {
            $errors[] = __('The slug must match the plugin folder name ":folder".', ['folder' => basename($path)]);
        }

        return $errors;
    }

    // True when the plugin works with the given Verso version.
    public function supports(string $versoVersion): bool
    {
        return VersionConstraint::satisfies($versoVersion, $this->requires);
    }

    /**
     * Data for the admin UI.
     *
     * @return array{name: string, slug: string, version: string, description: string, requires: string, permissions: list<string>, network: list<string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'version' => $this->version,
            'description' => $this->description,
            'requires' => $this->requires,
            'permissions' => array_map(fn (Capability $capability): string => $capability->value, $this->permissions),
            'network' => $this->network,
        ];
    }
}
