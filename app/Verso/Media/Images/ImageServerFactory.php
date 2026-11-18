<?php

namespace App\Verso\Media\Images;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Glide\Server;
use League\Glide\ServerFactory;

// Builds the Glide server that resizes images from the media disk.
class ImageServerFactory
{
    /**
     * @param  array<string, mixed>  $config  the verso.media.images config
     */
    public function __construct(
        protected FilesystemFactory $disks,
        protected string $sourceDisk,
        protected array $config,
    ) {}

    public function make(): Server
    {
        $server = ServerFactory::create([
            'source' => $this->disk($this->sourceDisk)->getDriver(),
            'cache' => $this->disk($this->cacheDisk())->getDriver(),
            'cache_path_prefix' => (string) ($this->config['cache_folder'] ?? 'glide-cache'),
            'cache_with_file_extensions' => true,
            'driver' => (string) ($this->config['driver'] ?? 'gd'),
            'max_image_size' => (int) ($this->config['max_image_size'] ?? 0) ?: null,
            'defaults' => [
                'fm' => (string) ($this->config['default_format'] ?? 'webp'),
                'q' => (int) ($this->config['default_quality'] ?? 80),
            ],
        ]);

        return $server;
    }

    public function cacheDisk(): string
    {
        return (string) ($this->config['cache_disk'] ?? 'local');
    }

    // Removes every cached size of one source image.
    public function forget(string $path): void
    {
        $this->make()->deleteCache($path);
    }

    protected function disk(string $name): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = $this->disks->disk($name);

        return $disk;
    }
}
