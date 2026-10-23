<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasMedia, HasTranslations;

    /** @var list<string> fields HasTranslations::translate()/syncTranslations() may override per locale */
    protected array $translatable = ['name', 'description'];

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        // translations is deliberately NOT listed here — like
        // BusinessSetting::payment_gateway_credentials, it must only ever
        // be written through syncTranslations() (a per-locale merge), never
        // a raw mass-assignment that could wipe other locales' entries.
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'translations' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
