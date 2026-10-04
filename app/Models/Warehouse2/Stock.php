<?php

namespace App\Models\Warehouse2;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $connection = 'mysql'; // atau 'warehouse2'
    protected $table = 'warehouse2_stock';
    
    protected $fillable = [
        'item_id',
        'quantity',
        'location',
        'last_updated'
    ];

    protected $casts = [
        'quantity' => 'float',
        'last_updated' => 'datetime'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}