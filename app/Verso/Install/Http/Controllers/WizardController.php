<?php

namespace App\Verso\Install\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\I18n\AdminLocale;
use App\Verso\I18n\Locales;
use App\Verso\I18n\Translate;
use App\Verso\Install\Database\ConnectionProblem;
use App\Verso\Install\Database\DatabaseConnectionTester;
use App\Verso\Install\Database\DatabaseCreator;
use App\Verso\Install\Database\DatabaseSettings;
use App\Verso\Install\Pipeline\InstallPipeline;
use App\Verso\Install\Plan\InstallPlan;
use App\Verso\Install\Requirement;
use App\Verso\Install\Requirements;
use App\Verso\Install\Wizard\SecretFields;
use App\Verso\Install\Wizard\StepRules;
use App\Verso\Install\Wizard\WizardDefaults;
use App\Verso\Install\Wizard\WizardOptions;
use App\Verso\Install\Wizard\WizardStep;
use App\Verso\Install\Wizard\WizardStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

// The install wizard pages: shows a step and saves it after server side checks.
class WizardController extends Controller
{
    public function __construct(
        protected WizardStore $store,
        protected StepRules $rules,
        protected WizardOptions $options,
        protected WizardDefaults $defaults,
    ) {}

    public function index(): RedirectResponse
    {
        return to_route('install.step', $this->store->current()->value);
    }

    public function show(Request $request, string $step, Requirements $requirements, InstallPipeline $pipeline, DatabaseConnectionTester $tester): Response|RedirectResponse
    {
        $current = WizardStep::tryFrom($step) ?? abort(404);

        if (! $this->store->canOpen($current)) {
            return $this->index();
        }

        $language = $this->language($request);
        App::setLocale($language);

        $stored = $this->store->get($current);
        $defaults = $this->defaults->for($current, $request->root(), $language);
        $secrets = SecretFields::strip($current, $stored === [] ? $defaults : $stored + $defaults);

        return Inertia::render('install/wizard', [
            'step' => $current->value,
            'steps' => WizardStep::list(),
            'done' => $this->store->done(),
            'values' => $secrets['values'],
            'savedSecrets' => $stored === [] ? [] : $secrets['saved'],
            'versoVersion' => (string) config('verso.version'),
            'siteName' => 'Verso',
            'siteTimezone' => 'UTC',
            ...$this->stepProps($current, $requirements, $pipeline, $tester),
        ]);
    }

    public function save(Request $request, string $step, Requirements $requirements, DatabaseConnectionTester $tester, DatabaseCreator $creator, InstallPipeline $pipeline): RedirectResponse
    {
        $current = WizardStep::tryFrom($step) ?? abort(404);

        if (! $this->store->canOpen($current)) {
            return $this->index();
        }

        $values = match ($current) {
            WizardStep::Requirements => $this->checkRequirements($requirements),
            WizardStep::Database => $this->checkDatabase($request, $tester, $creator),
            WizardStep::Review, WizardStep::Install => [],
            default => $this->rules->validate($current, SecretFields::restore($current, $request->all(), $this->store->get($current))),
        };

        unset($values['admin_password_confirmation']);
        $this->store->put($current, $values);

        if ($current === WizardStep::Review) {
            $pipeline->start();
        }

        return to_route('install.step', ($current->next() ?? WizardStep::Install)->value);
    }

