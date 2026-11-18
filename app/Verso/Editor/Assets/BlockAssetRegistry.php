<?php

namespace App\Verso\Editor\Assets;

use InvalidArgumentException;

// CSS and JavaScript files that some blocks need on the public site, like the tabs script.
// A page only loads the files of the blocks it shows. Core blocks, plugins and themes register here.
class BlockAssetRegistry
{
    // Asset keys: lowercase letters, digits and dashes.
    public const string KEY_PATTERN = '/^[a-z0-9][a-z0-9-]{0,60}$/';

    // File kinds an asset can have.
    public const array KINDS = ['css', 'js', 'json'];

    /** @var array<string, array<string, string>> key => kind => file path */
    protected array $files = [];

    /** @var array<string, list<string>> block type => asset keys */
    protected array $blocks = [];

    /** @var array<string, string> "key.kind" => short hash of the file, for the URL */
    protected array $versions = [];

    /**
     * Adds the files of one asset and the block types that need it.
     *
     * @param  list<string>  $blocks
     */
    public function register(string $key, ?string $css = null, ?string $js = null, array $blocks = [], ?string $json = null): void
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("The block asset key [{$key}] is not valid.");
        }

        $this->files[$key] = array_filter(['css' => $css, 'js' => $js, 'json' => $json], fn (?string $path): bool => $path !== null);

        foreach ($blocks as $block) {
            $this->blocks[$block] = array_values(array_unique([...$this->blocks[$block] ?? [], $key]));
        }
    }

    // The asset keys a block type needs.
    /** @return list<string> */
    public function forBlock(string $type): array
    {
        return $this->blocks[$type] ?? [];
    }

    // The file of an asset, or null when it has none of that kind.
    public function file(string $key, string $kind): ?string
    {
        $path = $this->files[$key][$kind] ?? null;

        return $path !== null && is_file($path) ? $path : null;
    }

    // The public address of an asset file, with a version so browsers keep it for a long time.
    public function url(string $key, string $kind): ?string
    {
        $file = $this->file($key, $kind);

        if ($file === null) {
            return null;
        }

        $this->versions[$key.'.'.$kind] ??= substr(hash('xxh128', (string) filemtime($file).'|'.(string) filesize($file).'|'.$file), 0, 10);

        return route('verso.block-asset', ['asset' => $key.'.'.$kind, 'v' => $this->versions[$key.'.'.$kind]]);
    }

    /**
     * The tags of some assets: stylesheets go before the blocks, scripts after them.
     *
     * @param  list<string>  $keys
     * @return array{before: string, after: string}
     */
    public function tags(array $keys): array
    {
        $before = '';
        $after = '';

        foreach ($keys as $key) {
            $css = $this->url($key, 'css');
            $js = $this->url($key, 'js');
            $before .= $css === null ? '' : '<link rel="stylesheet" href="'.e($css).'" data-verso-asset="'.e($key).'">';
            $after .= $js === null ? '' : '<script type="module" src="'.e($js).'" data-verso-asset="'.e($key).'"></script>';
        }

        return ['before' => $before, 'after' => $after];
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->files);
    }
}
