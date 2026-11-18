<?php

namespace Verso\Plugins\Newsletter\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Verso\Plugins\Newsletter\Enums\SubscriberStatus;

// One email address on the newsletter, with its double opt-in state.
/**
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property SubscriberStatus $status
 * @property string $source
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $unsubscribed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Subscriber extends Model
{
    protected $table = 'newsletter_subscribers';

    protected $attributes = ['status' => 'pending', 'source' => 'signup'];

    protected $fillable = ['email', 'name', 'status', 'source', 'confirmed_at', 'unsubscribed_at'];

    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Subscriber $subscriber): void {
            $subscriber->email = mb_strtolower(trim($subscriber->email));
        });
    }

    /**
     * @return BelongsToMany<MailingList, $this>
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(MailingList::class, 'newsletter_list_subscriber', 'subscriber_id', 'list_id');
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /** @param Builder<Subscriber> $query */
    public function scopeSubscribed(Builder $query): void
    {
        $query->where('status', SubscriberStatus::Subscribed);
    }

    public function isSubscribed(): bool
    {
        return $this->status === SubscriberStatus::Subscribed;
    }

    // The double opt-in click: the address is now confirmed.
    public function confirm(): void
    {
        $this->update(['status' => SubscriberStatus::Subscribed, 'confirmed_at' => $this->confirmed_at ?? now(), 'unsubscribed_at' => null]);
    }

    public function unsubscribe(): void
    {
        $this->update(['status' => SubscriberStatus::Unsubscribed, 'unsubscribed_at' => now()]);
    }

    // A signed link that confirms the address. It expires after a few hours.
    public function confirmUrl(): string
    {
        return URL::temporarySignedRoute('newsletter.confirm', now()->addHours((int) config('newsletter.confirm_hours')), ['subscriber' => $this->id]);
    }

    // A signed link that never expires, put in every email.
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $this->id]);
    }
}
