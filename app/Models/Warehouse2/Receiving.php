<?php

namespace App\Models\Warehouse2;

use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    protected $connection = 'mysql'; // atau 'warehouse2'
    protected $table = 'warehouse2_receiving';
    
    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'supplier',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function details()
    {
        return $this->hasMany(ReceivingDetail::class, 'receiving_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}