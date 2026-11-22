<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'price',
        'stock_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function nextSku(): string
    {
        $lastId = static::max('id') ?? 0;

        return 'PRD-'.str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT);
    }
}
