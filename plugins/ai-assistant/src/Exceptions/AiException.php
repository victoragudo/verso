<?php

namespace Verso\Plugins\AiAssistant\Exceptions;

use RuntimeException;

// Base error of the AI assistant. The message is safe to show to the user.
abstract class AiException extends RuntimeException
{
    // HTTP status sent back to the editor.
    abstract public function status(): int;
}
