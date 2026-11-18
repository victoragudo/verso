<?php

namespace App\Verso\System\Server;

use App\Verso\I18n\Translate;

// Works out the real upload limit: the lowest of the Verso setting, PHP and the web server.
class UploadLimits
{
    public const VERSO = 'verso';

    public const WEB_SERVER = 'web_server';

    public function __construct(
        protected PhpIni $ini,
        protected int $overheadKb,
        protected int $webServerMb = 0,
    ) {}

    // The effective limit for a Verso setting given in kilobytes.
    public function effective(int $configuredKb): UploadLimit
    {
        $post = $this->unlimitedOr(PhpIni::bytes($this->ini->get('post_max_size')));
        $parts = [
            ['key' => self::VERSO, 'label' => __('the Verso setting'), 'bytes' => max(0, $configuredKb) * 1024, 'note' => __('Saved in System settings.')],
            ['key' => 'upload_max_filesize', 'label' => 'PHP upload_max_filesize', 'bytes' => $this->unlimitedOr(PhpIni::bytes($this->ini->get('upload_max_filesize'))), 'note' => __('Biggest single file PHP accepts.')],
            ['key' => 'post_max_size', 'label' => 'PHP post_max_size', 'bytes' => $post === null ? null : max(0, $post - $this->overheadKb * 1024), 'note' => __('Whole request size, minus :size for the other form fields.', ['size' => self::human($this->overheadKb * 1024)])],
            ['key' => self::WEB_SERVER, 'label' => __('the web server body limit'), 'bytes' => $this->webServerMb > 0 ? $this->webServerMb * 1024 ** 2 : null, 'note' => $this->webServerMb > 0 ? __('Entered in System settings.') : __('Not known. PHP cannot read it; enter it in System settings if you know it.')],
        ];

        $bottleneck = self::VERSO;
        $bytes = $parts[0]['bytes'];

        foreach ($parts as $part) {
            if ($part['bytes'] !== null && $part['bytes'] < $bytes) {
                $bytes = $part['bytes'];
                $bottleneck = $part['key'];
            }
        }

        return new UploadLimit($bytes, $bottleneck, $parts);
    }

    // A short size for people, like "8 MB" or "512 KB".
    public static function human(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 && $bytes % (1024 ** 3) === 0 => Translate::icu('{count} GB', ['count' => intdiv($bytes, 1024 ** 3)]),
            $bytes >= 1024 ** 2 => Translate::icu('{count} MB', ['count' => round($bytes / 1024 ** 2, 1)]),
            $bytes >= 1024 => Translate::icu('{count} KB', ['count' => intdiv($bytes, 1024)]),
            default => Translate::icu('{count, plural, one {# byte} other {# bytes}}', ['count' => $bytes]),
        };
    }

    // PHP uses 0 and -1 for "no limit".
    protected function unlimitedOr(int $bytes): ?int
    {
        return $bytes <= 0 ? null : $bytes;
    }
}
