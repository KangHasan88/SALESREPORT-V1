<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PgItem extends Model
{
    protected $connection = 'pgsql2';
    protected $table = 'tb_skb080_1mmara';
    protected $primaryKey = 'id_items';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'mtart',
        'meins',
        'item_name'
    ];
}
