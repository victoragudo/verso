<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\RenderContext;

function drawFor(Entry $entry, array $blocks): string
{
    return app(RenderContext::class)->withEntry($entry, fn (): string => app(BlockRenderer::class)->render($blocks));
}

beforeEach(function () {
    $this->author = User::factory()->create(['name' => 'Clara Moreno', 'slug' => 'clara-moreno']);
    $this->entry = Entry::factory()->published()->create([
        'type' => 'post', 'title' => 'Ten tips for a crisp crust', 'slug' => 'crisp-crust', 'author_id' => $this->author->id,
        'excerpt' => '', 'published_at' => '2026-03-14 09:30:00',
        'blocks' => [['id' => 'b_p', 'type' => 'paragraph', 'content' => 'Steam is the secret of a crisp crust in a home oven.']],
    ]);
});

it('draws nothing outside of an entry', function () {
    expect(app(BlockRenderer::class)->render([['id' => 'b_t', 'type' => 'post-title']]))->toBe('');
});

it('draws the title, date, author and excerpt of the current entry', function () {
    app(Settings::class)->set('site.timezone', 'Europe/Madrid');

    $html = drawFor($this->entry, [
        ['id' => 'b_t', 'type' => 'post-title', 'attrs' => ['level' => '2', 'link' => true]],
        ['id' => 'b_d', 'type' => 'post-date', 'attrs' => ['prefix' => 'Baked on']],
        ['id' => 'b_a', 'type' => 'post-author', 'attrs' => ['prefix' => 'By']],
        ['id' => 'b_e', 'type' => 'post-excerpt', 'attrs' => ['words' => 5, 'more' => 'Read more']],
    ]);

    expect($html)->toContain('<h2 class="verso-post-title"><a href="'.e($this->entry->url()).'">Ten tips for a crisp crust</a></h2>')
        ->toContain('Baked on <time datetime="2026-03-14T10:30:00+01:00">')
        ->toContain('By <a href="'.url('author/clara-moreno').'" rel="author">Clara Moreno</a>')
        ->toContain('Steam is the secret of')
        ->toContain('<span class="verso-visually-hidden">: Ten tips for a crisp crust</span>');
});

it('lists the terms of the entry and the trail to it', function () {
    $tips = Term::factory()->create(['taxonomy' => 'category', 'name' => 'Baking tips', 'slug' => 'baking-tips']);
    $this->entry->terms()->attach($tips);
    $this->entry->load('terms');

    $html = drawFor($this->entry, [
        ['id' => 'b_terms', 'type' => 'post-terms', 'attrs' => ['taxonomy' => 'category', 'prefix' => 'Filed under']],
        ['id' => 'b_crumbs', 'type' => 'breadcrumbs'],
    ]);

    expect($html)->toContain('<span>Filed under</span> <a href="'.e($tips->url()).'" rel="tag">Baking tips</a>')
        ->toContain('<nav class="verso-breadcrumbs" aria-label="Breadcrumbs"><ol>')
        ->toContain('aria-current="page">Ten tips for a crisp crust</a>');
});

it('never draws the content of an entry inside itself', function () {
    $loop = Entry::factory()->published()->create(['type' => 'page', 'slug' => 'loop', 'blocks' => [['id' => 'b_c', 'type' => 'post-content']]]);

    expect(drawFor($loop, [['id' => 'b_c', 'type' => 'post-content']]))->toContain('verso-post-content');
});

it('says the reading time with plurals', function () {
    $long = Entry::factory()->published()->create(['type' => 'post', 'blocks' => [['id' => 'b_p', 'type' => 'paragraph', 'content' => str_repeat('flour water salt yeast ', 150)]]]);

    expect(drawFor($long, [['id' => 'b_r', 'type' => 'post-reading-time']]))->toContain('3 minutes read');
});
