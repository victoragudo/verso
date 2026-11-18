<?php

namespace Verso\Plugins\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

// One anonymous page view. "visitor" is a hash that cannot be turned back into an IP address.
class AnalyticsEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'analytics_events';

    protected $fillable = ['day', 'path', 'referrer_host', 'device', 'visitor', 'created_at'];
}
