<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ERequest extends Model
{
    use SoftDeletes;

    protected $table = 'e_request_requests';

    protected $fillable = [
        'request_number',
        'service_key',
        'request_type_key',
        'workflow_key',
        'requesting_department',
        'owner_department',
        'requester_id',
        'assigned_to',
        'title',
        'description',
        'priority',
        'status',
        'payload',
        'metadata',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function histories()
    {
        return $this->hasMany(ERequestHistory::class, 'e_request_id');
    }

    public function attachments()
    {
        return $this->hasMany(ERequestAttachment::class, 'e_request_id');
    }
}
