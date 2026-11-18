<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Simple\SimpleMode;
use App\Verso\Install\InstallState;
use App\Verso\Install\Pipeline\InstallPipeline;
use App\Verso\Install\Pipeline\InstallProgress;
use App\Verso\Install\Plan\InstallPlan;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Support\InstallFixtures;

beforeEach(function () {
    $this->paths = InstallFixtures::fresh();
    $this->sqlite = $this->paths['folder'].'/lisbon.sqlite';
    $this->plan = InstallPlan::fromSteps(InstallFixtures::answers($this->sqlite));
    $this->pipeline = app(InstallPipeline::class);
});

afterEach(function () {
    config(['database.connections.sqlite.database' => ':memory:']);
    app('db')->purge('sqlite');
});

it('installs Verso on SQLite in a temporary folder', function () {
    $status = $this->pipeline->runAll($this->plan);

    expect($status->finished())->toBeTrue()
        ->and(array_column($status->steps, 'status', 'key'))->toBe([
            'environment' => 'done', 'database' => 'done', 'migrations' => 'done', 'admin' => 'done', 'settings' => 'done',
            'demo' => 'skipped', 'plugins' => 'done', 'storage' => 'done', 'finish' => 'done',
        ])
        ->and(file_exists($this->sqlite))->toBeTrue()
        ->and(file_exists($this->paths['lock']))->toBeTrue();

    $admin = User::firstWhere('email', 'ines@lisbonfood.example');
    $settings = app(Settings::class);
    $settings->forgetCache();
    $env = Dotenv::parse((string) file_get_contents($this->paths['env']));

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(password_verify('sardines-and-vinho-2026', $admin->password))->toBeTrue()
        ->and($settings->get('site.name'))->toBe('Lisbon Food Notes')
        ->and($settings->get('site.timezone'))->toBe('Europe/Lisbon')
        ->and($settings->get('site.locale'))->toBe('pt')
        ->and($settings->get('plugins.enabled'))->toBe(['reading-time'])
        ->and($settings->get(SimpleMode::DEFAULT_SETTING))->toBeTrue()
        ->and($settings->get(InstallState::SETTING))->not->toBeNull()
        ->and($env['APP_NAME'])->toBe('Lisbon Food Notes')
        ->and($env['DB_CONNECTION'])->toBe('sqlite')
        ->and($env['DB_DATABASE'])->toBe($this->sqlite)
        ->and(file_get_contents($this->paths['env']))->toContain('# DB_HOST=127.0.0.1')
        ->and(app(InstallState::class)->isInstalled())->toBeTrue();
});

it('writes a new APP_KEY when the app has none', function () {
    config(['app.key' => '']);

    $this->pipeline->runAll($this->plan);

    expect(Dotenv::parse((string) file_get_contents($this->paths['env']))['APP_KEY'])->toStartWith('base64:');
});

it('adds demo content when asked', function () {
    $answers = InstallFixtures::answers($this->sqlite);
    $answers['content']['demo'] = true;

    $status = $this->pipeline->runAll(InstallPlan::fromSteps($answers));

    expect($status->finished())->toBeTrue()
        ->and(Entry::query()->count())->toBeGreaterThan(5);
});

it('stops at a failed step, keeps the done steps and retries from there', function () {
    $answers = InstallFixtures::answers($this->sqlite);
    $answers['content']['theme'] = 'missing-theme';
    Log::spy();

    $failed = $this->pipeline->runAll(InstallPlan::fromSteps($answers));

    expect($failed->failed())->toBeTrue()
        ->and($failed->error['step'])->toBe('settings')
        ->and(array_column($failed->steps, 'status', 'key'))->toMatchArray(['environment' => 'done', 'migrations' => 'done', 'admin' => 'done', 'settings' => 'failed', 'finish' => 'pending'])
        ->and(file_exists($this->paths['lock']))->toBeFalse()
        ->and(app(InstallState::class)->isInstalled())->toBeFalse();

    $answers['content']['theme'] = 'default';
    $retry = $this->pipeline->runAll(InstallPlan::fromSteps($answers));

    expect($retry->finished())->toBeTrue()
        ->and(User::where('email', 'ines@lisbonfood.example')->count())->toBe(1);
});

