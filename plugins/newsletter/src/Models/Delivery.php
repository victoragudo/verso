<?php

namespace Verso\Plugins\Newsletter\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Verso\Plugins\Newsletter\Enums\DeliveryStatus;

// One campaign email to one subscriber. Its token is used by the tracking links.
/**
 * @property int $id
 * @property int $campaign_id
 * @property int $subscriber_id
 * @property string $token
 * @property DeliveryStatus $status
 * @property string|null $error
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $clicked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Subscriber $subscriber
 */
class Delivery extends Model
{
    protected $table = 'newsletter_deliveries';

    protected $fillable = ['campaign_id', 'subscriber_id', 'token', 'status', 'error', 'sent_at', 'opened_at', 'clicked_at'];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Delivery $delivery): void {
            $delivery->token = $delivery->token ?: Str::random(48);
        });
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Subscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    // The first open counts. Later opens change nothing.
    public function markOpened(): void
    {
        if ($this->opened_at === null) {
            $this->update(['opened_at' => now()]);
        }
    }

    // A click also proves an open, because images may be blocked.
    public function markClicked(): void
    {
        $this->update(['clicked_at' => $this->clicked_at ?? now(), 'opened_at' => $this->opened_at ?? now()]);
    }
}
