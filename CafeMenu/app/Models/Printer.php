<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    protected $fillable = [
        'name',
        'connection_type',
        'ip_address',
        'port',
        'device_path',
        'paper_width',
        'farsi_codepage_table',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'farsi_codepage_table' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function routes(): HasMany
    {
        return $this->hasMany(PrintRoute::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Characters-per-line, the standard figure for common 203dpi thermal printers at each paper width. */
    public function columns(): int
    {
        return $this->paper_width === '58mm' ? 32 : 48;
    }
}
