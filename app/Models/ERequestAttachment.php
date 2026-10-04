<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ERequestAttachment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'e_request_attachments';

    protected $fillable = [
        'e_request_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
