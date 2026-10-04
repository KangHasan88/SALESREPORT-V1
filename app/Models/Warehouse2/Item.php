<?php

namespace App\Models\Warehouse2;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $connection = 'mysql'; // atau 'warehouse2' jika pakai database terpisah
    protected $table = 'warehouse2_items';
    
    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'min_stock',
        'max_stock'
    ];

    public function stock()
    {
        return $this->hasOne(Stock::class, 'item_id');
    }

    public function receivingDetails()
    {
        return $this->hasMany(ReceivingDetail::class, 'item_id');
    }

    public function issuingDetails()
    {
        return $this->hasMany(IssuingDetail::class, 'item_id');
    }
}