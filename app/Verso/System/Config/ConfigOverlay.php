<?php

namespace App\Verso\System\Config;

use App\Verso\Core\Settings\Settings;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Env;
use Throwable;

// Puts saved system settings on top of config() at boot. Works with "config:cache":
// the first values seen (from the files or the cache) are kept as the defaults.
class ConfigOverlay
{
    // Config key that keeps the default value and the env flag of every setting.
    public const BASELINE_KEY = 'verso_system_baseline';

    /** @var array<string, string> setting key => why the saved value was ignored */
    protected array $problems = [];

    protected bool $applied = false;

    public function __construct(
        protected Config $config,
        protected ConfigRegistry $registry,
        protected Settings $settings,
    ) {}

    // Apply every registered setting, then keep applying settings added later (plugins).
    public function boot(): void
    {
        if ($this->applied) {
            return;
        }

        $this->applied = true;
        $this->apply($this->registry->all());
        $this->registry->onAdd(fn (array $parameters) => $this->apply($parameters));
    }

    /**
     * @param  list<ConfigParameter>  $parameters
     */
    public function apply(array $parameters): void
    {
        $baseline = $this->baseline($parameters);

        try {
            $stored = $this->settings->all();
        } catch (Throwable) {
            // No settings table yet (fresh install): the config files stay as they are.
            return;
        }

        foreach ($parameters as $parameter) {
            $this->config->set($parameter->key, $this->resolve($parameter, $baseline[$parameter->key], $stored));
        }
    }

    // The default value of a setting, before any saved value.
    public function defaultValue(ConfigParameter $parameter): mixed
    {
        return $this->baselineFor($parameter)['default'];
    }

    // True when an env var sets the value. The admin then cannot change it.
    public function isSetByEnvironment(ConfigParameter $parameter): bool
    {
        return $this->baselineFor($parameter)['env'];
    }

    /** @return array<string, string> */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * @param  array{default: mixed, env: bool}  $baseline
     * @param  array<string, mixed>  $stored
     */
    protected function resolve(ConfigParameter $parameter, array $baseline, array $stored): mixed
    {
        unset($this->problems[$parameter->key]);

        if ($baseline['env'] || ! array_key_exists($parameter->settingKey(), $stored)) {
            return $baseline['default'];
        }

        $value = $stored[$parameter->settingKey()];
        $problem = $parameter->problem($value);

        if ($problem !== null) {
            $this->problems[$parameter->key] = $problem;

            return $baseline['default'];
        }

        return $parameter->cast($value);
    }

    /**
     * @return array{default: mixed, env: bool}
     */
    protected function baselineFor(ConfigParameter $parameter): array
    {
        return $this->baseline([$parameter])[$parameter->key];
    }

    /**
     * Read the saved baseline, adding the settings that are not in it yet.
     *
     * @param  list<ConfigParameter>  $parameters
     * @return array<string, array{default: mixed, env: bool}>
     */
    protected function baseline(array $parameters): array
    {
        /** @var array<string, array{default: mixed, env: bool}> $baseline */
        $baseline = (array) $this->config->get(self::BASELINE_KEY, []);
        $changed = false;

        foreach ($parameters as $parameter) {
            if (! isset($baseline[$parameter->key])) {
                $baseline[$parameter->key] = [
                    'default' => $this->config->get($parameter->key),
                    'env' => $parameter->env !== null && Env::get($parameter->env) !== null,
                ];
                $changed = true;
            }
        }

        if ($changed) {
            $this->config->set(self::BASELINE_KEY, $baseline);
        }

        return $baseline;
    }
}
