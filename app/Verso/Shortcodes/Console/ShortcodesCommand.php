<?php

namespace App\Verso\Shortcodes\Console;

use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Shortcodes\Enums\UsageKind;
use App\Verso\Shortcodes\Models\EmbedUsage;
use App\Verso\Shortcodes\ShortcodeDefinition;
use App\Verso\Shortcodes\ShortcodeRegistry;
use Illuminate\Console\Command;

// Lists registered shortcodes. With --usage it also lists where they are used and orphans.
class ShortcodesCommand extends Command
{
    protected $signature = 'verso:shortcodes {--usage : Show the entries that use each shortcode, and orphans}';

    protected $description = 'List the registered shortcodes and where they are used';

    public function handle(ShortcodeRegistry $registry): int
    {
        $definitions = $registry->all();

        if ($definitions === []) {
            $this->components->info('No shortcodes are registered. Enable a plugin with blocks, like Forms.');
        } else {
            $this->table(['Shortcode', 'Plugin', 'Fields'], array_map(fn (ShortcodeDefinition $definition): array => [
                '['.$definition->name().']',
                $definition->plugin !== '' ? $definition->plugin : '-',
                implode(', ', array_map(fn (BlockField $field): string => $field->name.($field->required ? '*' : ''), $definition->fields())),
            ], array_values($definitions)));
        }

        if ($this->option('usage')) {
            $this->usage($definitions);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, ShortcodeDefinition>  $definitions
     */
    protected function usage(array $definitions): void
    {
        $rows = EmbedUsage::query()->with('entry:id,title,type')->orderBy('name')->orderBy('entry_id')->get();

        if ($rows->isEmpty()) {
            $this->components->info('No entry uses a shortcode or a plugin block yet.');

            return;
        }

        $this->newLine();
        $this->table(['Name', 'Kind', 'Item', 'Entry', 'Status'], $rows->map(fn (EmbedUsage $usage): array => [
            $usage->name,
            $usage->kind->value,
            $usage->key ?? '-',
            ($usage->entry->title ?? '?').' (#'.$usage->entry_id.')',
            isset($definitions[$usage->name]) || $usage->kind === UsageKind::Block ? 'ok' : 'orphan',
        ])->all());

        $orphans = $rows->filter(fn (EmbedUsage $usage): bool => $usage->kind === UsageKind::Shortcode && ! isset($definitions[$usage->name]))
            ->pluck('name')->unique()->values()->all();

        if ($orphans !== []) {
            $this->components->warn('Orphan shortcodes (used but not registered): '.implode(', ', $orphans));
        }
    }
}
