<?php

namespace App\Verso\I18n\Support;

use Symfony\Component\Finder\Finder;

// Finds translatable strings in code: t() and msg() in TypeScript, __() and friends in PHP and Blade (theme views too),
// UI labels of registries (nav items, settings, stats) and plugin and theme manifests.
class StringExtractor
{
    // t('...'), msg('...') and the same with double quotes or a template without ${}.
    protected const JS_CALL = '/(?<![\w.$])(?:t|msg)\(\s*(?:\'((?:[^\'\\\\\n]|\\\\.)*)\'|"((?:[^"\\\\\n]|\\\\.)*)"|`((?:[^`\\\\$]|\\\\.)*)`)\s*[,)]/s';

    // __('...'), trans(), trans_choice(), @lang(), Lang::get() and Translate::text().
    protected const PHP_CALL = '/(?<![\w>$])(?:__|trans|trans_choice|@lang|Lang::get|Lang::choice|Translate::text|Translate::icu)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\$]|\\\\.)*)")/s';

    // Labels of registries that are translated when they are shown: label: '...', 'label' => '...'.
    protected const PHP_LABEL = '/(?<![\w$>])\'?(label|pluralLabel|singularLabel|description|title|summary|section|hint|help|placeholder|message|group_label|empty|emptyText|action|sample)\'?\s*(?::|=>)\s*\'((?:[^\'\\\\]|\\\\.)+)\'/';

    // Positional labels: new ConfigParameter('key', $group, 'Label', 'Description' ...) and new ConfigGroup(key, 'Label', 'Description' ...).
    protected const PHP_POSITIONAL = '/new\s+(?:ConfigParameter\(\s*\'[^\']*\'\s*,\s*[^,]+|ConfigGroup\(\s*[^,]+)\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'/';

    // Lists of words or steps, like keywords: [...] and 'steps' => [...].
    protected const PHP_KEYWORDS = '/\'?(?:keywords|steps)\'?\s*(?::|=>)\s*\[([^\]]*)\]/';

    // Maps of name => label, like the menu locations in config/verso.php.
    protected const PHP_LABEL_MAP = '/\'locations\'\s*=>\s*\[([^\]]*)\]/';

    // Sample site content between these lines is written in the site language, so its labels are not admin text.
    protected const PHP_CONTENT_BLOCK = '#//\s*i18n-ignore-start.*?//\s*i18n-ignore-end#s';

    // Keys of Laravel PHP lang files, like "validation.required", are not JSON keys.
    protected const GROUP_KEY = '/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/i';

    public function __construct(protected string $basePath) {}

