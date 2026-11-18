<?php

namespace App\Verso\Install\Plan;

use App\Verso\Install\Database\DatabaseSettings;

// Everything the install pipeline needs, collected by the web wizard or the command.
final readonly class InstallPlan
{
    public const MASK = '••••••••';

    public const SECTION_STEPS = [
        'Database' => 'database',
        'Site' => 'site',
        'Admin account' => 'site',
        'Services' => 'services',
        'Content' => 'content',
    ];

    public function __construct(
        public string $language,
        public DatabaseSettings $database,
        public SiteDetails $site,
        public AdminAccount $admin,
        public ServiceSettings $services,
        public ContentChoices $content,
    ) {}

    /**
     * Build a plan from the wizard data (one array per step).
     *
     * @param  array<string, array<string, mixed>>  $steps
     */
    public static function fromSteps(array $steps): self
    {
        return new self(
            language: (string) ($steps['welcome']['language'] ?? 'en'),
            database: DatabaseSettings::fromArray($steps['database'] ?? []),
            site: SiteDetails::fromArray($steps['site'] ?? []),
            admin: AdminAccount::fromArray($steps['site'] ?? []),
            services: ServiceSettings::fromArray($steps['services'] ?? []),
            content: ContentChoices::fromArray($steps['content'] ?? []),
        );
    }

    /**
     * Every line the installer writes to .env.
     *
     * @return array<string, string|int|bool|null>
     */
    public function envValues(): array
    {
        return [
            'APP_NAME' => $this->site->name,
            'APP_URL' => $this->site->url,
            'APP_LOCALE' => $this->site->locale,
            ...$this->database->envValues(),
            ...$this->services->envValues(),
        ];
    }

    /**
     * @return list<string>
     */
    public function secrets(): array
    {
        return array_values(array_filter([$this->database->password, $this->admin->password, ...$this->services->secrets()]));
    }

    // Hide every secret of this plan inside a message, so errors never show or log a password.
    public function redact(string $message): string
    {
        $secrets = $this->secrets();

        return $secrets === [] ? $message : str_replace($secrets, self::MASK, $message);
    }

    /**
     * What the review step shows. Secrets are masked.
     *
     * @return array<string, array<string, string>>
     */
    public function summary(): array
    {
        $database = $this->database;
        $services = $this->services;
        $none = __('None');
        $mask = fn (string $secret): string => $secret === '' ? __('Not set') : self::MASK;

        return [
            __('Database') => $database->isSqlite()
                ? [__('Driver') => 'SQLite', __('File') => $database->database, __('Table prefix') => $database->prefix ?: $none]
                : [
                    __('Driver') => (string) config("verso.install.database.drivers.{$database->driver}.label", $database->driver),
                    __('Server') => $database->host.':'.$database->port,
                    __('Database') => $database->database,
                    __('Username') => $database->username,
                    __('Password') => $mask($database->password),
                    __('Table prefix') => $database->prefix ?: $none,
                ],
            __('Site') => [
                __('Name') => $this->site->name,
                __('Tagline') => $this->site->tagline ?: $none,
                __('Address') => $this->site->url,
                __('Language') => $this->site->locale,
                __('Time zone') => $this->site->timezone,
            ],
            __('Admin account') => [
                __('Name') => $this->admin->name,
                __('Email') => $this->admin->email,
                __('Password') => $mask($this->admin->password),
            ],
            __('Services') => [
                __('Mail') => $services->mailer === 'smtp' ? 'SMTP ('.$services->mailHost.':'.$services->mailPort.')' : ucfirst($services->mailer),
                __('Cache') => $services->cacheDriver,
                __('Sessions') => $services->sessionDriver,
                __('Queue') => $services->queueDriver,
                __('File storage') => $services->storage === 's3' ? 'S3 ('.$services->s3Bucket.')' : __('Local public disk'),
            ],
            __('Content') => [
                __('Demo content') => $this->content->demo ? __('Yes') : __('No, start empty'),
                __('Theme') => $this->content->theme,
                __('Plugins') => $this->content->plugins === [] ? $none : implode(', ', $this->content->plugins),
            ],
        ];
    }
}
