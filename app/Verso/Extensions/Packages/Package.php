<?php

namespace App\Verso\Extensions\Packages;

use App\Verso\Extensions\Enums\ExtensionKind;

// A checked plugin or theme zip: its manifest data and the files it will unpack.
final readonly class Package
{
    /**
     * @param  list<string>  $permissions
     * @param  array<string, int>  $files  path inside the package => entry index in the zip
     * @param  list<string>  $network  hosts a plugin declares it connects to
     */
    public function __construct(
        public ExtensionKind $kind,
        public string $slug,
        public string $name,
        public string $version,
        public string $description,
        public string $author,
        public string $requires,
        public array $permissions,
        public array $files,
        public int $unpackedBytes,
        public array $network = [],
    ) {}

    /**
     * Data for the admin consent screen.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'slug' => $this->slug,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'requires' => $this->requires,
            'permissions' => $this->permissions,
            'network' => $this->network,
            'fileCount' => count($this->files),
            'unpackedBytes' => $this->unpackedBytes,
        ];
    }
}
