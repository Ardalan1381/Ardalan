<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductOption extends Model
{
    use HasTranslations;

    /** @var list<string> fields HasTranslations::translate()/syncTranslations() may override per locale */
    protected array $translatable = ['name'];

    protected $fillable = [
        'product_option_group_id',
        'name',
        'price_delta',
        'sort_order',
        'is_active',
        // translations is deliberately excluded — see Category for why.
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
            'is_active' => 'boolean',
            'translations' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }

    public function recipeItems(): MorphMany
    {
        return $this->morphMany(RecipeItem::class, 'recipable');
    }
}
