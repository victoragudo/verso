<?php

namespace Verso\Plugins\AiAssistant;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Verso\Plugins\AiAssistant\Models\AiUsage;

// Adds the tokens of each answer to the user's row for today (UTC).
class UsageLog
{
    public function record(User $user, AiResponse $response): void
    {
        $day = Carbon::now('UTC')->startOfDay();
        $amounts = ['requests' => 1, 'input_tokens' => $response->inputTokens, 'output_tokens' => $response->outputTokens];

        if ($this->add($user, $day, $amounts) > 0) {
            return;
        }

        try {
            AiUsage::query()->create(['user_id' => $user->getKey(), 'day' => $day, ...$amounts]);
        } catch (UniqueConstraintViolationException) {
            // Another request made the row first, so add to it.
            $this->add($user, $day, $amounts);
        }
    }

    /**
     * @param  array<string, int>  $amounts
     */
    protected function add(User $user, Carbon $day, array $amounts): int
    {
        return AiUsage::query()
            ->where('user_id', $user->getKey())
            ->whereDate('day', $day->toDateString())
            ->incrementEach($amounts);
    }
}