    /**
     * Every key found, with the files it was found in.
     *
     * @return array<string, list<string>>
     */
    public function extract(): array
    {
        $keys = [];

        foreach ($this->jsFiles() as $path) {
            // TypeScript keys name their prefix themselves: t('shop::Orders') or the core t('Save').
            $this->collect($keys, $path, null, $this->fromJavaScript((string) file_get_contents($path)));
        }

        foreach ($this->phpFiles() as $path) {
            $contents = (string) file_get_contents($path);
            $namespace = $this->namespaceFor($path);
            $this->collect($keys, $path, null, $this->fromPhpCalls($contents));
            $this->collect($keys, $path, $namespace, $this->fromPhpLabels($contents));
        }

        foreach ($this->manifests() as $path => $namespace) {
            $this->collect($keys, $path, $namespace, $this->fromManifest($path));
        }

        ksort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @return list<string>
     */
    public function fromJavaScript(string $code): array
    {
        preg_match_all(self::JS_CALL, $code, $matches, PREG_SET_ORDER);
        $keys = [];

        foreach ($matches as $match) {
            $raw = ($match[1] ?? '') !== '' ? $match[1] : (($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? ''));
            $keys[] = $this->unescapeJs($raw);
        }

        return $this->clean($keys);
    }

    /**
     * @return list<string>
     */
    public function fromPhpCalls(string $code): array
    {
        preg_match_all(self::PHP_CALL, $code, $matches, PREG_SET_ORDER);
        $keys = [];

        foreach ($matches as $match) {
            $keys[] = ($match[1] ?? '') !== '' ? $this->unescapePhp($match[1]) : stripcslashes($match[2] ?? '');
        }

        return $this->clean($keys);
    }

    /**
     * @return list<string>
     */
    public function fromPhpLabels(string $code): array
    {
        $keys = [];
        $code = (string) preg_replace(self::PHP_CONTENT_BLOCK, '', $code);

        preg_match_all(self::PHP_LABEL, $code, $labels, PREG_SET_ORDER);

        foreach ($labels as $match) {
            if ($this->looksLikeText($text = $this->unescapePhp($match[2]))) {
                $keys[] = $text;
            }
        }

        preg_match_all(self::PHP_POSITIONAL, $code, $positional, PREG_SET_ORDER);

        foreach ($positional as $match) {
            $keys[] = $this->unescapePhp($match[1]);
            $keys[] = $this->unescapePhp($match[2]);
        }

        preg_match_all(self::PHP_KEYWORDS, $code, $keywords, PREG_SET_ORDER);

        foreach ($keywords as $match) {
            preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'/', $match[1], $words);

            foreach ($words[1] as $word) {
                $keys[] = $this->unescapePhp($word);
            }
        }

        preg_match_all(self::PHP_LABEL_MAP, $code, $maps, PREG_SET_ORDER);

        foreach ($maps as $match) {
            preg_match_all('/=>\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $match[1], $values);

            foreach ($values[1] as $value) {
                if ($this->looksLikeText($text = $this->unescapePhp($value))) {
                    $keys[] = $text;
                }
            }
        }

        return $this->clean($keys);
    }

    /**
     * Name, description, admin labels and keywords of a plugin, and labels of a theme.
     *
     * @return list<string>
     */
    public function fromManifest(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return [];
        }

        $keys = [(string) ($data['name'] ?? ''), (string) ($data['description'] ?? '')];

        array_walk_recursive($data, function (mixed $value, int|string $key) use (&$keys): void {
            if (in_array($key, ['label', 'help', 'description'], true) && is_string($value)) {
                $keys[] = $value;
            }
        });

        foreach ($this->manifestKeywords($data) as $keyword) {
            $keys[] = $keyword;
        }

        // Theme menu locations (name => label) and block patterns (title, category).
        foreach ((array) ($data['menus'] ?? []) as $label) {
            if (is_string($label)) {
                $keys[] = $label;
            }
        }

        foreach (['title', 'category'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $keys[] = $data[$field];
            }
        }

        // Slugs like "call-to-action" are names, not text.
        return $this->clean(array_filter($keys, fn (string $key): bool => preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key) !== 1 || ! str_contains($key, '-')));
    }

    // "shop" for files of the shop plugin (its folder, its admin pages and components), null for core.
    public function namespaceFor(string $path): ?string
    {
        $relative = str_replace('\\', '/', ltrim(substr($path, strlen($this->basePath)), '/\\'));

        if (preg_match('#^(?:plugins|themes)/([a-z0-9-]+)/#', $relative, $match)) {
            return $match[1];
        }

        if (preg_match('#^resources/js/pages/admin/plugins/([a-z0-9-]+)/#', $relative, $match)) {
            return $match[1];
        }

        if (preg_match('#^resources/js/components/verso/([a-z0-9-]+)/#', $relative, $match) && is_dir($this->basePath.'/plugins/'.$match[1])) {
            return $match[1];
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $keys
     * @param  list<string>  $found
     */
    protected function collect(array &$keys, string $path, ?string $namespace, array $found): void
    {
        $relative = ltrim(substr($path, strlen($this->basePath)), '/\\');

        foreach ($found as $key) {
            if ($namespace !== null && ! str_contains($key, '::')) {
                $key = $namespace.'::'.$key;
            }

            $keys[$key] ??= [];

            if (! in_array($relative, $keys[$key], true)) {
                $keys[$key][] = $relative;
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return list<string>
     */
    protected function manifestKeywords(array $data): array
    {
        $words = [];
        $admin = is_array($data['admin'] ?? null) ? $data['admin'] : [];

        foreach ([$admin, ...array_values(is_array($admin['items'] ?? null) ? $admin['items'] : [])] as $part) {
            if (is_array($part) && is_array($part['keywords'] ?? null)) {
                foreach ($part['keywords'] as $word) {
                    if (is_string($word)) {
                        $words[] = $word;
                    }
                }
            }
        }

        return $words;
    }

    /**
     * @param  array<int, string>  $keys
     * @return list<string>
     */
    protected function clean(array $keys): array
    {
        return array_values(array_unique(array_filter(
            $keys,
            fn (string $key): bool => preg_match('/\p{L}/u', $key) === 1 && ! preg_match(self::GROUP_KEY, $key),
        )));
    }

    // Skips code-like values (keys, routes, CSS classes, icons) that the label pattern can catch.
    protected function looksLikeText(string $value): bool
    {
        if (! preg_match('/\p{Lu}/u', mb_substr($value, 0, 1)) && ! str_contains($value, ' ')) {
            return false;
        }

        return ! preg_match('/^(https?:|\/|#|--|[a-z0-9_.-]+$)/', $value) && (str_contains($value, ' ') || ! preg_match('/^(admin|verso)/', $value));
    }

    protected function unescapeJs(string $raw): string
    {
        return (string) preg_replace_callback('/\\\\(u\{[0-9a-fA-F]+\}|u[0-9a-fA-F]{4}|x[0-9a-fA-F]{2}|.)/s', function (array $match): string {
            $code = $match[1];

            return match (true) {
                $code === 'n' => "\n",
                $code === 't' => "\t",
                str_starts_with($code, 'u{') => mb_chr((int) hexdec(substr($code, 2, -1))),
                str_starts_with($code, 'u') && strlen($code) === 5 => mb_chr((int) hexdec(substr($code, 1))),
                str_starts_with($code, 'x') && strlen($code) === 3 => mb_chr((int) hexdec(substr($code, 1))),
                default => $code,
            };
        }, $raw);
    }

    protected function unescapePhp(string $raw): string
    {
        return str_replace(["\\'", '\\\\'], ["'", '\\'], $raw);
    }

    /**
     * @return iterable<string>
     */
    protected function jsFiles(): iterable
    {
        $folders = array_filter([$this->basePath.'/resources/js', ...glob($this->basePath.'/plugins/*/resources/js') ?: []], is_dir(...));

        $finder = Finder::create()->files()->in($folders)->name(['*.ts', '*.tsx'])
            ->notPath(['#(^|/)(tests|actions|routes|wayfinder)/#'])
            ->notName(['*.test.ts', '*.test.tsx', '*.d.ts']);

        foreach ($finder as $file) {
            yield $file->getPathname();
        }
    }

    /**
     * @return iterable<string>
     */
    protected function phpFiles(): iterable
    {
        $folders = array_filter([
            $this->basePath.'/app',
            $this->basePath.'/config',
            $this->basePath.'/routes',
            $this->basePath.'/resources/views',
            ...glob($this->basePath.'/plugins/*/src') ?: [],
            ...glob($this->basePath.'/plugins/*/config') ?: [],
            ...glob($this->basePath.'/plugins/*/resources/views') ?: [],
            ...glob($this->basePath.'/plugins/*/routes') ?: [],
            ...glob($this->basePath.'/themes/*/views') ?: [],
        ], is_dir(...));

        foreach (Finder::create()->files()->in($folders)->name('*.php') as $file) {
            yield $file->getPathname();
        }
    }

    /**
     * Plugin and theme manifests with their namespace.
     *
     * @return array<string, string>
     */
    protected function manifests(): array
    {
        $manifests = [];

        foreach ([...glob($this->basePath.'/plugins/*/verso.json') ?: [], ...glob($this->basePath.'/themes/*/theme.json') ?: []] as $path) {
            $manifests[$path] = basename(dirname($path));
        }

        foreach (glob($this->basePath.'/themes/*/patterns/*.json') ?: [] as $path) {
            $manifests[$path] = basename(dirname($path, 2));
        }

        return $manifests;
    }
}
