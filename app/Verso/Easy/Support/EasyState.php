<?php

namespace App\Verso\Easy\Support;

use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Carbon;

// Reads and saves the Easy mode settings of the site, like the starter site that was chosen.
class EasyState
{
    // Key of the chosen starter site.
    public const STARTER = 'easy.starter';

    // When the starter site was installed (ISO 8601, UTC).
    public const STARTED_AT = 'easy.started_at';

    // Style preset and font pair picked on "Make it yours", and when the style was saved last (ISO 8601, UTC).
    public const STYLE_PRESET = 'easy.style_preset';

    public const FONT_PAIR = 'easy.font_pair';

    public const STYLED_AT = 'easy.styled_at';

    // When the site went live with "Go live" (ISO 8601, UTC), and the id of the privacy page.
    public const LAUNCHED_AT = 'easy.launched_at';

    public const PRIVACY_PAGE = 'easy.privacy_page';

    public function __construct(protected Settings $settings) {}

    // Key of the starter site that was installed last, or null when none was chosen yet.
    public function starter(): ?string
    {
        return $this->text(self::STARTER);
    }

    public function hasStarter(): bool
    {
        return $this->starter() !== null;
    }

    public function rememberStarter(string $key): void
    {
        $this->settings->setMany([
            self::STARTER => $key,
            self::STARTED_AT => Carbon::now('UTC')->toIso8601String(),
        ]);
    }

    public function stylePreset(): ?string
    {
        return $this->text(self::STYLE_PRESET);
    }

    public function fontPair(): ?string
    {
        return $this->text(self::FONT_PAIR);
    }

    public function hasStyled(): bool
    {
        return $this->text(self::STYLED_AT) !== null;
    }

    // Records the picks of "Make it yours" and when they were saved.
    public function rememberStyle(?string $preset, ?string $fontPair): void
    {
        $this->settings->setMany([
            self::STYLE_PRESET => $preset,
            self::FONT_PAIR => $fontPair,
            self::STYLED_AT => Carbon::now('UTC')->toIso8601String(),
        ]);
    }

    public function hasLaunched(): bool
    {
        return $this->text(self::LAUNCHED_AT) !== null;
    }

    public function rememberLaunch(): void
    {
        $this->settings->set(self::LAUNCHED_AT, Carbon::now('UTC')->toIso8601String());
    }

    // Id of the page made with "Create a privacy page", or null.
    public function privacyPageId(): ?int
    {
        $value = $this->settings->get(self::PRIVACY_PAGE);

        return is_numeric($value) ? (int) $value : null;
    }

    public function rememberPrivacyPage(int $id): void
    {
        $this->settings->set(self::PRIVACY_PAGE, $id);
    }

    protected function text(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
