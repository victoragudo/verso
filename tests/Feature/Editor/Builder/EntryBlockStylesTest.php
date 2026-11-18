<?php

use App\Verso\Content\Models\Entry;

it('prints the styles of styled blocks on the public entry page', function () {
    Entry::factory()->page()->published()->create([
        'title' => 'Harbor Bakery',
        'slug' => 'harbor-bakery',
        'blocks' => [[
            'id' => 'b_hero', 'type' => 'section', 'attrs' => ['tag' => 'div', 'design' => [
                'spacing' => ['padding' => ['top' => ['base' => '96px', 'mobile' => '40px']]],
                'color' => ['background' => '#f4efe6'],
            ]],
            'children' => [['id' => 'b_t', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Fresh sourdough every morning']],
        ]],
    ]);

    $html = $this->get('/harbor-bakery')->assertOk()->getContent();

    preg_match('/<div class="verso-section (vs-[a-z0-9]+)">/', $html, $match);

    expect($match)->not->toBeEmpty()
        ->and(substr_count($html, '<style data-verso-block-styles>'))->toBe(1)
        ->and($html)->toContain('.'.$match[1].'{padding-top:96px;background-color:#f4efe6}')
        ->and($html)->toMatch('/@media \(max-width: ?640px\)\{\.'.$match[1].'\{padding-top:40px\}\}/');
});
