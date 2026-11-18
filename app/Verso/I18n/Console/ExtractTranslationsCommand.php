<?php

namespace App\Verso\I18n\Console;

use App\Verso\I18n\Locales;
use App\Verso\I18n\Support\CatalogWriter;
use App\Verso\I18n\Support\StringExtractor;
use Illuminate\Console\Command;

// Collects every admin string into lang/en.json (core, plugins, themes) and reports missing translations.
class ExtractTranslationsCommand extends Command
{
    protected $signature = 'verso:lang-extract
        {--check : Fail when en.json is out of date or a language misses keys, without writing}
        {--prune : Remove keys that the code no longer uses from every language}
        {--missing : List the missing keys of each language file}';

    protected $description = 'Extract admin strings into lang/en.json files and check the translations';

    public function handle(Locales $locales): int
    {
        $keys = (new StringExtractor(base_path()))->extract();
        $writer = new CatalogWriter(base_path(), $locales->codes(), $locales->fallback(), (string) config('verso.i18n.extension_lang_path'));
        $catalogs = $writer->catalogs(array_keys($keys));
        $check = (bool) $this->option('check');

        $changed = $writer->writeSource($catalogs, dryRun: $check);
        $this->components->info(count($keys).' keys in '.count($catalogs).' catalogs.');

        foreach ($changed as $path) {
            $this->components->twoColumnDetail($this->relative($path), $check ? '<fg=red>out of date</>' : '<fg=green>written</>');
        }

        if ($this->option('prune') && ! $check) {
            $this->components->info($writer->prune($catalogs).' unused keys removed.');
        }

        $failed = $check && $changed !== [];

        foreach ($writer->compare($catalogs) as $path => $result) {
            if ($result['missing'] === [] && $result['extra'] === []) {
                continue;
            }

            $failed = $failed || ($check && $result['missing'] !== []);
            $this->components->twoColumnDetail($this->relative($path), count($result['missing']).' missing, '.count($result['extra']).' unused');

            if ($this->option('missing')) {
                foreach ($result['missing'] as $key) {
                    $this->line('    '.$key);
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function relative(string $path): string
    {
        return ltrim(substr($path, strlen(base_path())), '/');
    }
}