    /**
     * @return array<string, mixed>
     */
    protected function stepProps(WizardStep $step, Requirements $requirements, InstallPipeline $pipeline, DatabaseConnectionTester $tester): array
    {
        return match ($step) {
            WizardStep::Welcome => ['languages' => $this->options->languages()],
            WizardStep::Requirements => [
                'requirements' => array_map(fn (Requirement $requirement): array => $requirement->toArray(), $requirements->check()),
                'ready' => $requirements->passes(),
            ],
            WizardStep::Database => [
                'drivers' => $this->options->drivers(),
                'sslModes' => $this->options->sslModes(),
                'databaseDefaults' => [
                    'host' => (string) config('verso.install.database.host'),
                    'database' => (string) config('verso.install.database.name'),
                    'sqlite_path' => (string) config('verso.install.database.sqlite_path'),
                    'charset' => (string) config('verso.install.database.charset'),
                    'collation' => (string) config('verso.install.database.collation'),
                    'sslmode' => (string) config('verso.install.database.ssl_mode'),
                ],
                'detected' => $this->detectedDatabase($tester),
            ],
            WizardStep::Site => [
                'locales' => $this->options->locales(),
                'languages' => $this->options->languages(),
                'timezones' => $this->options->timezones(),
                'passwordMinLength' => (int) config('verso.install.password_min_length'),
            ],
            WizardStep::Services => ['services' => $this->options->services()],
            WizardStep::Content => ['themes' => $this->options->themes(), 'plugins' => $this->options->plugins()],
            WizardStep::Review => $this->reviewProps(),
            WizardStep::Install => ['status' => $pipeline->status()->toArray()],
        };
    }

    /**
     * @return array{summary: array<string, array<string, string>>, editSteps: array<string, string>}
     */
    protected function reviewProps(): array
    {
        $editSteps = [];

        foreach (InstallPlan::SECTION_STEPS as $section => $step) {
            $editSteps[Translate::text($section)] = $step;
        }

        return ['summary' => $this->store->plan()->summary(), 'editSteps' => $editSteps];
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkRequirements(Requirements $requirements): array
    {
        if (! $requirements->passes()) {
            throw ValidationException::withMessages(['requirements' => __('Fix the failed server checks first, then check again.')]);
        }

        return ['checked_at' => now()->toIso8601String()];
    }

    /**
     * Validate the fields, then test the connection. Saves only a database that works.
     *
     * @return array<string, mixed>
     */
    protected function checkDatabase(Request $request, DatabaseConnectionTester $tester, DatabaseCreator $creator): array
    {
        if ($request->boolean('use_detected') && $this->defaults->hasEnvironmentServer()) {
            $input = $this->defaults->environmentDatabase()->toArray();
        } else {
            $input = SecretFields::restore(WizardStep::Database, $request->all(), $this->store->get(WizardStep::Database));
        }

        $values = $this->rules->validate(WizardStep::Database, $input);
        $settings = DatabaseSettings::fromArray($values);
        $report = $tester->test($settings);

        if (! $report->ok && $report->problem === ConnectionProblem::UnknownDatabase && $request->boolean('create_database')) {
            try {
                $creator->create($settings);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['connection' => $exception->getMessage()]);
            }

            $report = $tester->test($settings);
        }

        if (! $report->ok) {
            throw ValidationException::withMessages(['connection' => trim($report->message.' '.$report->problem?->hint())]);
        }

        if ($report->hasVersoTables()) {
            throw ValidationException::withMessages(['prefix' => __('This database already has Verso tables (:tables). Pick a table prefix or an empty database.', ['tables' => implode(', ', $report->versoTables)])]);
        }

        return $settings->toArray();
    }

    /**
     * DB_* values that already work, so Docker users can confirm them in one click.
     *
     * @return array<string, mixed>|null
     */
    protected function detectedDatabase(DatabaseConnectionTester $tester): ?array
    {
        if (! $this->defaults->hasEnvironmentServer()) {
            return null;
        }

        $settings = $this->defaults->environmentDatabase();
        $report = $tester->test($settings);

        return $report->ok && ! $report->hasVersoTables()
            ? ['settings' => $settings->masked(), 'message' => $report->message]
            : null;
    }

    // The language picked last (language menu or first step), or the best match for the browser.
    protected function language(Request $request): string
    {
        $locales = app(Locales::class);
        $picked = $request->hasSession() ? $request->session()->get(AdminLocale::SESSION_KEY) : null;
        $stored = $this->store->get(WizardStep::Welcome)['language'] ?? null;

        return $locales->normalize(is_string($picked) ? $picked : null)
            ?? $locales->normalize(is_string($stored) ? $stored : null)
            ?? $locales->fromAcceptLanguage($request->header('Accept-Language'))
            ?? $locales->fallback();
    }
}
