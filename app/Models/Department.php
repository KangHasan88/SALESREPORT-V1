<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function options(bool $activeOnly = true): array
    {
        $query = static::query()->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->pluck('name', 'code')->all();
    }
}
