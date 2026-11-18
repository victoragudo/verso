<?php

namespace App\Verso\Install\Wizard;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

// Validation rules for each wizard step. The web wizard and the verso:install command share them.
class StepRules
{
    protected const DATABASE_NAME = '/^[A-Za-z0-9_][A-Za-z0-9_\-]{0,63}$/';

    protected const HOST = '/^[A-Za-z0-9.\-_:\[\]]+$/';

    protected const IDENTIFIER = '/^[A-Za-z0-9_]*$/';

    public function __construct(
        protected Config $config,
        protected WizardOptions $options,
    ) {}

    /**
     * Validate the input of one step and return the clean values.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(WizardStep $step, array $input, bool $confirmPassword = true): array
    {
        return Validator::make($input, $this->rules($step, $input, $confirmPassword), [], $this->attributes())->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function rules(WizardStep $step, array $input = [], bool $confirmPassword = true): array
    {
        return match ($step) {
            WizardStep::Welcome => ['language' => ['required', Rule::in(array_keys($this->options->languages()))]],
            WizardStep::Database => $this->databaseRules((string) ($input['driver'] ?? '')),
            WizardStep::Site => $this->siteRules($confirmPassword),
            WizardStep::Services => $this->servicesRules($input),
            WizardStep::Content => $this->contentRules(),
            default => [],
        };
    }

    // The admin password rule: long enough, with letters and numbers.
    public function passwordRule(): Password
    {
        return Password::min((int) $this->config->get('verso.install.password_min_length', 10))->letters()->numbers();
    }

    /**
     * @return array<string, mixed>
     */
    protected function databaseRules(string $driver): array
    {
        $rules = [
            'driver' => ['required', Rule::in(array_keys((array) $this->config->get('verso.install.database.drivers', []))), $this->driverLoaded()],
            'prefix' => ['nullable', 'string', 'max:20', 'regex:'.self::IDENTIFIER],
        ];

        if ($driver === 'sqlite') {
            return $rules + ['database' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F]/']];
        }

        $rules += [
            'host' => ['required', 'string', 'max:255', 'regex:'.self::HOST],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'regex:'.self::DATABASE_NAME],
            'username' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:255'],
        ];

        if ($driver === 'pgsql') {
            return $rules + ['sslmode' => ['required', Rule::in($this->options->sslModes())]];
        }

        return $rules + [
            'charset' => ['required', 'string', 'max:32', 'regex:'.self::IDENTIFIER],
            'collation' => ['required', 'string', 'max:64', 'regex:'.self::IDENTIFIER],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function siteRules(bool $confirmPassword): array
    {
        $password = ['required', 'string', 'max:255', $this->passwordRule()];

        if ($confirmPassword) {
            $password[] = 'confirmed';
        }

        return [
            'site_name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'site_url' => ['required', 'string', 'max:255', 'url:http,https'],
            'locale' => ['required', Rule::in($this->options->locales())],
            'timezone' => ['required', 'timezone:all'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'admin_password' => $password,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function servicesRules(array $input): array
    {
        $services = (array) $this->config->get('verso.install.services', []);
        $redis = Rule::requiredIf(in_array('redis', [$input['cache_driver'] ?? null, $input['session_driver'] ?? null, $input['queue_driver'] ?? null], true));

        return [
            'mailer' => ['required', Rule::in((array) ($services['mailers'] ?? []))],
            'mail_host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:255', 'regex:'.self::HOST],
            'mail_port' => ['nullable', 'required_if:mailer,smtp', 'integer', 'between:1,65535'],
            'mail_encryption' => ['nullable', 'required_if:mailer,smtp', Rule::in((array) ($services['smtp_encryptions'] ?? []))],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'required_if:mailer,smtp', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'cache_driver' => ['required', Rule::in((array) ($services['cache_drivers'] ?? [])), $this->redisUsable()],
            'session_driver' => ['required', Rule::in((array) ($services['session_drivers'] ?? [])), $this->redisUsable()],
            'queue_driver' => ['required', Rule::in((array) ($services['queue_drivers'] ?? [])), $this->redisUsable()],
            'redis_host' => ['nullable', 'string', 'max:255', 'regex:'.self::HOST, $redis],
            'redis_port' => ['nullable', 'integer', 'between:1,65535', $redis],
            'redis_password' => ['nullable', 'string', 'max:255'],
            'storage' => ['required', Rule::in(['local', 's3'])],
            's3_key' => ['nullable', 'required_if:storage,s3', 'string', 'max:255'],
            's3_secret' => ['nullable', 'required_if:storage,s3', 'string', 'max:255'],
            's3_region' => ['nullable', 'required_if:storage,s3', 'string', 'max:64'],
            's3_bucket' => ['nullable', 'required_if:storage,s3', 'string', 'max:255'],
            's3_endpoint' => ['nullable', 'url:https,http', 'max:255'],
            's3_path_style' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function contentRules(): array
    {
        return [
            'demo' => ['required', 'boolean'],
            'theme' => ['required', Rule::in(array_column($this->options->themes(), 'slug'))],
            'plugins' => ['present', 'array'],
            'plugins.*' => ['string', 'distinct', Rule::in(array_column($this->options->plugins(), 'slug'))],
        ];
    }

    // The PDO extension of the chosen driver must be loaded.
    protected function driverLoaded(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && ! in_array($value, $this->options->availableDrivers(), true)) {
                $fail(__('PHP cannot talk to this database: its PDO extension is not loaded.'));
            }
        };
    }

    // Redis can only be picked when PHP has a Redis client.
    protected function redisUsable(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === 'redis' && ! (bool) $this->options->services()['redis_available']) {
                $fail(__('Redis needs the phpredis extension or the predis/predis package.'));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return [
            'site_url' => __('site address'),
            'admin_name' => __('admin name'),
            'admin_email' => __('admin email'),
            'admin_password' => __('admin password'),
            'mail_host' => __('SMTP host'),
            'mail_port' => __('SMTP port'),
            'mail_from_address' => __('sender address'),
            'redis_host' => __('Redis host'),
            'redis_port' => __('Redis port'),
            's3_key' => __('access key'),
            's3_secret' => __('secret key'),
            's3_bucket' => __('bucket'),
            's3_region' => __('region'),
        ];
    }
}
