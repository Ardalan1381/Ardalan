<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOptionGroup extends Model
{
    use HasTranslations;

    /** @var list<string> fields HasTranslations::translate()/syncTranslations() may override per locale */
    protected array $translatable = ['name'];

    protected $fillable = [
        'product_id',
        'name',
        'selection_type',
        'is_required',
        'min_select',
        'max_select',
        'sort_order',
        'is_active',
        // translations is deliberately excluded — see Category for why.
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'min_select' => 'integer',
            'max_select' => 'integer',
            'is_active' => 'boolean',
            'translations' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class);
    }
}
