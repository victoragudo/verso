<?php

namespace App\Verso\Install\Console;

use App\Verso\Install\Database\ConnectionProblem;
use App\Verso\Install\Database\DatabaseConnectionTester;
use App\Verso\Install\Database\DatabaseSettings;
use App\Verso\Install\InstallState;
use App\Verso\Install\Pipeline\InstallPipeline;
use App\Verso\Install\Pipeline\InstallProgress;
use App\Verso\Install\Plan\InstallPlan;
use App\Verso\Install\Requirement;
use App\Verso\Install\Requirements;
use App\Verso\Install\Wizard\StepRules;
use App\Verso\Install\Wizard\WizardDefaults;
use App\Verso\Install\Wizard\WizardOptions;
use App\Verso\Install\Wizard\WizardStep;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

// The command line install wizard. It asks the same questions as the web wizard, or reads flags for Docker and CI.
class InstallCommand extends Command
{
    protected $signature = 'verso:install
        {--db-driver= : Database driver: sqlite, mysql, mariadb or pgsql}
        {--db-host= : Database host}
        {--db-port= : Database port}
        {--db-database= : Database name, or the SQLite file path}
        {--db-username= : Database user}
        {--db-password= : Database password}
        {--db-prefix= : Table prefix}
        {--db-sslmode= : PostgreSQL SSL mode}
        {--site-name= : Name of the site}
        {--site-url= : Address of the site, like https://example.com}
        {--tagline= : Short line under the site name}
        {--locale= : Site language, like en or es}
        {--timezone= : Site time zone, like Europe/Madrid}
        {--admin-name= : Name of the admin user}
        {--admin-email= : Email of the admin user}
        {--admin-password= : Password of the admin user}
        {--mail= : Mailer: log, smtp or sendmail}
        {--mail-host= : SMTP host}
        {--mail-port= : SMTP port}
        {--mail-encryption= : SMTP encryption: tls, ssl or none}
        {--mail-username= : SMTP user}
        {--mail-password= : SMTP password}
        {--mail-from= : Sender address}
        {--cache= : Cache driver}
        {--session= : Session driver}
        {--queue= : Queue driver}
        {--demo : Add demo content}
        {--theme= : Theme to use}
        {--plugins= : Plugins to turn on, comma separated}
        {--force : Install again even when Verso looks installed}';

    protected $description = 'Install Verso: check the server, set up the database, create the admin and the site';

    /** @var array<string, array<string, mixed>> */
    protected array $answers = [];

    public function handle(
        Requirements $requirements,
        InstallState $state,
        StepRules $rules,
        WizardOptions $options,
        WizardDefaults $defaults,
        DatabaseConnectionTester $tester,
        InstallPipeline $pipeline,
        InstallProgress $progress,
    ): int {
        if ($state->isInstalled() && $this->laravel->environment('production')) {
            $this->components->error('Verso is already installed and this is a production site. The installer will not run again.');

            return self::FAILURE;
        }

        if ($state->isInstalled() && ! (bool) $this->option('force')) {
            $this->components->error('Verso is already installed. Use --force to install again.');

            return self::FAILURE;
        }

        if (! $this->checkRequirements($requirements)) {
            return self::FAILURE;
        }

        try {
            $this->askDatabase($rules, $options, $defaults, $tester);
            $this->askSite($rules, $options, $defaults);
            $this->askServices($rules, $options, $defaults);
            $this->askContent($rules, $options);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        } catch (DatabaseNotReady $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $plan = InstallPlan::fromSteps($this->answers);
        $this->showSummary($plan);

        if ($this->input->isInteractive() && ! confirm('Install Verso now?', default: true)) {
            $this->components->warn('Nothing was installed.');

            return self::FAILURE;
        }

        return $this->install($plan, $pipeline, $progress);
    }

    protected function checkRequirements(Requirements $requirements): bool
    {
        foreach ($requirements->check() as $requirement) {
            $this->components->twoColumnDetail($requirement->label, $requirement->passed ? '<fg=green>OK</>' : '<fg=red>FAIL</>');
        }

        $failures = $requirements->failures();

        foreach ($failures as $failure) {
            $this->components->bulletList([$failure->label.': '.$failure->fix]);
        }

        if ($failures !== []) {
            $this->components->error('Fix these checks first: '.implode(', ', array_map(fn (Requirement $requirement): string => $requirement->label, $failures)).'.');

            return false;
        }

        return true;
    }

    // Ask for the database until the connection works (or fail at once without interaction).
    protected function askDatabase(StepRules $rules, WizardOptions $options, WizardDefaults $defaults, DatabaseConnectionTester $tester): void
    {
        $environment = $defaults->environmentDatabase()->toArray();
        $drivers = array_column(array_filter($options->drivers(), fn (array $driver): bool => $driver['available']), 'label', 'value');

        while (true) {
            $driver = $this->value('db-driver', fn (): string => (string) select(
                label: 'Which database do you want to use?',
                options: $drivers,
                default: array_key_exists((string) $environment['driver'], $drivers) ? (string) $environment['driver'] : 'sqlite',
                hint: 'SQLite is the easiest choice for a small site.',
            ), (string) $environment['driver']);

            $values = $driver === 'sqlite'
                ? $this->askSqlite($environment)
                : $this->askServer($driver, $environment);

            $values = $rules->validate(WizardStep::Database, $values);
            $settings = DatabaseSettings::fromArray($values);
            $report = spin(fn () => $tester->test($settings), 'Testing the database connection...');

            if ($report->ok && ! $report->hasVersoTables()) {
                $this->components->info($report->message);
                $this->answers['database'] = $settings->toArray();

                return;
            }

            $create = $report->problem === ConnectionProblem::UnknownDatabase
                && (! $this->input->isInteractive() || confirm("The database \"{$settings->database}\" does not exist. Create it during the install?", default: true));

            if ($create) {
                $this->answers['database'] = $settings->toArray();

                return;
            }

            $message = $report->ok ? $report->message : trim($report->message.' '.$report->problem?->hint());

            if (! $this->input->isInteractive()) {
                throw new DatabaseNotReady($message);
            }

            $this->components->error($message);
            $this->forgetDatabaseOptions();
        }
    }

    /**
     * @param  array<string, mixed>  $environment
     * @return array<string, mixed>
     */
    protected function askSqlite(array $environment): array
    {
        $default = $environment['driver'] === 'sqlite' ? (string) $environment['database'] : (string) config('verso.install.database.sqlite_path');

        return [
            'driver' => 'sqlite',
            'database' => $this->value('db-database', fn (): string => text('SQLite database file', default: $default, required: true), $default),
            'prefix' => (string) ($this->option('db-prefix') ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $environment
     * @return array<string, mixed>
     */
    protected function askServer(string $driver, array $environment): array
    {
        $same = $environment['driver'] === $driver;
        $port = (string) ($same && $environment['port'] ? $environment['port'] : config("verso.install.database.drivers.{$driver}.port"));
        $host = $same && $environment['host'] !== '' ? (string) $environment['host'] : (string) config('verso.install.database.host');
        $database = $same && $environment['database'] !== '' ? (string) $environment['database'] : (string) config('verso.install.database.name');
        $username = $same ? (string) $environment['username'] : '';
        $passwordDefault = $same ? (string) $environment['password'] : '';

        return [
            'driver' => $driver,
            'host' => $this->value('db-host', fn (): string => text('Database host', default: $host, required: true), $host),
            'port' => $this->value('db-port', fn (): string => text('Database port', default: $port, required: true), $port),
            'database' => $this->value('db-database', fn (): string => text('Database name', default: $database, required: true), $database),
            'username' => $this->value('db-username', fn (): string => text('Database user', default: $username, required: true), $username),
            'password' => $this->value('db-password', fn (): string => password('Database password', hint: $passwordDefault !== '' ? 'Leave empty to use the password from the environment.' : '') ?: $passwordDefault, $passwordDefault),
            'prefix' => (string) ($this->option('db-prefix') ?? ''),
            'sslmode' => (string) ($this->option('db-sslmode') ?? ($same && $environment['sslmode'] !== '' ? $environment['sslmode'] : config('verso.install.database.ssl_mode'))),
            'charset' => $same && $environment['charset'] !== '' ? (string) $environment['charset'] : (string) config('verso.install.database.charset'),
            'collation' => $same && $environment['collation'] !== '' ? (string) $environment['collation'] : (string) config('verso.install.database.collation'),
        ];
    }

    protected function askSite(StepRules $rules, WizardOptions $options, WizardDefaults $defaults): void
    {
        $site = $defaults->for(WizardStep::Site, (string) (config('verso.install.cli.site_url') ?: config('app.url')));
        $fieldRules = $rules->rules(WizardStep::Site, [], confirmPassword: false);
        $check = fn (string $field): Closure => fn (string $value): ?string => $this->fieldError($field, $value, $fieldRules);
        $siteName = (string) (config('verso.install.cli.site_name') ?: $site['site_name']);

        $values = [
            'site_name' => $this->value('site-name', fn (): string => text('Site name', placeholder: 'Harbor Lane Bakery', default: $siteName, required: true, validate: $check('site_name')), $siteName),
            'tagline' => $this->value('tagline', fn (): string => text('Tagline (optional)', placeholder: 'Fresh bread every morning'), ''),
            'site_url' => $this->value('site-url', fn (): string => text('Site address', default: (string) $site['site_url'], required: true, validate: $check('site_url')), (string) $site['site_url']),
            'locale' => $this->value('locale', fn (): string => (string) select('Site language', $options->languages(), default: 'en'), 'en'),
            'timezone' => $this->value('timezone', fn (): string => text('Time zone', default: (string) $site['timezone'], required: true, validate: $check('timezone')), (string) $site['timezone']),
            'admin_name' => $this->value('admin-name', fn (): string => text('Admin name', default: (string) config('verso.install.cli.admin_name'), required: true), (string) config('verso.install.cli.admin_name')),
            'admin_email' => $this->value('admin-email', fn (): string => text('Admin email', placeholder: 'you@example.com', required: true, validate: $check('admin_email')), (string) config('verso.install.cli.admin_email')),
            'admin_password' => $this->value('admin-password', fn (): string => password('Admin password', required: true, validate: $check('admin_password'), hint: 'At least '.config('verso.install.password_min_length').' characters, with letters and numbers.'), (string) config('verso.install.cli.admin_password')),
        ];

        $this->answers['welcome'] = ['language' => $values['locale']];
        $this->answers['site'] = $rules->validate(WizardStep::Site, $values, confirmPassword: false);
    }

    protected function askServices(StepRules $rules, WizardOptions $options, WizardDefaults $defaults): void
    {
        $values = $defaults->for(WizardStep::Services);
        $flags = ['mail' => 'mailer', 'mail-host' => 'mail_host', 'mail-port' => 'mail_port', 'mail-encryption' => 'mail_encryption', 'mail-username' => 'mail_username', 'mail-password' => 'mail_password', 'mail-from' => 'mail_from_address', 'cache' => 'cache_driver', 'session' => 'session_driver', 'queue' => 'queue_driver'];

        foreach ($flags as $flag => $field) {
            if ($this->option($flag) !== null) {
                $values[$field] = $this->option($flag);
            }
        }

        if ($this->input->isInteractive() && $this->option('mail') === null && confirm('Set up mail, cache, sessions and queue now?', default: false, hint: 'You can skip this: mail goes to the log and everything else uses files.')) {
            $services = $options->services();
            $drivers = fn (string $key): array => array_values(array_filter((array) $services[$key], fn (string $driver): bool => $driver !== 'redis' || (bool) $services['redis_available']));

            $values['mailer'] = select('How should Verso send email?', ['log' => 'Write emails to the log', 'smtp' => 'SMTP server', 'sendmail' => 'Sendmail on this server'], default: 'log');

            if ($values['mailer'] === 'smtp') {
                $values['mail_host'] = text('SMTP host', required: true);
                $values['mail_port'] = text('SMTP port', default: (string) $values['mail_port'], required: true);
                $values['mail_encryption'] = select('SMTP encryption', ['tls', 'ssl', 'none'], default: 'tls');
                $values['mail_username'] = text('SMTP user');
                $values['mail_password'] = password('SMTP password');
                $values['mail_from_address'] = text('Sender address', placeholder: 'hello@example.com', required: true);
            }

            $values['cache_driver'] = select('Cache driver', $drivers('cache_drivers'), default: 'file');
            $values['session_driver'] = select('Session driver', $drivers('session_drivers'), default: 'file');
            $values['queue_driver'] = select('Queue driver', $drivers('queue_drivers'), default: 'sync');
        }

        $this->answers['services'] = $rules->validate(WizardStep::Services, $values) + $values;
    }

    protected function askContent(StepRules $rules, WizardOptions $options): void
    {
        $themes = array_column($options->themes(), 'name', 'slug');
        $plugins = array_column($options->plugins(), 'name', 'slug');
        $interactive = $this->input->isInteractive();

        $demo = (bool) $this->option('demo') || ($interactive && confirm('Add demo content (posts, pages, photos and menus)?', default: false));
        $recommended = $options->recommendedTheme();
        $theme = $this->option('theme') ?? ($interactive && count($themes) > 1 ? select('Theme', $themes, default: $recommended) : $recommended);
        $chosen = $this->option('plugins') !== null
            ? array_values(array_filter(array_map(trim(...), explode(',', (string) $this->option('plugins')))))
            : ($interactive && $plugins !== [] ? multiselect('Plugins to turn on', $plugins, hint: 'Space selects, Enter confirms.') : []);

        $this->answers['content'] = $rules->validate(WizardStep::Content, ['demo' => $demo, 'theme' => (string) $theme, 'plugins' => array_map(strval(...), $chosen)]);
    }

    protected function showSummary(InstallPlan $plan): void
    {
        foreach ($plan->summary() as $section => $rows) {
            $this->components->twoColumnDetail('<fg=gray>'.$section.'</>');

            foreach ($rows as $label => $value) {
                $this->components->twoColumnDetail($label, $value);
            }
        }
    }

    protected function install(InstallPlan $plan, InstallPipeline $pipeline, InstallProgress $progress): int
    {
        $pipeline->start();

        while (true) {
            $next = collect($pipeline->status()->steps)->first(fn (array $step): bool => ! in_array($step['status'], [InstallProgress::DONE, InstallProgress::SKIPPED], true));

            if ($next === null) {
                break;
            }

            $status = spin(fn () => $pipeline->runNext($plan), $next['label'].'...');

            if ($status->failed()) {
                $this->components->error($next['label'].' failed: '.($status->error['message'] ?? ''));
                $this->line('  Fix the problem and run the same command again. Steps that are done will be skipped.');

                return self::FAILURE;
            }

            $this->components->twoColumnDetail($next['label'], '<fg=green>DONE</>');
        }

        $progress->forget();
        $this->components->info("Verso is installed. Open {$plan->site->url}/admin and sign in as {$plan->admin->email}.");

        return self::SUCCESS;
    }

    // A flag value, or the prompt answer, or the fallback when there is no interaction.
    protected function value(string $option, Closure $ask, string $fallback): string
    {
        $flag = $this->option($option);

        if (is_string($flag) && $flag !== '') {
            return $flag;
        }

        if (! $this->input->isInteractive()) {
            return $fallback;
        }

        $answer = $ask();

        return is_scalar($answer) ? (string) $answer : $fallback;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    protected function fieldError(string $field, string $value, array $rules): ?string
    {
        $validator = Validator::make([$field => $value], [$field => $rules[$field] ?? []]);

        return $validator->fails() ? $validator->errors()->first($field) : null;
    }

    // After a failed test in interactive mode, ask every database question again.
    protected function forgetDatabaseOptions(): void
    {
        foreach (['db-driver', 'db-host', 'db-port', 'db-database', 'db-username', 'db-password'] as $option) {
            $this->input->setOption($option, null);
        }
    }
}
