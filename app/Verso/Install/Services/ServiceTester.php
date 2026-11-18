<?php

namespace App\Verso\Install\Services;

use App\Verso\Install\Plan\ServiceSettings;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Mail\MailManager;
use Illuminate\Redis\RedisManager;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Throwable;

// Tests mail, Redis and S3 settings before they are saved. Messages never contain secrets.
class ServiceTester
{
    protected const TEST_NAME = 'verso_install_test';

    public function __construct(
        protected Config $config,
        protected MailManager $mail,
        protected RedisManager $redis,
        protected FilesystemManager $storage,
        protected int $timeout,
    ) {}

    public function redisAvailable(): bool
    {
        return extension_loaded('redis') || class_exists('Predis\Client');
    }

    public function s3Available(): bool
    {
        return class_exists(AwsS3V3Adapter::class);
    }

    // Send a short email with the given settings.
    public function sendTestMail(ServiceSettings $settings, string $to): ServiceTestResult
    {
        $name = self::TEST_NAME;
        $this->config->set("mail.mailers.{$name}", match ($settings->mailer) {
            'smtp' => [
                'transport' => 'smtp',
                'scheme' => $settings->mailEncryption === 'ssl' ? 'smtps' : null,
                'host' => $settings->mailHost,
                'port' => $settings->mailPort,
                'username' => $settings->mailUsername ?: null,
                'password' => $settings->mailPassword ?: null,
                'timeout' => $this->timeout,
            ],
            'sendmail' => ['transport' => 'sendmail', 'path' => (string) $this->config->get('verso.install.services.sendmail_path')],
            default => ['transport' => 'log'],
        });

        try {
            $this->mail->purge($name);
            $this->mail->mailer($name)->raw(
                __('This is a test email from the Verso installer. Your mail settings work.'),
                function ($message) use ($settings, $to): void {
                    $message->to($to)->subject(__('Verso test email'));

                    if ($settings->mailFromAddress !== '') {
                        $message->from($settings->mailFromAddress, $settings->mailFromName ?: null);
                    }
                },
            );
        } catch (Throwable $exception) {
            return new ServiceTestResult(false, __('The email could not be sent: :error', ['error' => $this->safe($exception, $settings)]));
        } finally {
            $this->mail->purge($name);
        }

        return new ServiceTestResult(true, __('A test email was sent to :email.', ['email' => $to]));
    }

    public function testRedis(ServiceSettings $settings): ServiceTestResult
    {
        if (! $this->redisAvailable()) {
            return new ServiceTestResult(false, __('PHP has no Redis client. Install the phpredis extension or the predis/predis package.'));
        }

        $name = self::TEST_NAME;
        $this->config->set("database.redis.{$name}", [
            'host' => $settings->redisHost,
            'port' => $settings->redisPort,
            'password' => $settings->redisPassword ?: null,
            'database' => 0,
            'timeout' => $this->timeout,
            'read_timeout' => $this->timeout,
        ]);

        try {
            $this->redis->purge($name);
            $this->redis->connection($name)->command('ping');
        } catch (Throwable $exception) {
            return new ServiceTestResult(false, __('Redis did not answer: :error', ['error' => $this->safe($exception, $settings)]));
        } finally {
            $this->redis->purge($name);
        }

        return new ServiceTestResult(true, __('Redis at :host::port answered.', ['host' => $settings->redisHost, 'port' => $settings->redisPort]));
    }

    // Write a small file to the bucket, read it back and delete it.
    public function testStorage(ServiceSettings $settings, string $probe): ServiceTestResult
    {
        if (! $this->s3Available()) {
            return new ServiceTestResult(false, __('S3 storage needs the league/flysystem-aws-s3-v3 package (composer require league/flysystem-aws-s3-v3).'));
        }

        $contents = 'verso '.bin2hex(random_bytes(8));

        try {
            $disk = $this->storage->build($settings->s3Config() + ['http' => ['connect_timeout' => $this->timeout, 'timeout' => $this->timeout]]);
            $disk->put($probe, $contents);
            $readBack = $disk->get($probe);
            $disk->delete($probe);
        } catch (Throwable $exception) {
            return new ServiceTestResult(false, __('The bucket test failed: :error', ['error' => $this->safe($exception, $settings)]));
        }

        return $readBack === $contents
            ? new ServiceTestResult(true, __('Wrote, read and removed a test file in the bucket :bucket.', ['bucket' => $settings->s3Bucket]))
            : new ServiceTestResult(false, __('The test file came back changed. Check the endpoint and bucket.'));
    }

    protected function safe(Throwable $exception, ServiceSettings $settings): string
    {
        $secrets = $settings->secrets();
        $message = $exception->getMessage();

        return $secrets === [] ? $message : str_replace($secrets, '••••••••', $message);
    }
}
