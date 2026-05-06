<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorEvent extends Model
{
    public $timestamps = false;     // only created_at, set by DB default

    protected $fillable = [
        'session_id', 'ip_hash', 'path', 'referrer',
        'utm_source', 'utm_medium', 'utm_campaign',
        'organization_id', 'user_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
