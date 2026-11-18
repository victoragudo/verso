<?php

namespace Verso\Plugins\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

// The random salt of one day. It is deleted when the day is over.
class VisitorSalt extends Model
{
    public $timestamps = false;

    protected $table = 'analytics_salts';

    protected $fillable = ['day', 'salt'];
}
