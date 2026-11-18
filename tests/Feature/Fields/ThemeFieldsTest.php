<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use App\Verso\Fields\FieldGroupRegistry;
use App\Verso\Fields\Models\FieldGroupRecord;
use App\Verso\Fields\Theme\CurrentEntry;
use App\Verso\Fields\Theme\EntryFieldValues;
use App\Verso\Fields\Values\FieldImage;
use App\Verso\Media\Models\Media;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;

beforeEach(function () {
    $this->photo = Media::factory()->create(['alt' => 'Tiled facade in Alfama', 'caption' => 'Morning light']);
    $this->second = Media::factory()->create(['alt' => 'Tram 28']);
    $this->guide = Media::factory()->document()->create(['filename' => 'lisbon-guide.pdf']);
    $this->related = Entry::factory()->published()->create(['title' => 'Three days in Porto']);
    $this->draft = Entry::factory()->create(['title' => 'Unfinished draft']);
    $this->term = Term::factory()->create(['name' => 'City breaks']);
    $this->host = User::factory()->editor()->create(['name' => 'Inês Moreira']);

    FieldGroupRecord::factory()->create([
        'key' => 'city_guide',
        'fields' => [
            ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text'],
            ['name' => 'summary', 'label' => 'Summary', 'type' => 'rich_text'],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
            ['name' => 'budget', 'label' => 'Budget', 'type' => 'number'],
            ['name' => 'season', 'label' => 'Season', 'type' => 'select', 'options' => ['spring' => 'Spring', 'autumn' => 'Autumn']],
            ['name' => 'perks', 'label' => 'Perks', 'type' => 'checkbox', 'options' => ['wifi' => 'Free wifi', 'pool' => 'Pool']],
            ['name' => 'family', 'label' => 'Family friendly', 'type' => 'toggle'],
            ['name' => 'visit_on', 'label' => 'Visit on', 'type' => 'date'],
            ['name' => 'tour_at', 'label' => 'Tour at', 'type' => 'date_time'],
            ['name' => 'accent', 'label' => 'Accent', 'type' => 'color'],
            ['name' => 'hero', 'label' => 'Hero', 'type' => 'image'],
            ['name' => 'brochure', 'label' => 'Brochure', 'type' => 'file'],
            ['name' => 'photos', 'label' => 'Photos', 'type' => 'gallery'],
            ['name' => 'see_also', 'label' => 'See also', 'type' => 'entry_relation', 'multiple' => true],
            ['name' => 'main_topic', 'label' => 'Main topic', 'type' => 'term_relation', 'taxonomy' => 'category'],
            ['name' => 'host', 'label' => 'Host', 'type' => 'user_relation'],
            ['name' => 'stops', 'label' => 'Stops', 'type' => 'repeater', 'fields' => [
                ['name' => 'place', 'label' => 'Place', 'type' => 'text'],
                ['name' => 'photo', 'label' => 'Photo', 'type' => 'image'],
            ]],
            ['name' => 'address', 'label' => 'Address', 'type' => 'group', 'fields' => [
                ['name' => 'street', 'label' => 'Street', 'type' => 'text'],
                ['name' => 'city', 'label' => 'City', 'type' => 'text'],
            ]],
        ],
    ]);

    $this->entry = Entry::factory()->published()->create(['title' => 'A weekend in Lisbon', 'meta' => ['fields' => [
        'subtitle' => 'Trams, tiles & custard tarts',
        'summary' => '<p>Walk <strong>slowly</strong>.</p>',
        'notes' => "Bring shoes\nfor hills",
        'budget' => 350,
        'season' => 'spring',
        'perks' => ['wifi', 'pool'],
        'family' => true,
        'visit_on' => '2026-05-02',
        'tour_at' => '2026-05-02T09:30',
        'accent' => '#0f766e',
        'hero' => $this->photo->id,
        'brochure' => $this->guide->id,
        'photos' => [$this->second->id, $this->photo->id],
        'see_also' => [$this->draft->id, $this->related->id],
        'main_topic' => $this->term->id,
        'host' => $this->host->id,
        'stops' => [['place' => 'Miradouro da Graça', 'photo' => $this->second->id]],
        'address' => ['street' => 'Rua Augusta 10', 'city' => 'Lisbon'],
    ]]]);
});