it('never puts secrets in error messages or logs', function () {
    $answers = InstallFixtures::answers($this->sqlite);
    $answers['database'] = ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'food', 'username' => 'app', 'password' => 'Pastel-de-nata-77', 'sslmode' => 'disable'];
    $plan = InstallPlan::fromSteps($answers);
    Log::spy();

    expect($plan->redact('login with Pastel-de-nata-77 failed'))->toBe('login with •••••••• failed');

    $status = $this->pipeline->runAll($plan);

    expect($status->failed())->toBeTrue()
        ->and(json_encode($status->toArray()))->not->toContain('Pastel-de-nata-77');

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => ! str_contains($message, 'Pastel-de-nata-77'));
});

it('runs over the web one step per request and signs the admin in at the end', function () {
    $session = ['verso_install' => ['steps' => InstallFixtures::answers($this->sqlite), 'done' => ['welcome', 'requirements', 'database', 'site', 'services', 'content', 'review']]];
    $this->withSession($session);
    $this->post('/install/review');

    $response = null;

    foreach (range(1, 12) as $request) {
        $response = $this->postJson('/install/run')->assertOk();

        if ($response->json('finished')) {
            break;
        }
    }

    expect($response->json('finished'))->toBeTrue()
        ->and($response->json('links.site'))->toBe('https://lisbonfood.example');

    $this->getJson('/install/status')->assertNotFound();

    $link = $response->json('links.admin');
    $this->get($link)->assertRedirect(route('admin.easy.start'));
    $this->assertAuthenticatedAs(User::firstWhere('email', 'ines@lisbonfood.example'));

    auth()->logout();
    $this->get($link)->assertForbidden();
});

// Loads the config a restarted server would read from the .env the install wrote.
function reloadConfigFromEnv(string $envPath): void
{
    $env = Dotenv::parse((string) file_get_contents($envPath));

    config([
        'app.name' => $env['APP_NAME'],
        'session.driver' => $env['SESSION_DRIVER'],
        'session.cookie' => Str::slug($env['APP_NAME']).'-session',
    ]);

    app('session')->forgetDrivers();
}

it('keeps the wizard answers when writing .env renames the session cookie and changes the session driver', function () {
    config(['verso.install.redirect' => true, 'app.name' => 'Verso', 'session.cookie' => 'verso-session']);
    $cookie = (string) config('verso.install.session_cookie');

    $first = $this->get('/install/welcome')->assertOk();
    $sessionId = (string) $first->getCookie($cookie)?->getValue();

    $answers = InstallFixtures::answers($this->sqlite);
    $answers['services']['session_driver'] = 'database';
    $answers['services']['cache_driver'] = 'database';
    $store = app('session')->driver();
    $store->setId($sessionId);
    $store->start();
    $store->put('verso_install', ['steps' => $answers, 'done' => ['welcome', 'requirements', 'database', 'site', 'services', 'content', 'review']]);
    $store->save();

    $response = null;

    foreach (range(1, 12) as $request) {
        $response = $this->withCredentials()->withCookie($cookie, $sessionId)->postJson('/install/run')->assertOk();

        reloadConfigFromEnv($this->paths['env']);

        if ($response->json('finished')) {
            break;
        }
    }

    $env = Dotenv::parse((string) file_get_contents($this->paths['env']));

    expect($sessionId)->not->toBe('')
        ->and($env['APP_NAME'])->toBe('Lisbon Food Notes')
        ->and($env['SESSION_DRIVER'])->toBe('database')
        ->and($response?->json('finished'))->toBeTrue()
        ->and(User::where('email', 'ines@lisbonfood.example')->exists())->toBeTrue();
});

it('refuses a sign-in link with a wrong nonce', function () {
    $this->pipeline->runAll($this->plan);
    $admin = User::firstWhere('email', 'ines@lisbonfood.example');
    app(InstallProgress::class)->rememberLogin($admin->id, 'right-nonce');

    $this->get(URL::temporarySignedRoute('verso.welcome', now()->addMinutes(5), ['user' => $admin->id, 'nonce' => 'wrong-nonce'], absolute: false))->assertForbidden();
    $this->get(route('verso.welcome', ['user' => $admin->id, 'nonce' => 'right-nonce']))->assertForbidden();
});
