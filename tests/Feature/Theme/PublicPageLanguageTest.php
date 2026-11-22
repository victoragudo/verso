<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\ThemeManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

// Texts each theme prints in Spanish and Arabic, per public page.
function publicPageTexts(): array
{
    return [
        'ashgrove' => [
            'es' => [
                'home' => ['Saltar al contenido', 'Entradas recientes', '<span class="visually-hidden">Buscar</span>'],
                'post' => ['Escrito por', 'Entrada anterior', 'Etiquetas'],
                'archive' => ['<h1 class="page-title">Entradas</h1>', 'Categorías'],
                'search' => ['Resultados de “astronomía”', 'Nada coincide con tu búsqueda.'],
                'not found' => ['Esta página no está aquí', 'página de inicio'],
            ],
            'ar' => [
                'home' => ['تخطٍّ إلى المحتوى', 'أحدث المقالات', '<span class="visually-hidden">بحث</span>'],
                'post' => ['بقلم', 'المقالة السابقة', 'الوسوم'],
                'archive' => ['<h1 class="page-title">المقالات</h1>', 'التصنيفات'],
                'search' => ['نتائج «astronomía»', 'لم يطابق بحثك أي شيء.'],
                'not found' => ['هذه الصفحة غير موجودة هنا', 'الصفحة الرئيسية'],
            ],
        ],
        'default' => [
            'es' => [
                'home' => ['Saltar al contenido', 'Buscar en el sitio'],
                'post' => ['Etiquetas', 'por <a'],
                'archive' => ['<h1 class="page-title">Entradas</h1>', 'Anteriores →', 'Página 1 de'],
                'search' => ['Resultados de “astronomía”', 'No se ha encontrado ninguna entrada.'],
                'not found' => ['No se ha encontrado esta página', 'Error 404'],
            ],
            'ar' => [
                'home' => ['تخطٍّ إلى المحتوى', 'البحث في الموقع'],
                'post' => ['الوسوم', 'بقلم <a'],
                'archive' => ['<h1 class="page-title">المقالات</h1>', 'الأقدم ←', 'الصفحة 1 من'],
                'search' => ['نتائج «astronomía»', 'لم يُعثر على أي مقالة.'],
                'not found' => ['تعذّر العثور على هذه الصفحة', 'خطأ 404'],
            ],
        ],
    ];
}

// English texts of the themes that must not show on a translated site.
const PUBLIC_ENGLISH_TEXTS = ['Skip to content', 'Search the site', 'Recent posts', 'Written by', 'Page not found', 'Nothing matched', 'No entries found', 'Older posts', 'Older →', 'This page'];

const PUBLIC_PAGE_PATHS = [
    'home' => '/',
    'post' => '/blog/a-slow-weekend-in-porto',
    'archive' => '/blog',
    'search' => '/search?q=astronom%C3%ADa',
    'not found' => '/no-such-page-here',
];

beforeEach(function () {
    Storage::fake('public');
    config(['verso.page_cache.enabled' => false]);
    $this->seed(DatabaseSeeder::class);
});

function visitPublicPage(string $page): TestResponse
{
    $response = test()->get(PUBLIC_PAGE_PATHS[$page]);

    return $page === 'not found' ? $response->assertNotFound() : $response->assertOk();
}

it('shows every public page in the site language', function (string $theme, string $locale) {
    app(ThemeManager::class)->activate($theme);
    app(Settings::class)->set('site.locale', $locale);
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';

    foreach (publicPageTexts()[$theme][$locale] as $page => $texts) {
        $response = visitPublicPage($page)->assertSee('<html lang="'.$locale.'" dir="'.$dir.'">', false);

        foreach ($texts as $text) {
            $response->assertSee($text, false);
        }

        foreach (PUBLIC_ENGLISH_TEXTS as $english) {
            $response->assertDontSee($english, false);
        }
    }
})->with(['ashgrove', 'default'])->with(['es', 'ar']);

it('prints dates in the site language', function (string $theme) {
    app(ThemeManager::class)->activate($theme);
    app(Settings::class)->set('site.locale', 'es');

    preg_match('#<time datetime="[^"]+">([^<]+)</time>#', visitPublicPage('post')->getContent(), $date);

    expect($date[1] ?? '')->toMatch('/^\d{1,2} de \p{Ll}+ de \d{4}$/u');
})->with(['ashgrove', 'default']);

it('shows the site language to signed in admins who use another admin language', function () {
    app(Settings::class)->set('site.locale', 'es');
    $admin = User::factory()->admin()->create(['admin_locale' => 'de']);

    $this->actingAs($admin)->get('/')
        ->assertOk()
        ->assertSee('<html lang="es" dir="ltr">', false)
        ->assertSee('Entradas recientes')
        ->assertDontSee('Neueste Beiträge');
});

it('keeps English on an English site', function () {
    app(Settings::class)->set('site.locale', 'en');

    visitPublicPage('search')->assertSee('Nothing matched your search.')->assertSee('<html lang="en" dir="ltr">', false);
    visitPublicPage('not found')->assertSee('This page is not here');
});
