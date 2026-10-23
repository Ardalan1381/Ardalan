<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryUnit extends Model
{
    protected $fillable = [
        'name',
        'abbreviation',
        'type',
        'base_factor',
    ];

    protected function casts(): array
    {
        return [
            'base_factor' => 'decimal:6',
        ];
    }
}
