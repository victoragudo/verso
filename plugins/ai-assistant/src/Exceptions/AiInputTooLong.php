<?php

namespace Verso\Plugins\AiAssistant\Exceptions;

use App\Verso\I18n\Translate;
use Illuminate\Support\Number;

// The text or image is too big to send in one request.
class AiInputTooLong extends AiException
{
    public static function text(int $limit): self
    {
        return new self(Translate::icu('ai-assistant::{limit, plural, one {The text is too long for the assistant (more than # character). Select a shorter part.} other {The text is too long for the assistant (more than # characters). Select a shorter part.}}', ['limit' => $limit]));
    }

    public static function image(int $bytes): self
    {
        $megabytes = round($bytes / 1024 / 1024, 1);

        return new self(__('ai-assistant::The image is too big for the assistant (more than :size MB).', ['size' => Number::format($megabytes, maxPrecision: 1, locale: app()->getLocale())]));
    }

    public static function segments(int $limit): self
    {
        return new self(__('ai-assistant::The entry has too many text pieces to translate at once (more than :limit).', ['limit' => Number::format($limit, locale: app()->getLocale())]));
    }

    public static function sectionPieces(int $limit): self
    {
        return new self(__('ai-assistant::This section has too many texts for the helper (more than :limit). Split it into smaller sections.', ['limit' => Number::format($limit, locale: app()->getLocale())]));
    }

    public function status(): int
    {
        return 422;
    }
}
