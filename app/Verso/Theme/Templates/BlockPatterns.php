<?php

namespace App\Verso\Theme\Templates;

use App\Verso\Editor\BlockRegistry;
use App\Verso\I18n\Translate;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use JsonException;

// Reads the block patterns (patterns/*.json) of the active theme and its parents.
class BlockPatterns
{
    // Folder of pattern files inside a theme.
    public const FOLDER = 'patterns';

    public function __construct(
        protected ThemeManager $themes,
        protected BlockRegistry $blocks,
        protected int $maxFileBytes,
    ) {}

    /**
     * Patterns sorted by category and title. A child theme replaces a parent pattern with the same name.
     * Patterns with unknown block types are left out.
     *
     * @return list<BlockPattern>
     */
    public function all(?Theme $theme = null): array
    {
        $patterns = [];

        foreach (array_reverse($this->themes->lineage($theme ?? $this->themes->active())) as $member) {
            foreach ($this->read($member) as $pattern) {
                $patterns[$pattern->name] = $pattern;
            }
        }

        $patterns = array_values(array_filter($patterns, fn (BlockPattern $pattern): bool => $this->known($pattern)));

        usort($patterns, fn (BlockPattern $a, BlockPattern $b): int => [strtolower($a->category), strtolower($a->title)] <=> [strtolower($b->category), strtolower($b->title)]);

        return $patterns;
    }

    /**
     * @return list<array{name: string, title: string, category: string, description: string, keywords: list<string>, blocks: list<array<string, mixed>>}>
     */
    public function toArray(?Theme $theme = null): array
    {
        return array_map(fn (BlockPattern $pattern): array => $pattern->toArray(), $this->all($theme));
    }

    /**
     * Valid patterns in one theme folder. Broken or too large files are skipped.
     *
     * @return list<BlockPattern>
     */
    protected function read(Theme $theme): array
    {
        $patterns = [];
        $files = glob($theme->filePath(self::FOLDER).'/*.json') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (! is_file($file) || filesize($file) > $this->maxFileBytes) {
                continue;
            }

            try {
                $data = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            $pattern = is_array($data) ? BlockPattern::fromArray($data, basename($file, '.json')) : null;

            if ($pattern !== null) {
                $patterns[] = $pattern->withText(
                    Translate::text($pattern->title, plugin: $theme->slug),
                    Translate::text($pattern->category, plugin: $theme->slug),
                    $pattern->description === '' ? '' : Translate::text($pattern->description, plugin: $theme->slug),
                );
            }
        }

        return $patterns;
    }

    protected function known(BlockPattern $pattern): bool
    {
        foreach ($pattern->blockTypes() as $type) {
            if (! $this->blocks->has($type)) {
                return false;
            }
        }

        return true;
    }
}
