<?php

namespace App\Models\Warehouse2;

use Illuminate\Database\Eloquent\Model;

class Issuing extends Model
{
    protected $connection = 'mysql'; // atau 'warehouse2'
    protected $table = 'warehouse2_issuing';
    
    protected $fillable = [
        'issue_number',
        'issue_date',
        'department',
        'purpose',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function details()
    {
        return $this->hasMany(IssuingDetail::class, 'issuing_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}