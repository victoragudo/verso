<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Forms\Actions\SaveForm;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\Support\FormsSettings;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('forms');
    Mail::fake();
    $this->form = bakeryContactForm();
});

function pageWithForm(string $slug = 'bakery-contact', array $attrs = []): Entry
{
    return Entry::factory()->published()->create([
        'type' => 'page',
        'title' => 'Order a cake',
        'slug' => 'order-a-cake',
        'blocks' => [['id' => 'b_form', 'type' => 'form', 'attrs' => ['form' => $slug, 'title' => 'Ask for a quote', ...$attrs], 'content' => '', 'children' => []]],
    ]);
}

it('renders the form block with labels, help text and spam protection', function () {
    $html = $this->get(pageWithForm()->path())->assertOk()->getContent();

    expect($html)
        ->toContain('id="form-bakery-contact"')
        ->toContain('<h2 class="verso-form__title" id="form-bakery-contact-title">Ask for a quote</h2>')
        ->toContain('action="'.route('forms.submit', $this->form).'"')
        ->toContain('<label for="form-bakery-contact-name">Your name')
        ->toContain('aria-describedby="form-bakery-contact-message-help form-bakery-contact-message-error"')
        ->toContain('Tell us the date and the number of guests.')
        ->toContain('autocomplete="email"')
        ->toContain('name="company_website"')
        ->toContain('name="verso_form_time"')
        ->toContain('data-token-url="'.route('forms.token').'"')
        ->toContain('<option value="Wedding cakes"')
        ->toContain('verso-form__field--half')
        ->toContain('>Send</button>');
});

it('groups radio buttons and checkboxes in a fieldset with a legend', function () {
    bakeryContactForm(extraFields: [
        ['id' => 'f6', 'type' => 'radio', 'name' => 'size', 'label' => 'Cake size', 'required' => true, 'options' => ['Small', 'Large']],
        ['id' => 'f7', 'type' => 'checkboxes', 'name' => 'extras', 'label' => 'Extras', 'options' => ['Candles', 'Delivery']],
    ]);

    $html = $this->get(pageWithForm()->path())->getContent();

    expect($html)
        ->toContain('<legend>Cake size')
        ->toMatch('/name="fields\[size\]"\s+value="Small"/')
        ->toMatch('/name="fields\[extras\]\[\]"\s+value="Delivery"/')
        ->toContain('<label for="form-bakery-contact-extras-1">Delivery</label>');
});

it('shows a file input with its accepted types and size limit', function () {
    bakeryContactForm(extraFields: [
        ['id' => 'f6', 'type' => 'file', 'name' => 'sketch', 'label' => 'Cake sketch', 'accept' => ['pdf', 'png'], 'max_kb' => 2048],
    ]);

    $html = $this->get(pageWithForm()->path())->getContent();

    expect($html)
        ->toContain('enctype="multipart/form-data"')
        ->toContain('accept=".pdf,.png"')
        ->toContain('data-max-kb="2048"')
        ->toContain('PDF, PNG, up to 2 MB.');
});

it('prints the style and script once for two forms on one page', function () {
    app(SaveForm::class)->handle(['title' => 'Newsletter feedback', 'slug' => 'feedback', 'fields' => [['id' => 'f1', 'type' => 'textarea', 'name' => 'message', 'label' => 'Your feedback']], 'settings' => Form::DEFAULT_SETTINGS]);
    $entry = Entry::factory()->published()->create([
        'type' => 'page',
        'slug' => 'two-forms',
        'blocks' => [
            ['id' => 'b_1', 'type' => 'form', 'attrs' => ['form' => 'bakery-contact'], 'content' => '', 'children' => []],
            ['id' => 'b_2', 'type' => 'form', 'attrs' => ['form' => 'feedback'], 'content' => '', 'children' => []],
        ],
    ]);

    $html = $this->get($entry->path())->getContent();

    expect(substr_count($html, 'form.verso-form__form'))->toBe(1)
        ->and(substr_count($html, '<form class="verso-form__form"'))->toBe(2);
});

it('renders nothing for an unknown or empty form choice', function () {
    $html = $this->get(pageWithForm('catering-quote')->path())->getContent();

    expect($html)->not->toContain('verso-form__form');
});

it('shows errors, old values and focuses the first wrong field after a failed send', function () {
    $entry = pageWithForm();

    $this->post(route('forms.submit', $this->form), bakeryAnswers(['email' => 'ines@', 'message' => 'Hi'], ['_page' => url($entry->path())]))
        ->assertRedirect(url($entry->path()).'#form-bakery-contact');

    $html = $this->get($entry->path())->getContent();

    expect($html)
        ->toContain('role="alert"')
        ->toContain('2 answers need your attention')
        ->toContain('value="Inês Carvalho"')
        ->toMatch('/id="form-bakery-contact-email"[^>]*aria-invalid="true"[^>]*autofocus/s')
        ->toContain('The Email field must be a valid email address.');
});

