<?php

namespace App\Verso\System\Actions;

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\System\Config\ApplyAction;
use App\Verso\System\Config\ConfigOverlay;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\ConfigRegistry;
use App\Verso\System\Models\ConfigChange;
use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;

// Saves or resets system settings, writes the audit log and runs the apply actions.
class SaveSystemSettings
{
    public function __construct(
        protected ConfigRegistry $registry,
        protected ConfigOverlay $overlay,
        protected Settings $settings,
        protected Config $config,
        protected RunApplyActions $applyActions,
    ) {}

    /**
     * Save checked values (in the stored unit). A value equal to the default removes the saved value.
     *
     * @param  array<string, mixed>  $values  config key => value
     * @return array{changed: list<string>, applied: list<string>}
     */
    public function handle(User $user, array $values): array
    {
        $changed = [];
        $actions = [];

        foreach ($values as $key => $value) {
            $parameter = $this->parameter($key);
            $value = $parameter->cast($value);
            $old = $this->config->get($key);

            if ($value === $this->overlay->defaultValue($parameter)) {
                $this->settings->forget($parameter->settingKey());
            } else {
                $this->settings->set($parameter->settingKey(), $value);
            }

            if ($old === $value) {
                continue;
            }

            $this->config->set($key, $value);
            $this->log($user, ConfigChange::UPDATE, $key, $old, $value);
            $changed[] = $key;
            array_push($actions, ...$parameter->apply);
        }

        return ['changed' => $changed, 'applied' => $this->run($actions)];
    }

    // Go back to the default value of one setting.
    public function reset(User $user, string $key): void
    {
        $parameter = $this->parameter($key);
        $old = $this->config->get($key);
        $default = $this->overlay->defaultValue($parameter);

        $this->settings->forget($parameter->settingKey());
        $this->config->set($key, $default);

        if ($old !== $default) {
            $this->log($user, ConfigChange::RESET, $key, $old, $default);
            $this->run($parameter->apply);
        }
    }

    protected function parameter(string $key): ConfigParameter
    {
        $parameter = $this->registry->find($key);

        if ($parameter === null || $this->overlay->isSetByEnvironment($parameter)) {
            throw new InvalidArgumentException("The system setting [{$key}] cannot be changed here.");
        }

        return $parameter;
    }

    /**
     * @param  list<ApplyAction>  $actions
     * @return list<string>
     */
    protected function run(array $actions): array
    {
        return $actions === [] ? [] : $this->applyActions->handle($actions);
    }

    protected function log(User $user, string $action, string $key, mixed $old, mixed $new): void
    {
        ConfigChange::query()->create(['user_id' => $user->id, 'action' => $action, 'key' => $key, 'old_value' => $old, 'new_value' => $new]);
    }
}
