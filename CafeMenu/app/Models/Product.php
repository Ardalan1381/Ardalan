<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    use HasFactory, HasMedia, HasTranslations;

    /** @var list<string> fields HasTranslations::translate()/syncTranslations() may override per locale */
    protected array $translatable = ['name', 'description'];

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'base_price',
        'discount_price',
        'is_available',
        'is_active',
        'label',
        'sort_order',
        // translations is deliberately excluded — see Category for why.
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'discount_price' => 'integer',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'translations' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class);
    }

    public function recipeItems(): MorphMany
    {
        return $this->morphMany(RecipeItem::class, 'recipable');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function effectivePrice(): int
    {
        return $this->discount_price ?? $this->base_price;
    }

    /**
     * The price and currency to show for a given locale (defaults to the
     * current one): a manually-entered override for that locale if staff
     * set one and it's still active, otherwise the home price/currency.
     * Never used for order pricing — CartPricer always prices in the home
     * currency regardless of which locale a customer is browsing in.
     *
     * Pass the already-loaded $business (and eager-load the `prices`
     * relation) when calling this per-product in a menu listing loop —
     * otherwise each card re-queries BusinessSetting::current() and, for a
     * non-home locale, this product's price override.
     *
     * @return array{amount: int, original_amount: ?int, currency_code: string, currency_symbol: ?string, minor_units: int, is_home: bool}
     */
    public function priceFor(?BusinessSetting $business = null, ?string $locale = null): array
    {
        $business ??= BusinessSetting::current();
        $currency = $business->currencyFor($locale);

        if (! $currency['is_home']) {
            $override = $this->relationLoaded('prices')
                ? $this->prices->firstWhere('locale', $locale ?? app()->getLocale())
                : $this->prices()->where('locale', $locale ?? app()->getLocale())->first();

            if ($override) {
                return [
                    'amount' => $override->effectivePrice(),
                    'original_amount' => $override->discount_price !== null ? $override->price : null,
                    'currency_code' => $currency['code'],
                    'currency_symbol' => $currency['symbol'],
                    'minor_units' => $currency['minor_units'],
                    'is_home' => false,
                ];
            }
        }

        return [
            'amount' => $this->effectivePrice(),
            'original_amount' => $this->discount_price !== null ? $this->base_price : null,
            'currency_code' => $business->currency,
            'currency_symbol' => null,
            'minor_units' => $business->currency_minor_units,
            'is_home' => true,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }
}