it('gives typed values to themes', function () {
    $values = app(EntryFieldValues::class);

    expect($values->get($this->entry, 'subtitle'))->toBe('Trams, tiles & custard tarts')
        ->and($values->get($this->entry, 'summary'))->toBeInstanceOf(HtmlString::class)
        ->and($values->get($this->entry, 'budget'))->toBe(350)
        ->and($values->get($this->entry, 'perks'))->toBe(['wifi', 'pool'])
        ->and($values->get($this->entry, 'family'))->toBeTrue()
        ->and($values->get($this->entry, 'visit_on'))->toBeInstanceOf(CarbonImmutable::class)
        ->and($values->get($this->entry, 'visit_on')->format('Y-m-d'))->toBe('2026-05-02')
        ->and($values->get($this->entry, 'brochure'))->toBeInstanceOf(Media::class)
        ->and($values->get($this->entry, 'main_topic')?->is($this->term))->toBeTrue()
        ->and($values->get($this->entry, 'host')?->is($this->host))->toBeTrue()
        ->and($values->get($this->entry, 'address.city'))->toBe('Lisbon')
        ->and($values->get($this->entry, 'unknown'))->toBeNull();
});

it('returns images as media objects with a srcset', function () {
    $hero = app(EntryFieldValues::class)->get($this->entry, 'hero');

    expect($hero)->toBeInstanceOf(FieldImage::class)
        ->and($hero->id())->toBe($this->photo->id)
        ->and($hero->alt)->toBe('Tiled facade in Alfama')
        ->and($hero->srcset)->toContain('w=')
        ->and($hero->sizes)->toBe(config('verso.fields.image_sizes'))
        ->and($hero->img(['class' => 'hero']))->toContain('class="hero"')
        ->and($hero->toHtml())->toContain('srcset=')
        ->and(app(EntryFieldValues::class)->get($this->entry, 'photos'))->toHaveCount(2)
        ->and(app(EntryFieldValues::class)->get($this->entry, 'photos')[0]->id())->toBe($this->second->id);
});

it('returns relations as models in the saved order and hides unpublished entries', function () {
    $seeAlso = app(EntryFieldValues::class)->get($this->entry, 'see_also');

    expect($seeAlso)->toBeInstanceOf(Collection::class)
        ->and($seeAlso->pluck('title')->all())->toBe(['Three days in Porto']);
});

it('formats repeater rows and groups', function () {
    $stops = app(EntryFieldValues::class)->get($this->entry, 'stops');

    expect($stops)->toHaveCount(1)
        ->and($stops[0]['place'])->toBe('Miradouro da Graça')
        ->and($stops[0]['photo'])->toBeInstanceOf(FieldImage::class)
        ->and(app(EntryFieldValues::class)->all($this->entry)['address'])->toBe(['street' => 'Rua Augusta 10', 'city' => 'Lisbon']);
});

it('prints fields with the @field directive', function (string $name, string $html) {
    expect(Blade::render("@field('{$name}')", ['entry' => $this->entry]))->toBe($html);
})->with([
    'text is escaped' => ['subtitle', 'Trams, tiles &amp; custard tarts'],
    'rich text is printed as html' => ['summary', '<p>Walk <strong>slowly</strong>.</p>'],
    'textarea keeps lines' => ['notes', "Bring shoes<br />\nfor hills"],
    'select prints the label' => ['season', 'Spring'],
    'checkboxes print labels' => ['perks', 'Free wifi, Pool'],
    'toggle prints yes' => ['family', 'Yes'],
    'date uses the date format' => ['visit_on', '2 May 2026'],
    'date time uses its format' => ['tour_at', '2 May 2026, 09:30'],
    'number' => ['budget', '350'],
    'group child' => ['address.city', 'Lisbon'],
    'unknown field prints nothing' => ['missing', ''],
    'repeater prints nothing' => ['stops', ''],
]);

