<?php

namespace Verso\Plugins\AiAssistant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tokens one user spent on one day.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $day
 * @property int $requests
 * @property int $input_tokens
 * @property int $output_tokens
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'day', 'requests', 'input_tokens', 'output_tokens'])]
class AiUsage extends Model
{
    protected $table = 'ai_usage';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'day' => 'date',
            'requests' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function totalTokens(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }
}
