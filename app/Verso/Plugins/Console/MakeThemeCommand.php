<?php

namespace App\Verso\Plugins\Console;

use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Str;

// Creates a new theme folder with theme.json and the required Blade views, or a child theme with --parent.
class MakeThemeCommand extends StubCommand
{
    // Files of a child theme, mapped to their stub. Views come from the parent.
    protected const CHILD_FILES = [
        'theme.json' => 'theme.child-manifest',
        'assets/theme.css' => 'theme.child-css',
        'README.md' => 'theme.child-readme',
    ];

    // Views every theme must have, mapped to their stub.
    protected const VIEWS = [
        'layouts/app' => 'theme.layout',
        'home' => 'theme.home',
        'entry' => 'theme.entry',
        'archive' => 'theme.archive',
        'taxonomy' => 'theme.taxonomy',
        'search' => 'theme.search',
        '404' => 'theme.404',
    ];

    protected $signature = 'verso:make-theme
        {name : Name of the theme, like "Harbor"}
        {--description= : One line about the theme}
        {--author= : Author of the theme}
        {--parent= : Slug of an installed theme to make a child theme of}
        {--force : Replace files that already exist}';

    protected $description = 'Create a new Verso theme from stubs';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $slug = Str::slug($name);

        if ($slug === '') {
            $this->components->error('Give the theme a name with letters or numbers.');

            return self::FAILURE;
        }

        $themesPath = rtrim((string) config('verso.generators.themes_path'), DIRECTORY_SEPARATOR);
        $folder = $themesPath.DIRECTORY_SEPARATOR.$slug;
        $replacements = [
            'name' => $name,
            'slug' => $slug,
            'description' => (string) ($this->option('description') ?? "The {$name} theme for Verso."),
            'author' => (string) ($this->option('author') ?? ''),
        ];

        if ($this->option('parent') !== null) {
            return $this->makeChild($themesPath, $folder, $replacements);
        }

        if (! $this->writeFile($folder.'/theme.json', $this->renderStub('theme.manifest', $replacements))) {
            return self::FAILURE;
        }

        foreach (self::VIEWS as $view => $stub) {
            if (! $this->writeFile($folder."/views/{$view}.blade.php", $this->renderStub($stub, $replacements))) {
                return self::FAILURE;
            }
        }

        $this->components->info("Theme {$name} created in ".$this->relative($folder).'.');

        return self::SUCCESS;
    }

    /**
     * Write a child theme: theme.json with the parent, a stylesheet and a short README.
     *
     * @param  array<string, string>  $replacements
     */
    protected function makeChild(string $themesPath, string $folder, array $replacements): int
    {
        $parent = trim((string) $this->option('parent'));

        if (preg_match(ThemeManager::SLUG_PATTERN, $parent) !== 1 || $parent === $replacements['slug']) {
            $this->components->error('Give --parent the slug of another theme, like "default".');

            return self::FAILURE;
        }

        if (! $this->parentExists($themesPath, $parent)) {
            $this->components->error("The parent theme [{$parent}] was not found.");

            return self::FAILURE;
        }

        $replacements['parent'] = $parent;

        if ($this->option('description') === null) {
            $replacements['description'] = "A child theme of {$parent}.";
        }

        foreach (self::CHILD_FILES as $file => $stub) {
            if (! $this->writeFile($folder.'/'.$file, $this->renderStub($stub, $replacements))) {
                return self::FAILURE;
            }
        }

        $this->components->info("Child theme {$replacements['name']} of {$parent} created in ".$this->relative($folder).'.');

        return self::SUCCESS;
    }

    // The parent is a folder next to the new theme, or an installed theme.
    protected function parentExists(string $themesPath, string $parent): bool
    {
        return $this->files->exists($themesPath.'/'.$parent.'/theme.json')
            || app(ThemeManager::class)->exists($parent);
    }
}
