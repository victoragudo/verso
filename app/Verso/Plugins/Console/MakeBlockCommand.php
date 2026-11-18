<?php

namespace App\Verso\Plugins\Console;

use App\Verso\Plugins\PluginManager;
use Illuminate\Support\Str;

// Creates a block class, inside a plugin or in the app.
class MakeBlockCommand extends StubCommand
{
    protected $signature = 'verso:make-block
        {name : Name of the block, like "Pull Quote"}
        {--plugin= : Slug of the plugin that gets the block}
        {--force : Replace the file when it already exists}';

    protected $description = 'Create a new Verso block class from a stub';

    public function handle(PluginManager $plugins): int
    {
        $name = trim((string) $this->argument('name'));
        $type = Str::slug($name);

        if ($type === '') {
            $this->components->error('Give the block a name with letters or numbers.');

            return self::FAILURE;
        }

        $class = Str::studly($type).'Block';
        $target = $this->target($plugins);

        if ($target === null) {
            return self::FAILURE;
        }

        [$folder, $namespace] = $target;

        $written = $this->writeFile($folder.DIRECTORY_SEPARATOR.$class.'.php', $this->renderStub('block', [
            'namespace' => $namespace,
            'class' => $class,
            'type' => $type,
            'label' => Str::headline($name),
        ]));

        if (! $written) {
            return self::FAILURE;
        }

        $this->components->info("Register it in a plugin provider with \$this->registerBlock(new {$class});");

        return self::SUCCESS;
    }

    /**
     * The folder and namespace for the new class, or null when the plugin is unknown.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function target(PluginManager $plugins): ?array
    {
        $slug = $this->option('plugin');

        if (! is_string($slug) || $slug === '') {
            return [rtrim((string) config('verso.generators.blocks_path'), DIRECTORY_SEPARATOR), trim((string) config('verso.generators.blocks_namespace'), '\\')];
        }

        $plugin = $plugins->find($slug);
        $autoload = $plugin?->manifest->autoload ?? [];

        if ($plugin === null || $autoload === []) {
            $this->components->error("The plugin \"{$slug}\" was not found or has no autoload.psr-4 entry.");

            return null;
        }

        $namespace = (string) array_key_first($autoload);
        $folder = $plugin->path.DIRECTORY_SEPARATOR.trim($autoload[$namespace], '/\\').DIRECTORY_SEPARATOR.'Blocks';

        return [$folder, trim($namespace, '\\').'\\Blocks'];
    }
}
