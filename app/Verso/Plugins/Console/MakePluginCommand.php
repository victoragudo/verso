<?php

namespace App\Verso\Plugins\Console;

use App\Verso\Plugins\PluginManifest;
use Illuminate\Support\Str;

// Creates a new plugin folder with a manifest, a service provider, an admin script and a readme.
class MakePluginCommand extends StubCommand
{
    protected $signature = 'verso:make-plugin
        {name : Name of the plugin, like "Reading Time"}
        {--description= : One line about what the plugin does}
        {--author= : Author of the plugin}
        {--force : Replace files that already exist}';

    protected $description = 'Create a new Verso plugin from stubs';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $slug = Str::slug($name);

        if ($slug === '' || preg_match(PluginManifest::SLUG_PATTERN, $slug) !== 1) {
            $this->components->error('Give the plugin a name with letters or numbers.');

            return self::FAILURE;
        }

        $class = Str::studly($slug);
        $namespace = 'Verso\\Plugins\\'.$class;
        $folder = $this->pluginsPath().DIRECTORY_SEPARATOR.$slug;
        $replacements = [
            'name' => $name,
            'slug' => $slug,
            'class' => $class,
            'namespace' => $namespace,
            'namespace_json' => str_replace('\\', '\\\\', $namespace),
            'description' => (string) ($this->option('description') ?? "Adds {$name} to Verso."),
            'author' => (string) ($this->option('author') ?? ''),
            'requires' => '^'.implode('.', array_slice(explode('.', (string) config('verso.version')), 0, 2)),
            'admin_prefix' => trim((string) config('verso.admin.prefix'), '/'),
        ];

        $written = $this->writeFile($folder.'/verso.json', $this->renderStub('plugin.manifest', $replacements))
            && $this->writeFile($folder."/src/{$class}ServiceProvider.php", $this->renderStub('plugin.provider', $replacements))
            && $this->writeFile($folder.'/routes/admin.php', $this->renderStub('plugin.routes', $replacements))
            && $this->writeFile($folder.'/resources/js/index.tsx', $this->renderStub('plugin.js', $replacements))
            && $this->writeFile($folder.'/README.md', $this->renderStub('plugin.readme', $replacements));

        if (! $written) {
            return self::FAILURE;
        }

        $this->components->info("Plugin {$name} created. Enable it in the admin under Administration > Plugins and themes.");

        return self::SUCCESS;
    }

    // The first folder in verso.plugins.paths.
    protected function pluginsPath(): string
    {
        $paths = (array) config('verso.plugins.paths');

        return rtrim((string) ($paths[0] ?? base_path('plugins')), DIRECTORY_SEPARATOR);
    }
}
