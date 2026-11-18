<?php

namespace Verso\Plugins\Shop\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Verso\Plugins\Shop\Enums\OrderStatus;

/**
 * One step in the status history of an order.
 *
 * @property int $id
 * @property int $order_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property int|null $user_id
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $user
 */
class OrderStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'shop_order_status_changes';

    protected $fillable = ['from_status', 'to_status', 'user_id', 'note'];

    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
