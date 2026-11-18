<?php

namespace Verso\Plugins\Newsletter\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Verso\Plugins\Newsletter\Enums\CampaignStatus;
use Verso\Plugins\Newsletter\Enums\DeliveryStatus;

// One newsletter issue: a subject and blocks, sent once to a list or to everyone.
/**
 * @property int $id
 * @property string $subject
 * @property string|null $preheader
 * @property array<int, array<string, mixed>>|null $blocks
 * @property CampaignStatus $status
 * @property int|null $list_id
 * @property bool $track_opens
 * @property bool $track_clicks
 * @property int $recipients_count
 * @property int|null $created_by
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Campaign extends Model
{
    protected $table = 'newsletter_campaigns';

    protected $attributes = ['status' => 'draft', 'track_opens' => false, 'track_clicks' => false, 'recipients_count' => 0];

    protected $fillable = ['subject', 'preheader', 'blocks', 'status', 'list_id', 'track_opens', 'track_clicks', 'recipients_count', 'created_by', 'started_at', 'sent_at'];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'status' => CampaignStatus::class,
            'track_opens' => 'boolean',
            'track_clicks' => 'boolean',
            'recipients_count' => 'integer',
            'started_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MailingList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * @return HasMany<CampaignLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(CampaignLink::class);
    }

    public function isDraft(): bool
    {
        return $this->status === CampaignStatus::Draft;
    }

    // Subscribed people who get this campaign: the list members, or everyone.
    /** @return Builder<Subscriber> */
    public function recipients(): Builder
    {
        return Subscriber::query()
            ->subscribed()
            ->when($this->list_id !== null, fn (Builder $query) => $query->whereHas('lists', fn (Builder $q) => $q->whereKey($this->list_id)));
    }

    // Closes the campaign once no email is waiting.
    public function finishIfDone(): void
    {
        if ($this->status === CampaignStatus::Sending && ! $this->deliveries()->where('status', DeliveryStatus::Queued)->exists()) {
            $this->update(['status' => CampaignStatus::Sent, 'sent_at' => now()]);
        }
    }

    /**
     * Counts for the stats page.
     *
     * @return array{recipients: int, sent: int, failed: int, queued: int, opens: int, clicks: int}
     */
    public function stats(): array
    {
        $deliveries = $this->deliveries();

        return [
            'recipients' => $this->recipients_count,
            'sent' => (clone $deliveries)->where('status', DeliveryStatus::Sent)->count(),
            'failed' => (clone $deliveries)->where('status', DeliveryStatus::Failed)->count(),
            'queued' => (clone $deliveries)->where('status', DeliveryStatus::Queued)->count(),
            'opens' => (clone $deliveries)->whereNotNull('opened_at')->count(),
            'clicks' => (clone $deliveries)->whereNotNull('clicked_at')->count(),
        ];
    }
}
