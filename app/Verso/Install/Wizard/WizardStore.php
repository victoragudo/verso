<?php

namespace App\Verso\Install\Wizard;

use App\Verso\Install\Plan\InstallPlan;
use Illuminate\Contracts\Session\Session;

// Keeps the wizard answers in the (encrypted, file based) session between steps.
class WizardStore
{
    protected const KEY = 'verso_install';

    public function __construct(protected Session $session) {}

    /**
     * @return array<string, mixed>
     */
    public function get(WizardStep $step): array
    {
        return (array) $this->session->get(self::KEY.'.steps.'.$step->value, []);
    }

    /**
     * Save the answers of a step and mark it done.
     *
     * @param  array<string, mixed>  $data
     */
    public function put(WizardStep $step, array $data): void
    {
        $this->session->put(self::KEY.'.steps.'.$step->value, $data);
        $this->session->put(self::KEY.'.done', array_values(array_unique([...$this->done(), $step->value])));
    }

    public function isDone(WizardStep $step): bool
    {
        return in_array($step->value, $this->done(), true);
    }

    /**
     * @return list<string>
     */
    public function done(): array
    {
        return array_values(array_map(strval(...), (array) $this->session->get(self::KEY.'.done', [])));
    }

    // A step can be opened when every step before it is done.
    public function canOpen(WizardStep $step): bool
    {
        foreach (WizardStep::cases() as $earlier) {
            if ($earlier === $step) {
                return true;
            }

            if (! $this->isDone($earlier)) {
                return false;
            }
        }

        return true;
    }

    // The first step that is not done yet.
    public function current(): WizardStep
    {
        foreach (WizardStep::cases() as $step) {
            if (! $this->isDone($step)) {
                return $step;
            }
        }

        return WizardStep::Install;
    }

    public function plan(): InstallPlan
    {
        $steps = [];

        foreach (WizardStep::cases() as $step) {
            $steps[$step->value] = $this->get($step);
        }

        return InstallPlan::fromSteps($steps);
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }
}
