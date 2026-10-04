<?php

namespace App\Models\Warehouse2;

use Illuminate\Database\Eloquent\Model;

class ReceivingDetail extends Model
{
    protected $connection = 'mysql'; // atau 'warehouse2'
    protected $table = 'warehouse2_receiving_detail';
    
    protected $fillable = [
        'receiving_id',
        'item_id',
        'quantity',
        'unit_price',
        'total_price'
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'total_price' => 'float'
    ];

    public function receiving()
    {
        return $this->belongsTo(Receiving::class, 'receiving_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}