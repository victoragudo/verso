<?php

namespace App\Verso\Easy\Launch;

use Closure;
use InvalidArgumentException;

// One row of the launch checklist: what to do, why, where to fix it and how to tell it is done.
final readonly class LaunchCheck
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9-]*$/';

    public const METHODS = ['get', 'post'];

    /**
     * @param  string|Closure(): string  $href  the page that fixes it, or the address the button posts to
     * @param  Closure(): bool  $done  true when the step is done
     * @param  string|null  $action  the button text, null for "Do it"
     * @param  (Closure(): bool)|null  $applies  false leaves the row out, like a logo on a theme without one
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string|Closure $href,
        public Closure $done,
        public int $order = 100,
        public ?string $action = null,
        public string $method = 'get',
        public ?Closure $applies = null,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("Launch check keys use a-z, 0-9 and \"-\", [{$key}] does not.");
        }

        if (! in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException("Launch check [{$key}] uses an unknown method [{$method}].");
        }
    }

    public function applies(): bool
    {
        return $this->applies === null || ($this->applies)();
    }

    public function isDone(): bool
    {
        return ($this->done)();
    }

    /**
     * The row for the dashboard card.
     *
     * @return array{key: string, label: string, description: string, href: string, action: string|null, method: string, done: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'href' => $this->href instanceof Closure ? (string) ($this->href)() : $this->href,
            'action' => $this->action,
            'method' => $this->method,
            'done' => $this->isDone(),
        ];
    }
}
