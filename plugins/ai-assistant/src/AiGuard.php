<?php

namespace Verso\Plugins\AiAssistant;

use App\Models\User;
use App\Verso\Plugins\PluginManager;
use Illuminate\Cache\RateLimiter;
use Verso\Plugins\AiAssistant\Exceptions\AiRateLimited;
use Verso\Plugins\AiAssistant\Exceptions\AiUnavailable;

// Checks that the assistant may send a request: plugin on, key saved, user under the rate limit.
class AiGuard
{
    public const SLUG = 'ai-assistant';

    public function __construct(
        protected PluginManager $plugins,
        protected AiSettings $settings,
        protected RateLimiter $limiter,
        protected int $window,
    ) {}

    // True when the plugin is on and an API key is saved.
    public function available(): bool
    {
        return $this->plugins->isEnabled(self::SLUG) && $this->settings->hasApiKey();
    }

    // Throws when the plugin is off or has no key. Nothing else is checked.
    public function ensureAvailable(): void
    {
        if (! $this->plugins->isEnabled(self::SLUG)) {
            throw AiUnavailable::disabled();
        }

        if (! $this->settings->hasApiKey()) {
            throw AiUnavailable::missingKey();
        }
    }

    // Counts one request for the user, or throws when the limit is reached.
    public function hit(User $user): void
    {
        $key = $this->key($user);

        if ($this->limiter->tooManyAttempts($key, $this->settings->rateLimit())) {
            throw new AiRateLimited($this->limiter->availableIn($key));
        }

        $this->limiter->hit($key, max(1, $this->window));
    }

    // Requests the user can still make in the current window.
    public function remaining(User $user): int
    {
        return $this->limiter->remaining($this->key($user), $this->settings->rateLimit());
    }

    protected function key(User $user): string
    {
        return self::SLUG.':'.$user->getKey();
    }
}
