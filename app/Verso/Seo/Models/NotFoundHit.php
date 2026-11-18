<?php

namespace App\Verso\Seo\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One address that was not found, with how often and when it was asked for.
 *
 * @property int $id
 * @property string $path
 * @property int $hits
 * @property string|null $referrer_host
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 */
#[Table('seo_not_found')]
#[Fillable(['path', 'hits', 'referrer_host', 'first_seen_at', 'last_seen_at'])]
class NotFoundHit extends Model
{
    public $timestamps = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'hits' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
