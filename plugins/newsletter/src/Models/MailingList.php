<?php

namespace Verso\Plugins\Newsletter\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

// A group of subscribers, like "Weekly digest" or "Event news".
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class MailingList extends Model
{
    protected $table = 'newsletter_lists';

    protected $fillable = ['name', 'slug', 'description'];

    protected static function booted(): void
    {
        static::saving(function (MailingList $list): void {
            $list->slug = Str::slug(filled($list->slug) ? $list->slug : $list->name);
        });
    }

    /**
     * @return BelongsToMany<Subscriber, $this>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Subscriber::class, 'newsletter_list_subscriber', 'list_id', 'subscriber_id');
    }
}
