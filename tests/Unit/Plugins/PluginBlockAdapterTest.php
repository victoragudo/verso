<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

function tideTableBlock(): PluginBlock
{
    return new class implements PluginBlock
    {
        public function name(): string
        {
            return 'tide-table';
        }

        public function label(): string
        {
            return 'Tide table';
        }

        public function render(array $block, ?Entry $entry = null): string
        {
            return '<p>High tide in '.e((string) ($block['attrs']['port'] ?? '')).' at '.e((string) ($block['attrs']['time'] ?? '')).'</p>';
        }
    };
}

it('uses the plugin block name and safe defaults', function () {
    $adapter = new PluginBlockAdapter(tideTableBlock());

    expect($adapter->name())->toBe('tide-table')
        ->and($adapter->rules())->toBe([])
        ->and($adapter->acceptsChild('paragraph'))->toBeFalse()
        ->and($adapter->capability())->toBeNull();
});

it('passes the block data to the plugin renderer', function () {
    $adapter = new PluginBlockAdapter(tideTableBlock());
    $block = BlockData::from(['id' => 'b_tide1', 'type' => 'tide-table', 'attrs' => ['port' => 'Cascais', 'time' => '14:32']]);

    expect($adapter->render($block, app(BlockRenderer::class)))->toBe('<p>High tide in Cascais at 14:32</p>');
});

it('builds attrs rules from the block fields', function () {
    $adapter = new PluginBlockAdapter(new class implements HasBlockFields, PluginBlock
    {
        public function name(): string
        {
            return 'ferry-times';
        }

        public function label(): string
        {
            return 'Ferry times';
        }

        public function description(): string
        {
            return 'Next ferries across the Tagus.';
        }

        public function fields(): array
        {
            return [
                new BlockField('pier', 'Pier', BlockFieldType::Select, options: ['cais-do-sodre' => 'Cais do Sodre', 'belem' => 'Belem'], required: true),
                new BlockField('departures', 'Departures', BlockFieldType::Number, min: 1, max: 12),
            ];
        }

        public function render(array $block, ?Entry $entry = null): string
        {
            return '';
        }
    });

    expect($adapter->rules())->toBe([
        'pier' => ['required', 'string', 'in:cais-do-sodre,belem'],
        'departures' => ['nullable', 'numeric', 'min:1', 'max:12'],
    ]);
});
