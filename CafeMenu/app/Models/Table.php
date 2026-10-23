<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_section_id',
        'name',
        'capacity',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Table $table) {
            if (empty($table->qr_token)) {
                $table->qr_token = self::generateUniqueQrToken();
            }
        });
    }

    public static function generateUniqueQrToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('qr_token', $token)->exists());

        return $token;
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TableSection::class, 'table_section_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    public function waiterCalls(): HasMany
    {
        return $this->hasMany(WaiterCall::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
