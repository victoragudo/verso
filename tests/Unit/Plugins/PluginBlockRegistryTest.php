<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

function weatherBlock(string $label = 'Weather'): PluginBlock
{
    return new class($label) implements PluginBlock
    {
        public function __construct(protected string $title) {}

        public function name(): string
        {
            return 'weather';
        }

        public function label(): string
        {
            return $this->title;
        }

        public function render(array $block, ?Entry $entry = null): string
        {
            return '<p>Sunny in '.e((string) ($block['attrs']['city'] ?? 'Lisbon')).'</p>';
        }
    };
}

it('registers and finds blocks', function () {
    $registry = new PluginBlockRegistry;
    $registry->register(weatherBlock());

    expect($registry->has('weather'))->toBeTrue()
        ->and($registry->has('poll'))->toBeFalse()
        ->and($registry->find('weather')?->label())->toBe('Weather')
        ->and($registry->find('poll'))->toBeNull()
        ->and(array_keys($registry->all()))->toBe(['weather']);
});

it('replaces a block with the same name', function () {
    $registry = new PluginBlockRegistry;
    $registry->register(weatherBlock());
    $registry->register(weatherBlock('Forecast'));

    expect($registry->toArray())->toBe([['name' => 'weather', 'label' => 'Forecast', 'description' => '', 'fields' => [], 'children' => false, 'childTypes' => null, 'parents' => null, 'starter' => [], 'shortcode' => false, 'aliases' => []]]);
});

it('shares the description and fields of blocks that describe them', function () {
    $registry = new PluginBlockRegistry;
    $registry->register(new class implements HasBlockFields, PluginBlock
    {
        public function name(): string
        {
            return 'opening-hours';
        }

        public function label(): string
        {
            return 'Opening hours';
        }

        public function description(): string
        {
            return 'When the bakery on Rua Augusta is open.';
        }

        public function fields(): array
        {
            return [
                new BlockField('weekdays', 'Weekdays', default: '08:00 to 19:00'),
                new BlockField('closedOnSunday', 'Closed on Sunday', BlockFieldType::Toggle, default: true),
            ];
        }

        public function render(array $block, ?Entry $entry = null): string
        {
            return '';
        }
    });

    $definition = $registry->toArray()[0];

    expect($definition['description'])->toBe('When the bakery on Rua Augusta is open.')
        ->and(array_column($definition['fields'], 'name'))->toBe(['weekdays', 'closedOnSunday'])
        ->and($definition['fields'][0]['default'])->toBe('08:00 to 19:00')
        ->and($definition['fields'][1]['type'])->toBe('toggle');
});
