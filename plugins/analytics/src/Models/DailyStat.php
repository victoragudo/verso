<?php

namespace Verso\Plugins\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

// Page views and visitors of one day for one dimension value, like a page path or a device.
class DailyStat extends Model
{
    public $timestamps = false;

    protected $table = 'analytics_daily';

    protected $fillable = ['day', 'dimension', 'value', 'views', 'visitors'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['views' => 'integer', 'visitors' => 'integer'];
    }
}
