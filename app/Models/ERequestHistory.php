<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ERequestHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'e_request_histories';

    protected $fillable = [
        'e_request_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
