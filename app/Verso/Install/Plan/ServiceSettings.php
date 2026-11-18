<?php

namespace App\Verso\Install\Plan;

// Mail, cache, session, queue and file storage choices.
final readonly class ServiceSettings
{
    public function __construct(
        public string $mailer = 'log',
        public string $mailHost = '',
        public ?int $mailPort = null,
        public string $mailEncryption = 'tls',
        public string $mailUsername = '',
        public string $mailPassword = '',
        public string $mailFromAddress = '',
        public string $mailFromName = '',
        public string $cacheDriver = 'file',
        public string $sessionDriver = 'file',
        public string $queueDriver = 'sync',
        public string $redisHost = '',
        public ?int $redisPort = null,
        public string $redisPassword = '',
        public string $storage = 'local',
        public string $s3Key = '',
        public string $s3Secret = '',
        public string $s3Region = '',
        public string $s3Bucket = '',
        public string $s3Endpoint = '',
        public bool $s3PathStyle = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $int = fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;
        $text = fn (string $key, string $default = ''): string => trim((string) ($data[$key] ?? $default));

        return new self(
            mailer: $text('mailer', 'log'),
            mailHost: $text('mail_host'),
            mailPort: $int('mail_port'),
            mailEncryption: $text('mail_encryption', 'tls'),
            mailUsername: $text('mail_username'),
            mailPassword: (string) ($data['mail_password'] ?? ''),
            mailFromAddress: $text('mail_from_address'),
            mailFromName: $text('mail_from_name'),
            cacheDriver: $text('cache_driver', 'file'),
            sessionDriver: $text('session_driver', 'file'),
            queueDriver: $text('queue_driver', 'sync'),
            redisHost: $text('redis_host'),
            redisPort: $int('redis_port'),
            redisPassword: (string) ($data['redis_password'] ?? ''),
            storage: $text('storage', 'local'),
            s3Key: $text('s3_key'),
            s3Secret: (string) ($data['s3_secret'] ?? ''),
            s3Region: $text('s3_region'),
            s3Bucket: $text('s3_bucket'),
            s3Endpoint: $text('s3_endpoint'),
            s3PathStyle: filter_var($data['s3_path_style'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function usesRedis(): bool
    {
        return in_array('redis', [$this->cacheDriver, $this->sessionDriver, $this->queueDriver], true);
    }

    /**
     * @return list<string>
     */
    public function secrets(): array
    {
        return array_values(array_filter([$this->mailPassword, $this->redisPassword, $this->s3Secret]));
    }

    /**
     * Mail and storage config keys for the running app.
     *
     * @return array<string, mixed>
     */
    public function mailConfig(): array
    {
        return [
            'mail.default' => $this->mailer,
            'mail.mailers.smtp.host' => $this->mailHost,
            'mail.mailers.smtp.port' => $this->mailPort,
            'mail.mailers.smtp.scheme' => $this->mailEncryption === 'ssl' ? 'smtps' : null,
            'mail.mailers.smtp.username' => $this->mailUsername ?: null,
            'mail.mailers.smtp.password' => $this->mailPassword ?: null,
            'mail.from.address' => $this->mailFromAddress ?: config('mail.from.address'),
            'mail.from.name' => $this->mailFromName ?: config('mail.from.name'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function redisConfig(): array
    {
        return [
            'database.redis.default.host' => $this->redisHost,
            'database.redis.default.port' => $this->redisPort,
            'database.redis.default.password' => $this->redisPassword ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function s3Config(): array
    {
        return [
            'driver' => 's3',
            'key' => $this->s3Key,
            'secret' => $this->s3Secret,
            'region' => $this->s3Region,
            'bucket' => $this->s3Bucket,
            'endpoint' => $this->s3Endpoint ?: null,
            'use_path_style_endpoint' => $this->s3PathStyle,
            'throw' => true,
        ];
    }

    /**
     * Lines for the .env file.
     *
     * @return array<string, string|int|bool|null>
     */
    public function envValues(): array
    {
        $values = [
            'MAIL_MAILER' => $this->mailer,
            'CACHE_STORE' => $this->cacheDriver,
            'SESSION_DRIVER' => $this->sessionDriver,
            'QUEUE_CONNECTION' => $this->queueDriver,
        ];

        if ($this->mailer === 'smtp') {
            $values += [
                'MAIL_SCHEME' => $this->mailEncryption === 'ssl' ? 'smtps' : null,
                'MAIL_HOST' => $this->mailHost,
                'MAIL_PORT' => $this->mailPort,
                'MAIL_USERNAME' => $this->mailUsername ?: null,
                'MAIL_PASSWORD' => $this->mailPassword ?: null,
            ];
        }

        if ($this->mailFromAddress !== '') {
            $values['MAIL_FROM_ADDRESS'] = $this->mailFromAddress;
        }

        if ($this->mailFromName !== '') {
            $values['MAIL_FROM_NAME'] = $this->mailFromName;
        }

        if ($this->usesRedis()) {
            $values += ['REDIS_HOST' => $this->redisHost, 'REDIS_PORT' => $this->redisPort, 'REDIS_PASSWORD' => $this->redisPassword ?: null];
        }

        if ($this->storage === 's3') {
            $values += [
                'VERSO_MEDIA_DISK' => 's3',
                'AWS_ACCESS_KEY_ID' => $this->s3Key,
                'AWS_SECRET_ACCESS_KEY' => $this->s3Secret,
                'AWS_DEFAULT_REGION' => $this->s3Region,
                'AWS_BUCKET' => $this->s3Bucket,
                'AWS_ENDPOINT' => $this->s3Endpoint,
                'AWS_USE_PATH_STYLE_ENDPOINT' => $this->s3PathStyle,
            ];
        }

        return $values;
    }
}
