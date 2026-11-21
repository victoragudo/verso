<?php

use App\Verso\I18n\Support\CatalogWriter;
use App\Verso\I18n\Support\StringExtractor;
use App\Verso\I18n\Translation\JsonFile;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->extractor = new StringExtractor(base_path());
});

it('finds t() and msg() calls in TypeScript', function () {
    $code = <<<'TS'
        const LABEL = msg('Drafts');
        toast.success(t('Saved “{title}”.', { title }));
        <Button aria-label={t("Close the panel")}>{t(`Publish now`)}</Button>
        format(t('It\'s ready'));
        t(
            '{count, plural, one {# comment} other {# comments}}',
            { count },
        );
        notT('Ignored');
        object.t('Also ignored');
        t(`Value ${name}`);
        TS;

    expect($this->extractor->fromJavaScript($code))->toBe([
        'Drafts',
        'Saved “{title}”.',
        'Close the panel',
        'Publish now',
        "It's ready",
        '{count, plural, one {# comment} other {# comments}}',
    ]);
});

it('finds __() and friends in PHP', function () {
    $code = <<<'PHP'
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post moved to the trash.')]);
        trans_choice('shop::One order|:count orders', $count);
        @lang('Sign in')
        __('validation.required');
        __("Hello :name");
        __('Don\'t panic');
        PHP;

    expect($this->extractor->fromPhpCalls($code))->toBe(['Post moved to the trash.', 'shop::One order|:count orders', 'Sign in', 'Hello :name', "Don't panic"]);
});

it('finds registry labels and keywords in PHP', function () {
    $code = <<<'PHP'
        AdminNav::add(new NavItem(key: 'media', label: 'Media', route: 'admin.media.index', icon: 'media', keywords: ['files', 'images']));
        new ConfigParameter('verso.media.per_page', $group, 'Items per page', 'How many files load at once.', unit: Unit::Items);
        new ConfigGroup(self::EMAIL, 'Email', 'How Verso sends email.', 'newsletter', 40);
        ['label' => 'Shop manager', 'icon' => 'shop', 'route' => 'admin.shop.index'];
        $item = ['label' => $dynamic];
        PHP;

    expect($this->extractor->fromPhpLabels($code))->toBe(['Media', 'Shop manager', 'Items per page', 'How many files load at once.', 'Email', 'How Verso sends email.', 'files', 'images']);
});

it('finds help topic summaries and steps in PHP', function () {
    $code = <<<'PHP'
        new HelpTopic(key: 'upload-photos', title: 'Upload photos', summary: 'Keep your pictures in one place.', steps: ['Open Photos and files.', 'Drop your pictures on the page.']);
        PHP;

    expect($this->extractor->fromPhpLabels($code))->toBe(['Upload photos', 'Keep your pictures in one place.', 'Open Photos and files.', 'Drop your pictures on the page.']);
});

it('finds font pair samples in PHP', function () {
    $code = <<<'PHP'
        new FontPair(key: 'classic', label: 'Classic', heading: 'georgia', body: 'charter', sample: 'Fresh bread every morning.');
        PHP;

    expect($this->extractor->fromPhpLabels($code))->toBe(['Classic', 'Fresh bread every morning.']);
});

it('finds menu location labels in PHP', function () {
    $code = <<<'PHP'
        'menus' => ['locations' => ['primary' => 'Primary menu', 'footer' => 'Footer menu', 'side' => $label]],
        PHP;

    expect($this->extractor->fromPhpLabels($code))->toBe(['Primary menu', 'Footer menu']);
});

it('skips sample site content between ignore lines', function () {
    $code = <<<'PHP'
        new StarterSite(key: 'restaurant', label: 'Restaurant or cafe', description: 'Your menu and opening hours.',
            // i18n-ignore-start
            pages: [['title' => 'Almond croissant', 'button' => ['label' => 'Book a table']]],
            // i18n-ignore-end
        );
        PHP;

    expect($this->extractor->fromPhpLabels($code))->toBe(['Restaurant or cafe', 'Your menu and opening hours.']);
});

it('puts plugin files in the plugin namespace', function (string $path, ?string $namespace) {
    expect($this->extractor->namespaceFor(base_path($path)))->toBe($namespace);
})->with([
    ['resources/js/pages/admin/plugins/shop/orders.tsx', 'shop'],
    ['resources/js/components/verso/newsletter/campaign-form.tsx', 'newsletter'],
    ['resources/js/components/verso/media/media-grid.tsx', null],
    ['resources/js/pages/admin/plugins/index.tsx', null],
    ['plugins/forms/src/FormsServiceProvider.php', 'forms'],
    ['themes/ashgrove/theme.json', 'ashgrove'],
    ['app/Verso/Core/CoreServiceProvider.php', null],
]);

it('reads plugin manifests', function () {
    $keys = $this->extractor->fromManifest(base_path('plugins/shop/verso.json'));

    expect($keys)->toContain('Shop')->toContain('Orders');
});

it('writes English catalogs and reports missing translations', function () {
    $base = sys_get_temp_dir().'/verso-catalog-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($base.'/lang');
    File::ensureDirectoryExists($base.'/plugins/harbor-weather/lang');
    JsonFile::write($base.'/lang/es.json', ['Drafts' => 'Borradores', 'Old text' => 'Texto viejo']);

    $writer = new CatalogWriter($base, ['en', 'es']);
    $catalogs = $writer->catalogs(['Drafts', 'Publish', 'harbor-weather::Tide table']);

    expect($writer->writeSource($catalogs))->toHaveCount(2)
        ->and(JsonFile::read($base.'/lang/en.json'))->toBe(['Drafts' => 'Drafts', 'Publish' => 'Publish'])
        ->and(JsonFile::read($base.'/plugins/harbor-weather/lang/en.json'))->toBe(['Tide table' => 'Tide table'])
        ->and($writer->writeSource($catalogs))->toBe([]);

    $report = $writer->compare($catalogs);

    expect($report[$base.'/lang/es.json'])->toBe(['missing' => ['Publish'], 'extra' => ['Old text']])
        ->and($writer->prune($catalogs))->toBe(1)
        ->and(JsonFile::read($base.'/lang/es.json'))->toBe(['Drafts' => 'Borradores']);

    File::deleteDirectory($base);
});
