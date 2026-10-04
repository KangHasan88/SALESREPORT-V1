<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'module',
        'action',
        'description',
        'risk_level',
        'method',
        'url',
        'route_name',
        'ip_address',
        'user_agent',
        'status_code',
        'request_data',
        'context_data',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'request_data' => 'array',
        'context_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
