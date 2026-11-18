<?php

namespace App\Verso\System\Server;

// Gives heavy tasks (imports, image work, updates, exports) more memory and time, only when PHP allows it.
class RuntimeLimits
{
    public function __construct(
        protected PhpIni $ini,
        protected int $memoryMb,
        protected int $timeSeconds,
    ) {}

    /**
     * Raise memory_limit and the time limit up to the configured values. Never lowers them.
     *
     * @return array<string, string> what was changed, like ['memory_limit' => '512M']
     */
    public function raiseForHeavyTask(): array
    {
        $changed = [];
        $memory = PhpIni::bytes($this->ini->get('memory_limit'));
        $target = $this->memoryMb * 1024 ** 2;

        if ($this->memoryMb > 0 && $memory !== -1 && $target > $memory && $this->ini->changeableAtRuntime('memory_limit')
            && $this->ini->set('memory_limit', $this->memoryMb.'M')) {
            $changed['memory_limit'] = $this->memoryMb.'M';
        }

        $time = (int) $this->ini->get('max_execution_time');

        if ($this->timeSeconds > 0 && $time > 0 && $this->timeSeconds > $time && $this->ini->changeableAtRuntime('max_execution_time')
            && $this->ini->setTimeLimit($this->timeSeconds)) {
            $changed['max_execution_time'] = (string) $this->timeSeconds;
        }

        return $changed;
    }
}
