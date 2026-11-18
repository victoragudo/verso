<?php

namespace App\Verso\Site\Parts;

use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Customizer\ThemeSettingType;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\Templates\TemplateParts;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

// "Create child theme from these changes": writes themes/<slug> with the active theme as parent.
// Published Site Editor parts become parts/<key>.json with a small Blade part that renders them,
// and the colors, fonts and other customizer values become the defaults in theme.json.
// The header and footer styles in use are saved as the choices of the new theme.
class ChildThemeFromChanges
{
    // Version needs (Verso and PHP) copied from the parent manifest when it has them.
    protected const VERSION_KEYS = ['requires', 'php'];

    // Screenshot files looked for in the parent, in this order.
    protected const SCREENSHOTS = ['screenshot.png', 'screenshot.webp', 'screenshot.jpg'];

    public function __construct(
        protected ThemeManager $themes,
        protected SitePartStore $store,
        protected ThemeCustomizer $customizer,
        protected GlobalStyles $styles,
        protected PartVariants $variants,
        protected Filesystem $files,
    ) {}

    // True when a theme folder with this slug could be written.
    public function slugProblem(string $slug): ?string
    {
        if (preg_match(ThemeManager::SLUG_PATTERN, $slug) !== 1) {
            return 'Use lowercase letters, numbers and dashes for the folder name.';
        }

        if ($this->files->exists($this->folder($slug)) || $this->themes->exists($slug)) {
            return 'A theme with this folder name already exists.';
        }

        if (! $this->files->isWritable($this->themes->basePath())) {
            return 'The themes folder cannot be written. Check its permissions.';
        }

        return null;
    }

    /**
     * Writes the child theme and returns it. Nothing is left behind when a step fails.
     *
     * @throws InvalidArgumentException when the slug cannot be used
     */
    public function handle(Theme $parent, string $name, string $slug, string $author = ''): Theme
    {
        $problem = $this->slugProblem($slug);

        if ($problem !== null) {
            throw new InvalidArgumentException($problem);
        }

        $folder = $this->folder($slug);

        try {
            $this->files->makeDirectory($folder.'/views/parts', 0755, true);
            $parts = $this->writeParts($parent, $folder);
            $this->writeJson($folder.'/theme.json', $this->manifest($parent, $name, $slug, $author));
            $this->writeStylesheet($parent, $folder);
            $this->copyScreenshot($parent, $folder);
            $this->files->put($folder.'/README.md', $this->readme($parent, $name, $parts));
        } catch (\Throwable $exception) {
            $this->files->deleteDirectory($folder);

            throw $exception;
        }

        $this->themes->refresh();
        $child = $this->themes->find($slug) ?? throw new InvalidArgumentException('The new theme could not be read.');

        // The styles in use become the choices of the new theme.
        foreach ($this->variants->choices($parent) as $part => $style) {
            $this->variants->choose($child, $part, $style);
        }

        return $child;
    }

    /**
     * The theme.json of the child theme.
     *
     * @return array<string, mixed>
     */
    public function manifest(Theme $parent, string $name, string $slug, string $author = ''): array
    {
        $parentManifest = Theme::readManifest($parent->path);
        $manifest = [
            'name' => $name,
            'slug' => $slug,
            'version' => '1.0.0',
            'description' => "A child theme of {$parent->name} with the Site Editor changes of ".Carbon::now()->toDateString().'.',
            'author' => $author !== '' ? $author : $parent->author,
        ];

        foreach (self::VERSION_KEYS as $key) {
            if (is_string($parentManifest[$key] ?? null)) {
                $manifest[$key] = $parentManifest[$key];
            }
        }

        $manifest['parent'] = $parent->slug;

        $settings = $this->settings($parent);
        $styles = $this->tokenDefaults($parent);

        if ($settings !== []) {
            $manifest['settings'] = $settings;
        }

        if ($styles !== []) {
            $manifest['styles'] = $styles;
        }

        return $manifest;
    }