it('keeps the errors of one form away from another form on the same page', function () {
    app(SaveForm::class)->handle(['title' => 'Feedback', 'slug' => 'feedback', 'fields' => [['id' => 'f1', 'type' => 'email', 'name' => 'email', 'label' => 'Email']], 'settings' => Form::DEFAULT_SETTINGS]);

    session()->put('errors', (new ViewErrorBag)->put('form_bakery-contact', new MessageBag(['fields.email' => ['Wrong email.']])));

    $html = Blade::render('<x-forms-form slug="feedback" />');

    expect($html)->not->toContain('Wrong email.')->not->toContain('aria-invalid="true"');
});

it('shows the success message after a message was sent', function () {
    $entry = pageWithForm();

    $this->post(route('forms.submit', $this->form), bakeryAnswers(extra: ['_page' => url($entry->path())]));

    $html = $this->get($entry->path())->getContent();

    expect($html)
        ->toContain('<p class="verso-form__success" role="status"')
        ->toContain('Thank you! Your message was sent.')
        ->not->toContain('<form class="verso-form__form"');
});

it('prints a form in a theme with the Blade component', function () {
    $html = Blade::render('<x-forms-form slug="bakery-contact" heading="Write to us" />');

    expect($html)->toContain('Write to us')->toContain('<form class="verso-form__form"');
    expect(Blade::render('<x-forms-form slug="no-such-form" />'))->toBe('');
});

it('never compiles visitor or editor text as Blade', function () {
    bakeryContactForm(['submit_label' => '{{ 7 * 6 }}']);

    expect(Blade::render('<x-forms-form slug="bakery-contact" />'))->toContain('{{ 7 * 6 }}')->not->toContain('>42<');
});

it('shows the captcha widget when keys are saved', function () {
    app(FormsSettings::class)->save([
        'retention_days' => 365, 'max_links' => 3, 'min_seconds' => 3, 'blocked_words' => '',
        'captcha_provider' => 'turnstile', 'captcha_site_key' => '0x4AAAAAAAsitekey', 'captcha_secret' => 'secret',
    ]);

    $html = Blade::render('<x-forms-form slug="bakery-contact" />');

    expect($html)
        ->toContain('class="cf-turnstile verso-form__captcha" data-sitekey="0x4AAAAAAAsitekey"')
        ->toContain('src="https://challenges.cloudflare.com/turnstile/v0/api.js"');
});

it('works with the page cache: the page is cached and pages with a notice are not', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    $entry = pageWithForm();

    $this->get($entry->path())->assertHeader(PageCache::HEADER, 'MISS');
    $this->get($entry->path())->assertHeader(PageCache::HEADER, 'HIT');

    $this->post(route('forms.submit', $this->form), bakeryAnswers(['email' => 'wrong'], ['_page' => url($entry->path())]))
        ->assertCookie((string) config('verso.page_cache.bypass_cookie'));

    // The browser sends the bypass cookie back, so it gets a live page with the errors, which is not stored.
    $this->withCookie((string) config('verso.page_cache.bypass_cookie'), '1')
        ->get($entry->path())
        ->assertSee('one answer needs your attention')
        ->assertHeaderMissing(PageCache::HEADER);

    $this->withCookie((string) config('verso.page_cache.bypass_cookie'), '1')
        ->get($entry->path())
        ->assertDontSee('answer needs your attention');
});

it('describes the form block for the editor with a searchable list of forms', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.entries.create', 'page'))
        ->assertInertia(fn ($page) => $page
            ->where('pluginBlocks', fn ($blocks) => collect($blocks)->contains(fn ($block) => $block['name'] === 'form'
                && $block['fields'][0]['searchable'] === true
                && $block['fields'][0]['options'][0] === ['value' => 'bakery-contact', 'label' => 'Contact the bakery']))
        );
});

it('shows untouched default texts in the site language and keeps custom ones', function () {
    app(Settings::class)->set('site.locale', 'es');

    $page = pageWithForm();

    $this->get($page->path())->assertOk()->assertSee('>Enviar</button>', false);
    $this->postJson(route('forms.submit', $this->form), bakeryAnswers())->assertJson(['message' => '¡Gracias! Tu mensaje se ha enviado.']);

    bakeryContactForm(['submit_label' => 'Pedir presupuesto']);
    app(PageCache::class)->flush();

    $this->get($page->path())->assertOk()->assertSee('>Pedir presupuesto</button>', false);
});
