<?php

namespace App\Verso\Extensions\Registry;

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Plugins\PluginManifest;
use Illuminate\Support\Facades\Validator;

// One plugin or theme listed in the registry index.
final readonly class RegistryEntry
{
    public function __construct(
        public ExtensionKind $kind,
        public string $name,
        public string $slug,
        public string $version,
        public string $description,
        public string $downloadUrl,
        public string $sha256,
    ) {}

    /**
     * Build an entry from index data. Returns null when the data is not valid.
     *
     * @param  array<mixed>  $data
     * @param  list<string>  $schemes  URL schemes allowed for the download
     */
    public static function fromArray(array $data, array $schemes): ?self
    {
        $validator = Validator::make($data, [
            'type' => ['nullable', 'string', 'in:'.implode(',', array_column(ExtensionKind::cases(), 'value'))],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:'.PluginManifest::SLUG_PATTERN],
            'version' => ['required', 'string', 'regex:'.PluginManifest::VERSION_PATTERN],
            'description' => ['nullable', 'string', 'max:500'],
            'download_url' => ['required', 'string', 'max:2048', 'url:'.implode(',', $schemes)],
            'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        return new self(
            kind: ExtensionKind::from((string) ($data['type'] ?? ExtensionKind::Plugin->value)),
            name: (string) $data['name'],
            slug: (string) $data['slug'],
            version: (string) $data['version'],
            description: (string) ($data['description'] ?? ''),
            downloadUrl: (string) $data['download_url'],
            sha256: strtolower((string) $data['sha256']),
        );
    }

    /**
     * Data for the admin Browse tab.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'name' => $this->name,
            'slug' => $this->slug,
            'version' => $this->version,
            'description' => $this->description,
            'downloadUrl' => $this->downloadUrl,
            'sha256' => $this->sha256,
        ];
    }
}
