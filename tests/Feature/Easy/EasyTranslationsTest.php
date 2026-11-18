<?php

use App\Models\User;
use App\Verso\Easy\Help\HelpTopic;
use App\Verso\Easy\Help\HelpTopics;
use App\Verso\Easy\Launch\LaunchCheck;
use App\Verso\Easy\Launch\LaunchChecklist;
use App\Verso\Easy\Pages\PageVersions;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Easy\Sections\SectionTemplate;
use App\Verso\Easy\Starters\StarterInstallReport;
use App\Verso\Easy\Styles\ContrastChecker;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

// Spanish lines for the strings these tests check. Core keys and "harbor-tours::" plugin keys.
function spanishEasyLines(): void
{
    $lines = [
        'Your site details are saved.' => 'Los datos de tu sitio están guardados.',
        'Restaurant or cafe' => 'Restaurante o cafetería',
        'Your menu, opening hours, a map and a way to book a table.' => 'Tu carta, tu horario, un mapa y una forma de reservar mesa.',
        'Do it' => 'Hacerlo',
        '{count, plural, =0 {Empty page} one {# section} other {# sections}}' => '{count, plural, =0 {Página vacía} one {# sección} other {# secciones}}',
        '{direction, select, darker {The text is hard to read on this background. Try a darker text color.} other {The text is hard to read on this background. Try a lighter text color.}}' => '{direction, select, darker {El texto es difícil de leer sobre este fondo. Prueba un color de texto más oscuro.} other {El texto es difícil de leer sobre este fondo. Prueba un color de texto más claro.}}',
        '{count, plural, one {Your site is ready: # page from the "{starter}" starter.} other {Your site is ready: # pages from the "{starter}" starter.}}' => '{count, plural, one {Tu sitio está listo: # página del sitio "{starter}".} other {Tu sitio está listo: # páginas del sitio "{starter}".}}',
        'harbor-tours::Boat tours' => 'Paseos en barco',
        'harbor-tours::Times and prices of the boat trips.' => 'Horarios y precios de los paseos.',
        'harbor-tours::Book a tour' => 'Reservar un paseo',
        'harbor-tours::Open the tours page.' => 'Abre la página de paseos.',
    ];
    $folder = sys_get_temp_dir().'/verso-easy-lang-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($folder);
    File::put($folder.'/es.json', (string) json_encode($lines));
    app('translator')->addJsonPath($folder);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Lucía Romero', 'admin_locale' => 'es']);
    spanishEasyLines();
});

it('sends the toast after saving the site details in the admin language', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.easy.details.update'), ['email' => 'hola@puertoazul.example', 'social' => []])
        ->assertInertiaFlash('toast.message', 'Los datos de tu sitio están guardados.');
});

it('translates the starter sites on the guided start', function () {
    $this->actingAs($this->admin)->get(route('admin.easy.start'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('starters.3.key', 'restaurant')
            ->where('starters.3.label', 'Restaurante o cafetería')
            ->where('starters.3.description', 'Tu carta, tu horario, un mapa y una forma de reservar mesa.')
            ->where('starters.3.pages.0.title', 'Home'));
});

it('translates plugin labels with the plugin prefix', function () {
    app()->setLocale('es');
    $library = app(SectionLibrary::class);
    $library->register(new SectionTemplate(
        key: 'boat-tours',
        label: 'Boat tours',
        description: 'Times and prices of the boat trips.',
        builder: fn (): array => [],
        keywords: ['boats'],
    ), 'harbor-tours');

    $section = collect($library->toArray())->firstWhere('key', 'boat-tours');

    expect($section['label'])->toBe('Paseos en barco')
        ->and($section['description'])->toBe('Horarios y precios de los paseos.')
        ->and($section['keywords'])->toBe(['boats']);
});

it('translates help topics and launch checks of plugins', function () {
    app()->setLocale('es');
    app(HelpTopics::class)->register(new HelpTopic(key: 'boat-tours', title: 'Boat tours', summary: 'Times and prices of the boat trips.', steps: ['Open the tours page.']), 'harbor-tours');
    app(LaunchChecklist::class)->register(new LaunchCheck(key: 'boat-tours', label: 'Boat tours', description: 'Times and prices of the boat trips.', href: '/admin/tours', done: fn (): bool => false, order: 999), 'harbor-tours');

    $topic = collect(app(HelpTopics::class)->forUser($this->admin))->firstWhere('key', 'boat-tours');
    $check = collect(app(LaunchChecklist::class)->toArray()['items'])->firstWhere('key', 'boat-tours');

    expect($topic['title'])->toBe('Paseos en barco')
        ->and($topic['steps'])->toBe(['Abre la página de paseos.'])
        ->and($check['label'])->toBe('Paseos en barco')
        ->and($check['action'])->toBe('Hacerlo');
});

it('writes contrast warnings, version summaries and the install message in the admin language', function () {
    app()->setLocale('es');
    $warnings = app(ContrastChecker::class)->review(['background' => '#ffffff', 'text' => '#dddddd']);
    $report = new StarterInstallReport('restaurant', 'Restaurante o cafetería', created: ['Inicio', 'Carta', 'Visítanos']);

    expect($warnings[0]['message'])->toBe('El texto es difícil de leer sobre este fondo. Prueba un color de texto más oscuro.')
        ->and(app(PageVersions::class)->summary([]))->toBe('Página vacía')
        ->and(app(PageVersions::class)->summary([['type' => 'section'], ['type' => 'section']]))->toBe('2 secciones')
        ->and($report->message())->toBe('Tu sitio está listo: 3 páginas del sitio de inicio "Restaurante o cafetería".');
});
