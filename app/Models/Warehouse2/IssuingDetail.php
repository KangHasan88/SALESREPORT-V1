<?php

namespace App\Models\Warehouse2;

use Illuminate\Database\Eloquent\Model;

class IssuingDetail extends Model
{
    protected $connection = 'mysql'; // atau 'warehouse2'
    protected $table = 'warehouse2_issuing_detail';
    
    protected $fillable = [
        'issuing_id',
        'item_id',
        'quantity',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'float'
    ];

    public function issuing()
    {
        return $this->belongsTo(Issuing::class, 'issuing_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}