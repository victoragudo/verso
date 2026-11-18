<?php

namespace Verso\Plugins\Newsletter\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A link found in a campaign, with its click count. Click tracking only sends people to these.
/**
 * @property int $id
 * @property int $campaign_id
 * @property string $url
 * @property int $clicks
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CampaignLink extends Model
{
    protected $table = 'newsletter_links';

    protected $fillable = ['campaign_id', 'url', 'clicks'];

    protected function casts(): array
    {
        return ['clicks' => 'integer'];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