    /**
     * Customizer fields whose value was changed, with that value as the new default. Media fields are left out.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function settings(Theme $parent): array
    {
        $values = $this->customizer->values($parent);
        $settings = [];

        foreach ($this->customizer->schema($parent)->fields as $key => $field) {
            $config = $parent->settings[$key] ?? null;

            if ($field->type === ThemeSettingType::Media || ! is_array($config) || $values[$key] === $field->default) {
                continue;
            }

            $settings[$key] = [...$config, 'default' => $values[$key]];
        }

        return $settings;
    }

    /**
     * Design tokens without a customizer field whose value was changed, as {"color": {"surface": {"default": "#efe8f7"}}}.
     * The child theme.json is merged with the parent one, so only the default is written.
     *
     * @return array<string, array<string, array{default: string}>>
     */
    protected function tokenDefaults(Theme $parent): array
    {
        $values = $this->styles->values($parent);
        $styles = [];

        foreach ($this->styles->tokens($parent) as $id => $token) {
            if ($token['setting'] === null && $values[$id] !== $token['default']) {
                $styles[$token['group']][$token['key']] = ['default' => $values[$id]];
            }
        }

        return $styles;
    }

    /**
     * Writes each published part as parts/<key>.json plus views/parts/<key>.blade.php.
     *
     * @return list<string> keys of the written parts
     */
    protected function writeParts(Theme $parent, string $folder): array
    {
        $keys = [];

        foreach ($this->store->forTheme($parent->slug) as $key => $sitePart) {
            $blocks = $sitePart->published_blocks;

            if (preg_match(TemplateParts::NAME_PATTERN, $key) !== 1 || ! is_array($blocks) || $blocks === []) {
                continue;
            }

            $part = Str::before($key, '-');
            $this->writeJson($folder.'/'.TemplateParts::BLOCKS_FOLDER.'/'.$key.'.json', ['blocks' => array_values($blocks)]);
            $this->files->put(
                $folder.'/views/parts/'.$key.'.blade.php',
                "{{-- The \"{$key}\" part, built with blocks in the Site Editor. The blocks are in parts/{$key}.json. --}}\n"
                ."<x-verso-part-blocks part=\"{$part}\" key=\"{$key}\" />\n",
            );
            $keys[] = $key;
        }

        return $keys;
    }

    // The changed colors, fonts and sizes as CSS after the parent stylesheet, because theme.json defaults alone print nothing.
    protected function writeStylesheet(Theme $parent, string $folder): void
    {
        $rules = array_filter([$this->customizer->css($parent), $this->styles->css($parent)]);

        $this->files->ensureDirectoryExists($folder.'/assets');
        $this->files->put(
            $folder.'/'.ThemeManager::STYLESHEET,
            "/* {$parent->name} child theme: the colors, fonts and sizes chosen in the Site Editor and the customizer. */\n".implode("\n", $rules)."\n",
        );
    }

    protected function copyScreenshot(Theme $parent, string $folder): void
    {
        foreach (self::SCREENSHOTS as $file) {
            $source = $this->themes->locateFile($parent, $file);

            if ($source !== null) {
                $this->files->copy($source, $folder.'/'.$file);

                return;
            }
        }
    }

    /**
     * @param  list<string>  $parts
     */
    protected function readme(Theme $parent, string $name, array $parts): string
    {
        $list = $parts === [] ? "No parts were edited.\n" : implode('', array_map(fn (string $key): string => "- `{$key}`\n", $parts));

        return "# {$name}\n\nA child theme of {$parent->name}, made with \"Create child theme from these changes\" in the Site Editor.\n\n"
            ."Colors, fonts and other customizer values are the defaults in `theme.json`. Parts built with blocks:\n\n{$list}\n"
            ."Edit a part again in the Site Editor, or change its blocks in `parts/<key>.json`.\n";
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    protected function writeJson(string $file, array $data): void
    {
        $this->files->ensureDirectoryExists(dirname($file));
        $this->files->put($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }

    protected function folder(string $slug): string
    {
        return rtrim($this->themes->basePath(), '/').'/'.$slug;
    }
}