it('prints images, files and relations as html', function () {
    $render = fn (string $name) => Blade::render("@field('{$name}')", ['entry' => $this->entry]);

    expect($render('hero'))->toStartWith('<img src=')
        ->and($render('hero'))->toContain('alt="Tiled facade in Alfama"')
        ->and($render('brochure'))->toContain('>lisbon-guide.pdf</a>')
        ->and($render('see_also'))->toBe('<a href="'.e($this->related->url()).'">Three days in Porto</a>')
        ->and($render('main_topic'))->toContain('>City breaks</a>')
        ->and($render('host'))->toBe('Inês Moreira')
        ->and(substr_count($render('photos'), '<img'))->toBe(2);
});

it('reads the entry of the page with the field helper', function () {
    expect(field('subtitle'))->toBeNull();

    app(CurrentEntry::class)->set($this->entry);

    expect(field('subtitle'))->toBe('Trams, tiles & custard tarts')
        ->and(field('budget', $this->related))->toBeNull()
        ->and(fields())->toHaveKey('stops')
        ->and(Blade::render("@field('subtitle')"))->toBe('Trams, tiles &amp; custard tarts')
        ->and(Blade::render("@field('subtitle', \$other)", ['other' => $this->related]))->toBe('');
});

it('remembers the entry while its theme view renders', function () {
    $this->get($this->entry->path())->assertOk();

    expect(app(CurrentEntry::class)->get()?->id)->toBe($this->entry->id);
});

it('reads field groups from the active theme folder', function () {
    $folder = 'fields-'.uniqid();
    config(['verso.fields.json_folder' => $folder]);
    $path = base_path('themes/'.app(Settings::class)->get('theme.active').'/'.$folder);
    File::ensureDirectoryExists($path);
    File::put($path.'/event.json', json_encode([
        'key' => 'theme_event',
        'title' => 'Event',
        'location' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
        'fields' => [['name' => 'venue', 'label' => 'Venue', 'type' => 'text']],
    ]));

    try {
        app()->forgetInstance(FieldGroupRegistry::class);

        expect(app(FieldGroupRegistry::class)->find('theme_event')?->source)->toBe('json');
    } finally {
        File::deleteDirectory($path);
    }
});

it('adds custom fields to the REST API entry resource', function () {
    $response = $this->getJson('/api/v1/entries/post/'.$this->entry->slug)->assertOk();

    $response->assertJsonPath('data.fields.subtitle', 'Trams, tiles & custard tarts')
        ->assertJsonPath('data.fields.summary', '<p>Walk <strong>slowly</strong>.</p>')
        ->assertJsonPath('data.fields.budget', 350)
        ->assertJsonPath('data.fields.family', true)
        ->assertJsonPath('data.fields.visit_on', '2026-05-02')
        ->assertJsonPath('data.fields.hero.id', $this->photo->id)
        ->assertJsonPath('data.fields.hero.alt', 'Tiled facade in Alfama')
        ->assertJsonPath('data.fields.hero.caption', 'Morning light')
        ->assertJsonPath('data.fields.brochure.filename', 'lisbon-guide.pdf')
        ->assertJsonPath('data.fields.photos.0.id', $this->second->id)
        ->assertJsonPath('data.fields.see_also', [['id' => $this->related->id, 'type' => 'post', 'title' => 'Three days in Porto', 'slug' => $this->related->slug, 'url' => $this->related->url()]])
        ->assertJsonPath('data.fields.main_topic.name', 'City breaks')
        ->assertJsonPath('data.fields.host', ['id' => $this->host->id, 'name' => 'Inês Moreira'])
        ->assertJsonPath('data.fields.stops.0.place', 'Miradouro da Graça')
        ->assertJsonPath('data.fields.stops.0.photo.id', $this->second->id)
        ->assertJsonPath('data.fields.address.city', 'Lisbon');

    expect($response->json('data.fields.hero.srcset'))->toContain('w=')
        ->and($response->json('data.fields.tour_at'))->toStartWith('2026-05-02T09:30:00')
        ->and($response->json('data.fields.host'))->not->toHaveKey('email');
});

it('sends an empty fields object when no group applies', function () {
    $page = Entry::factory()->page()->published()->create(['title' => 'Contact']);

    $this->getJson('/api/v1/entries/page')->assertOk()->assertJsonPath('data.0.id', $page->id);
    expect($this->getJson('/api/v1/entries/page')->getContent())->toContain('"fields":{}');
});
