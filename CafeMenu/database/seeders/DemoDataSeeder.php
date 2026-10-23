<?php

namespace Database\Seeders;

use App\Actions\CreateStaffOrderAction;
use App\Actions\Inventory\CreatePurchaseAction;
use App\Actions\Inventory\ReceivePurchaseAction;
use App\Actions\PlaceOrderAction;
use App\Actions\RecordPaymentAction;
use App\Actions\Shifts\OpenShiftAction;
use App\Actions\TransitionOrderStatusAction;
use App\Models\BusinessSetting;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Discount;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\InvoiceTemplate;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRule;
use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\Table;
use App\Models\TableSection;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Realistic demo content for local development/evaluation — a full cafe
 * menu with recipes and stock, tables, staff accounts, customers,
 * discounts, loyalty, a supplier/purchase, printers, and a handful of
 * sample orders so every admin screen has something real to look at.
 *
 * Never called by the installer (which seeds only RolePermissionSeeder for
 * a genuine customer install) — this is for `php artisan db:seed` /
 * `migrate:fresh --seed` during development only.
 *
 * Everything through seedPrintingSetup() is idempotent (safe to re-run).
 * seedSupplierAndPurchase() and seedSampleOrders() are not — each run adds
 * one more purchase/shift/order, same as real activity happening again.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBusinessSettings();
        $this->seedStaffUsers();
        $tables = $this->seedTablesAndSections();
        $categories = $this->seedCategories();
        $this->seedProducts($categories);
        $this->seedProductImages();
        $units = $this->seedInventoryUnits();
        $items = $this->seedInventoryItemsWithOpeningStock($units);
        $this->seedRecipes($items, $units);
        $this->seedSupplierAndPurchase($units, $items);
        $customerGroups = $this->seedCustomerGroups();
        $customers = $this->seedCustomers($customerGroups);
        $this->seedDiscountsAndCampaign($categories, $customerGroups);
        $this->seedLoyalty();
        $this->seedPrintingSetup();
        $this->seedOpenShift();
        $this->seedSampleOrders($tables, $customers);
    }

    private function seedBusinessSettings(): void
    {
        BusinessSetting::current()->update([
            'name' => 'کافه بامداد',
            'contact_email' => 'info@bamdadcafe.example',
            'contact_phone' => '021-88776655',
            'address' => 'تهران، خیابان ولیعصر، بالاتر از میدان ونک، پلاک ۱۲۴',
            'currency' => 'تومان',
            'currency_minor_units' => 0,
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'tax_enabled' => true,
            'tax_rate_bps' => 900,
            'service_charge_enabled' => false,
            'service_charge_rate_bps' => 0,
            'theme_color_primary' => '#7C4A2D',
            'theme_color_secondary' => '#D9A566',
            'social_links' => [
                'instagram' => 'https://instagram.com/bamdadcafe',
                'telegram' => 'https://t.me/bamdadcafe',
                'whatsapp' => null,
                'website' => null,
            ],
            'accepts_dine_in' => true,
            'accepts_takeaway' => true,
            'accepts_pickup' => true,
            'accepts_delivery' => false,
            'enabled_online_gateways' => ['zarinpal'],
            'working_hours' => collect(range(0, 6))->map(fn (int $day) => [
                'day' => $day,
                'is_closed' => false,
                'opens_at' => '08:00',
                'closes_at' => $day === 5 ? '22:00' : '23:30',
            ])->all(),
        ]);
    }

    private function seedStaffUsers(): void
    {
        $staff = [
            ['name' => 'مریم احمدی', 'email' => 'manager@example.com', 'role' => 'manager'],
            ['name' => 'رضا کریمی', 'email' => 'cashier@example.com', 'role' => 'cashier'],
            ['name' => 'سارا محمدی', 'email' => 'waiter@example.com', 'role' => 'waiter'],
            ['name' => 'امیر حسینی', 'email' => 'kitchen@example.com', 'role' => 'kitchen'],
            ['name' => 'نگین رضایی', 'email' => 'barista@example.com', 'role' => 'barista'],
        ];

        foreach ($staff as $person) {
            User::query()->updateOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'password' => 'password',
                    'role_id' => Role::where('name', $person['role'])->value('id'),
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return Collection<int, Table>
     */
    private function seedTablesAndSections(): Collection
    {
        $layout = [
            'سالن اصلی' => 8,
            'بالکن' => 4,
            'تراس' => 4,
        ];

        $tables = collect();

        foreach ($layout as $sectionName => $count) {
            $section = TableSection::query()->updateOrCreate(
                ['name' => $sectionName],
                ['is_active' => true, 'sort_order' => 0],
            );

            for ($i = 1; $i <= $count; $i++) {
                $tables->push(Table::query()->firstOrCreate(
                    ['table_section_id' => $section->id, 'name' => "میز {$i}"],
                    ['capacity' => $i % 2 === 0 ? 4 : 2, 'status' => 'available', 'is_active' => true],
                ));
            }
        }

        return $tables;
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $hotDrinks = $this->category('نوشیدنی‌های گرم', null, 1);
        $coldDrinks = $this->category('نوشیدنی‌های سرد', null, 2);
        $breakfast = $this->category('صبحانه', null, 3);
        $desserts = $this->category('دسر و شیرینی', null, 4);

        $coffee = $this->category('قهوه', $hotDrinks, 1);
        $tea = $this->category('چای و دمنوش', $hotDrinks, 2);

        return compact('hotDrinks', 'coldDrinks', 'breakfast', 'desserts', 'coffee', 'tea');
    }

    private function category(string $name, ?Category $parent, int $sortOrder): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'parent_id' => $parent?->id,
                'name' => $name,
                'description' => null,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function seedProducts(array $categories): void
    {
        $this->product($categories['coffee'], 'اسپرسو', 'یک شات اسپرسوی خالص و غلیظ.', 45000);
        $this->product($categories['coffee'], 'آمریکانو', 'اسپرسو رقیق‌شده با آب داغ.', 50000);
        $latte = $this->product($categories['coffee'], 'لاته', 'اسپرسو با شیر بخاردیده و فوم نرم.', 65000, label: 'popular');
        $cappuccino = $this->product($categories['coffee'], 'کاپوچینو', 'اسپرسو، شیر بخاردیده و فوم غلیظ.', 65000);
        $this->product($categories['coffee'], 'موکا', 'اسپرسو، شکلات و شیر بخاردیده.', 75000, label: 'new');
        $this->product($categories['coffee'], 'کارامل ماکیاتو', 'اسپرسو با شیراب کارامل و فوم شیر.', 80000, discountPrice: 70000, label: 'special');

        $this->product($categories['tea'], 'چای سیاه', 'چای دبش شده به سبک ایرانی.', 30000);
        $this->product($categories['tea'], 'چای سبز', 'چای سبز معطر.', 35000);
        $this->product($categories['tea'], 'دمنوش بهارنارنج', 'دمنوش گل بهارنارنج.', 40000);

        $this->product($categories['coldDrinks'], 'آیس لاته', 'لاته سرد با یخ.', 70000);
        $this->product($categories['coldDrinks'], 'آیس آمریکانو', 'آمریکانوی سرد با یخ.', 55000);
        $this->product($categories['coldDrinks'], 'اسموتی توت‌فرنگی', 'اسموتی توت‌فرنگی تازه.', 85000, label: 'popular');
        $this->product($categories['coldDrinks'], 'موهیتو', 'موهیتوی بدون الکل با نعنا و لیمو.', 75000);

        $this->product($categories['breakfast'], 'صبحانه کامل', 'تخم‌مرغ، پنیر، مربا، کره و نان تازه.', 180000, label: 'popular');
        $this->product($categories['breakfast'], 'املت', 'املت با گوجه و فلفل دلمه.', 95000);
        $this->product($categories['breakfast'], 'نان و پنیر و گردو', 'نان تازه، پنیر محلی و گردو.', 85000);

        $this->product($categories['desserts'], 'چیزکیک', 'چیزکیک نیویورکی با سس توت.', 95000, label: 'new');
        $this->product($categories['desserts'], 'براونی', 'براونی شکلاتی با بستنی وانیل.', 70000);
        $this->product($categories['desserts'], 'تیرامیسو', 'تیرامیسوی ایتالیایی اصیل.', 110000, discountPrice: 95000, label: 'special');

        $sizeGroup = $this->optionGroup($latte, 'سایز', 'single', required: true, min: 1, max: 1);
        $this->option($sizeGroup, 'کوچک', 0, 1);
        $this->option($sizeGroup, 'متوسط', 10000, 2);
        $this->option($sizeGroup, 'بزرگ', 18000, 3);

        $addOnsGroup = $this->optionGroup($latte, 'افزودنی', 'multiple', required: false, min: 0, max: null);
        $this->option($addOnsGroup, 'شات اضافه', 15000, 1);
        $this->option($addOnsGroup, 'شیر بادام', 8000, 2);
        $this->option($addOnsGroup, 'بدون کافئین', 0, 3);

        $cappuccinoSize = $this->optionGroup($cappuccino, 'سایز', 'single', required: true, min: 1, max: 1);
        $this->option($cappuccinoSize, 'کوچک', 0, 1);
        $this->option($cappuccinoSize, 'بزرگ', 12000, 2);
    }

    private function product(
        Category $category,
        string $name,
        string $description,
        int $basePrice,
        ?int $discountPrice = null,
        ?string $label = null,
    ): Product {
        return Product::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'category_id' => $category->id,
                'name' => $name,
                'description' => $description,
                'base_price' => $basePrice,
                'discount_price' => $discountPrice,
                'is_available' => true,
                'is_active' => true,
                'label' => $label,
                'sort_order' => 0,
            ],
        );
    }

    private function optionGroup(
        Product $product,
        string $name,
        string $selectionType,
        bool $required,
        int $min,
        ?int $max,
    ): ProductOptionGroup {
        return ProductOptionGroup::query()->updateOrCreate(
            ['product_id' => $product->id, 'name' => $name],
            [
                'selection_type' => $selectionType,
                'is_required' => $required,
                'min_select' => $min,
                'max_select' => $max,
                'sort_order' => 0,
                'is_active' => true,
            ],
        );
    }

    private function option(ProductOptionGroup $group, string $name, int $priceDelta, int $sortOrder): ProductOption
    {
        return ProductOption::query()->updateOrCreate(
            ['product_option_group_id' => $group->id, 'name' => $name],
            ['price_delta' => $priceDelta, 'sort_order' => $sortOrder, 'is_active' => true],
        );
    }

    /**
     * Generates a simple placeholder photo for every demo product that has
     * none, so the menu/admin screens don't look broken with blank tiles.
     * Drawn with GD (already a hard dependency via ImageProcessor) rather
     * than fetched from the internet, which this environment has no access
     * to. Deliberately text-free: there is no bundled font with real
     * Persian glyph shaping, and every view that shows this image already
     * prints the product name as a separate text label right next to it.
     */
    private function seedProductImages(): void
    {
        Product::query()->whereDoesntHave('media')->with('category')->each(
            fn (Product $product) => $this->generatePlaceholderProductImage($product)
        );
    }

    private function generatePlaceholderProductImage(Product $product): void
    {
        $width = 800;
        $height = 600;
        [$primary, $secondary] = $this->paletteForCategory($product->category?->name);
        [$r1, $g1, $b1] = $this->hexToRgb($primary);
        [$r2, $g2, $b2] = $this->hexToRgb($secondary);

        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $color = imagecolorallocate(
                $image,
                (int) round($r1 + ($r2 - $r1) * $ratio),
                (int) round($g1 + ($g2 - $g1) * $ratio),
                (int) round($b1 + ($b2 - $b1) * $ratio),
            );
            imageline($image, 0, $y, $width, $y, $color);
        }

        // A simple cup-and-saucer silhouette, centered — generic enough to
        // suit drinks, breakfast and desserts alike as a placeholder.
        $glyph = imagecolorallocatealpha($image, 255, 255, 255, 25);
        $cx = (int) ($width / 2);
        $cy = (int) ($height / 2);

        imagefilledellipse($image, $cx, $cy + 110, 320, 70, $glyph);
        imagefilledpolygon($image, [
            $cx - 130, $cy - 90,
            $cx + 130, $cy - 90,
            $cx + 95, $cy + 90,
            $cx - 95, $cy + 90,
        ], $glyph);

        // A stack of thin arcs reads as one smooth thick ring; a single
        // thick imagearc() call comes out visibly dashed once alpha
        // blending is involved.
        imagesetthickness($image, 1);
        for ($offset = -7; $offset <= 7; $offset++) {
            imagearc($image, $cx + 150, $cy - 10, 90 + $offset, 120 + $offset, -80, 80, $glyph);
        }

        $directory = "media/product/{$product->id}";
        $filename = Str::uuid()->toString().'.jpg';
        $path = "{$directory}/{$filename}";
        Storage::disk('public')->makeDirectory($directory);
        $absolutePath = Storage::disk('public')->path($path);

        imagejpeg($image, $absolutePath, 85);

        $product->media()->create([
            'disk' => 'public',
            'path' => $path,
            'original_filename' => "{$product->slug}.jpg",
            'mime_type' => 'image/jpeg',
            'size' => filesize($absolutePath),
            'width' => $width,
            'height' => $height,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * @return array{0: string, 1: string} a [start, end] hex color pair for
     *                                     a top-to-bottom gradient
     */
    private function paletteForCategory(?string $categoryName): array
    {
        return match ($categoryName) {
            'نوشیدنی‌های سرد' => ['#1E5F6E', '#5CA9B8'],
            'صبحانه' => ['#B5651D', '#E8A75D'],
            'دسر و شیرینی' => ['#7A2E4D', '#C97C9E'],
            default => ['#6B4226', '#A9714B'], // coffee/tea/hot drinks and fallback
        };
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * @return array<string, InventoryUnit>
     */
    private function seedInventoryUnits(): array
    {
        $gram = InventoryUnit::query()->updateOrCreate(['abbreviation' => 'g'], ['name' => 'گرم', 'type' => 'mass', 'base_factor' => 1]);
        $kilogram = InventoryUnit::query()->updateOrCreate(['abbreviation' => 'kg'], ['name' => 'کیلوگرم', 'type' => 'mass', 'base_factor' => 1000]);
        $milliliter = InventoryUnit::query()->updateOrCreate(['abbreviation' => 'ml'], ['name' => 'میلی‌لیتر', 'type' => 'volume', 'base_factor' => 1]);
        $liter = InventoryUnit::query()->updateOrCreate(['abbreviation' => 'l'], ['name' => 'لیتر', 'type' => 'volume', 'base_factor' => 1000]);
        $piece = InventoryUnit::query()->updateOrCreate(['abbreviation' => 'pc'], ['name' => 'عدد', 'type' => 'count', 'base_factor' => 1]);

        return compact('gram', 'kilogram', 'milliliter', 'liter', 'piece');
    }

    /**
     * @param  array<string, InventoryUnit>  $units
     * @return array<string, InventoryItem>
     */
    private function seedInventoryItemsWithOpeningStock(array $units): array
    {
        $driedGoods = InventoryCategory::query()->updateOrCreate(['name' => 'دانه و پودر']);
        $dairy = InventoryCategory::query()->updateOrCreate(['name' => 'لبنیات']);
        $extras = InventoryCategory::query()->updateOrCreate(['name' => 'شربت و افزودنی']);
        $packaging = InventoryCategory::query()->updateOrCreate(['name' => 'بسته‌بندی']);

        $definitions = [
            'coffeeBeans' => ['دانه قهوه', $driedGoods, $units['gram'], 500, '5000'],
            'milk' => ['شیر', $dairy, $units['milliliter'], 2000, '15000'],
            'sugar' => ['شکر', $driedGoods, $units['gram'], 1000, '5000'],
            'caramelSyrup' => ['شربت کارامل', $extras, $units['milliliter'], 300, '2000'],
            'cocoaPowder' => ['پودر شکلات', $driedGoods, $units['gram'], 300, '2000'],
            'cups' => ['لیوان یک‌بار مصرف', $packaging, $units['piece'], 50, '500'],
        ];

        $items = [];

        foreach ($definitions as $key => [$name, $category, $unit, $minStock, $openingQuantity]) {
            $item = InventoryItem::query()->updateOrCreate(
                ['name' => $name],
                [
                    'inventory_category_id' => $category->id,
                    'base_unit_id' => $unit->id,
                    'min_stock_level' => $minStock,
                    'is_active' => true,
                ],
            );

            // Only seed the opening balance once — re-running the seeder
            // must not keep adding stock on top of what's already there.
            if (! $item->movements()->where('type', 'opening_balance')->exists()) {
                app(StockMovementService::class)->record($item, 'opening_balance', $openingQuantity, unitCost: 0);
            }

            $items[$key] = $item;
        }

        return $items;
    }

    /**
     * @param  array<string, InventoryItem>  $items
     * @param  array<string, InventoryUnit>  $units
     */
    private function seedRecipes(array $items, array $units): void
    {
        $recipes = [
            'اسپرسو' => [[$items['coffeeBeans'], '18', $units['gram']]],
            'آمریکانو' => [[$items['coffeeBeans'], '18', $units['gram']]],
            'لاته' => [[$items['coffeeBeans'], '18', $units['gram']], [$items['milk'], '200', $units['milliliter']]],
            'کاپوچینو' => [[$items['coffeeBeans'], '18', $units['gram']], [$items['milk'], '150', $units['milliliter']]],
            'موکا' => [[$items['coffeeBeans'], '18', $units['gram']], [$items['milk'], '150', $units['milliliter']], [$items['cocoaPowder'], '15', $units['gram']]],
            'کارامل ماکیاتو' => [[$items['coffeeBeans'], '18', $units['gram']], [$items['milk'], '180', $units['milliliter']], [$items['caramelSyrup'], '15', $units['milliliter']]],
        ];

        foreach ($recipes as $productName => $lines) {
            $product = Product::where('slug', Str::slug($productName))->first();

            if (! $product) {
                continue;
            }

            foreach ($lines as [$item, $quantity, $unit]) {
                $product->recipeItems()->updateOrCreate(
                    ['inventory_item_id' => $item->id],
                    ['quantity' => $quantity, 'unit_id' => $unit->id],
                );
            }
        }

        // The "extra shot" add-on on Latte consumes coffee beans too.
        $latte = Product::where('slug', Str::slug('لاته'))->first();
        $extraShot = $latte?->optionGroups()->where('name', 'افزودنی')->first()?->options()->where('name', 'شات اضافه')->first();

        if ($extraShot) {
            $extraShot->recipeItems()->updateOrCreate(
                ['inventory_item_id' => $items['coffeeBeans']->id],
                ['quantity' => '18', 'unit_id' => $units['gram']->id],
            );
        }
    }

    /**
     * @param  array<string, InventoryUnit>  $units
     * @param  array<string, InventoryItem>  $items
     */
    private function seedSupplierAndPurchase(array $units, array $items): void
    {
        $supplier = Supplier::query()->updateOrCreate(
            ['name' => 'بازرگانی قهوه طلایی'],
            [
                'contact_name' => 'آقای طلایی',
                'phone' => '021-55443322',
                'email' => 'sales@goldencoffee.example',
                'address' => 'تهران، بازار، سرای قهوه',
                'is_active' => true,
            ],
        );

        // Only place one demo purchase per fresh install — re-running the
        // seeder shouldn't keep creating new purchase orders.
        if ($supplier->purchases()->exists()) {
            return;
        }

        $owner = User::where('role_id', Role::where('name', 'owner')->value('id'))->first();

        if (! $owner) {
            return;
        }

        $purchase = app(CreatePurchaseAction::class)->execute([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->subDays(3)->toDateString(),
            'notes' => 'سفارش ماهانه دانه قهوه',
            'items' => [
                ['inventory_item_id' => $items['coffeeBeans']->id, 'unit_id' => $units['kilogram']->id, 'quantity' => '10', 'unit_cost' => 350000],
            ],
        ], $owner);

        $purchaseItem = $purchase->items->first();

        app(ReceivePurchaseAction::class)->execute($purchase, [
            ['purchase_item_id' => $purchaseItem->id, 'quantity' => '10'],
        ], $owner);
    }

    /**
     * @return array<string, CustomerGroup>
     */
    private function seedCustomerGroups(): array
    {
        $vip = CustomerGroup::query()->updateOrCreate(
            ['name' => 'مشتریان ویژه'],
            ['description' => 'مشتریانی با خرید مکرر که تخفیف‌های ویژه دریافت می‌کنند.'],
        );

        return compact('vip');
    }

    /**
     * @param  array<string, CustomerGroup>  $groups
     * @return array<string, Customer>
     */
    private function seedCustomers(array $groups): array
    {
        $hossein = Customer::query()->updateOrCreate(
            ['phone' => '0912-1234567'],
            ['name' => 'حسین رستمی', 'status' => 'active'],
        );

        $elham = Customer::query()->updateOrCreate(
            ['phone' => '0935-7654321'],
            ['name' => 'الهام صادقی', 'customer_group_id' => $groups['vip']->id, 'status' => 'active'],
        );

        $kianoush = Customer::query()->updateOrCreate(
            ['phone' => '0919-1112233'],
            ['name' => 'کیانوش مرادی', 'status' => 'active'],
        );

        return compact('hossein', 'elham', 'kianoush');
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, CustomerGroup>  $groups
     */
    private function seedDiscountsAndCampaign(array $categories, array $groups): void
    {
        $campaign = Campaign::query()->updateOrCreate(
            ['name' => 'جشنواره زمستانه'],
            [
                'description' => 'تخفیف‌های ویژه فصل زمستان.',
                'starts_at' => now()->subDays(7)->toDateString(),
                'ends_at' => now()->addMonths(2)->toDateString(),
                'is_active' => true,
            ],
        );

        Discount::query()->updateOrCreate(
            ['name' => 'تخفیف ۱۰٪ کل سفارش'],
            [
                'description' => 'ده درصد تخفیف روی کل سفارش، در قالب جشنواره زمستانه.',
                'type' => 'percentage',
                'value' => 1000,
                'applies_to' => 'order',
                'priority' => 10,
                'is_stackable' => false,
                'is_active' => true,
                'campaign_id' => $campaign->id,
            ],
        );

        $buyCoffeeGetOne = Discount::query()->updateOrCreate(
            ['name' => 'قهوه سوم رایگان'],
            [
                'description' => 'با خرید هر ۲ نوشیدنی از دسته قهوه، یکی دیگر رایگان است.',
                'type' => 'buy_x_get_y',
                'buy_quantity' => 2,
                'get_quantity' => 1,
                'applies_to' => 'specific_categories',
                'priority' => 5,
                'is_stackable' => true,
                'is_active' => true,
            ],
        );
        $buyCoffeeGetOne->categories()->sync([$categories['coffee']->id]);

        $vipDiscount = Discount::query()->updateOrCreate(
            ['name' => 'تخفیف ویژه اعضا'],
            [
                'description' => 'تخفیف نقدی برای اعضای گروه مشتریان ویژه روی سفارش‌های بالای ۱۰۰,۰۰۰ تومان.',
                'type' => 'fixed_amount',
                'value' => 20000,
                'applies_to' => 'order',
                'min_order_amount' => 100000,
                'priority' => 1,
                'is_stackable' => true,
                'is_active' => true,
            ],
        );
        $vipDiscount->customerGroups()->sync([$groups['vip']->id]);
    }

    private function seedLoyalty(): void
    {
        LoyaltyRule::query()->updateOrCreate(
            ['name' => 'هر ۱۰۰,۰۰۰ تومان = ۵۰ امتیاز'],
            [
                'type' => 'spend_threshold',
                'min_spend_amount' => 100000,
                'points_earned' => 50,
                'is_active' => true,
            ],
        );

        LoyaltyRule::query()->updateOrCreate(
            ['name' => 'هر ۲ قلم سفارش = ۱۰ امتیاز جایزه'],
            [
                'type' => 'buy_x_get_y_points',
                'buy_quantity' => 2,
                'get_bonus_points' => 10,
                'is_active' => true,
            ],
        );

        LoyaltyReward::query()->updateOrCreate(
            ['name' => '۱۰٪ تخفیف با ۱۰۰ امتیاز'],
            ['points_cost' => 100, 'reward_type' => 'percentage_discount', 'value' => 1000, 'is_active' => true],
        );

        $espresso = Product::where('slug', Str::slug('اسپرسو'))->first();

        if ($espresso) {
            LoyaltyReward::query()->updateOrCreate(
                ['name' => 'یک اسپرسو رایگان با ۲۰۰ امتیاز'],
                ['points_cost' => 200, 'reward_type' => 'free_product', 'free_product_id' => $espresso->id, 'is_active' => true],
            );
        }
    }

    private function seedPrintingSetup(): void
    {
        $kitchenPrinter = Printer::query()->updateOrCreate(
            ['name' => 'پرینتر آشپزخانه'],
            ['connection_type' => 'network', 'ip_address' => '192.168.1.50', 'port' => 9100, 'paper_width' => '80mm', 'farsi_codepage_table' => 22, 'is_active' => true],
        );

        Printer::query()->updateOrCreate(
            ['name' => 'پرینتر صندوق'],
            ['connection_type' => 'network', 'ip_address' => '192.168.1.51', 'port' => 9100, 'paper_width' => '80mm', 'farsi_codepage_table' => 22, 'is_active' => true],
        );

        // A single catch-all route — every confirmed order's docket goes to
        // the one kitchen printer, which is the common setup for a small
        // cafe with no separate bar station.
        PrintRoute::query()->updateOrCreate(
            ['document_type' => 'kitchen_docket', 'category_id' => null],
            ['printer_id' => $kitchenPrinter->id, 'is_active' => true],
        );

        InvoiceTemplate::query()->updateOrCreate(
            ['name' => 'قالب پیش‌فرض A4'],
            ['is_default' => true, 'paper_size' => 'a4', 'show_logo' => true, 'is_active' => true],
        );
    }

    private function seedOpenShift(): void
    {
        $cashier = User::where('email', 'cashier@example.com')->first();

        if (! $cashier || Shift::where('user_id', $cashier->id)->open()->exists()) {
            return;
        }

        app(OpenShiftAction::class)->execute($cashier, 500000);
    }

    /**
     * @param  Collection<int, Table>  $tables
     * @param  array<string, Customer>  $customers
     */
    private function seedSampleOrders(Collection $tables, array $customers): void
    {
        // Only products without required option groups, so sample orders
        // don't need to resolve which options to select.
        $products = Product::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereDoesntHave('optionGroups', fn ($query) => $query->where('is_required', true))
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $placeOrder = app(PlaceOrderAction::class);
        $createStaffOrder = app(CreateStaffOrderAction::class);
        $transitionStatus = app(TransitionOrderStatusAction::class);
        $recordPayment = app(RecordPaymentAction::class);

        $owner = User::where('role_id', Role::where('name', 'owner')->value('id'))->first();
        $cashier = User::where('email', 'cashier@example.com')->first() ?? $owner;
        $waiter = User::where('email', 'waiter@example.com')->first() ?? $owner;

        // A completed, fully paid dine-in order taken by a waiter through
        // the POS panel, linked to a real customer — earns loyalty points
        // automatically on completion.
        $order1 = $createStaffOrder->execute([
            'type' => 'dine_in',
            'table_id' => $tables->first()?->id,
            'customer_id' => $customers['hossein']->id,
            'items' => $products->random(min(2, $products->count()))->map(fn (Product $p) => [
                'product_id' => $p->id,
                'quantity' => 1,
            ])->all(),
        ], $waiter);
        $transitionStatus->execute($order1, 'confirmed', $owner);
        $transitionStatus->execute($order1, 'preparing', $owner);
        $transitionStatus->execute($order1, 'ready', $owner);
        $transitionStatus->execute($order1, 'completed', $owner);
        $recordPayment->execute($order1, ['method' => 'cash', 'amount' => $order1->grand_total, 'status' => 'paid'], $cashier);

        // An order still being prepared, unpaid (public self-service checkout).
        $placeOrder->execute([
            'type' => 'takeaway',
            'customer_name' => 'رامین قاسمی',
            'customer_phone' => '0938-4455667',
            'items' => $products->random(min(3, $products->count()))->map(fn (Product $p) => [
                'product_id' => $p->id,
                'quantity' => 1,
            ])->all(),
        ], null);

        // A pickup order confirmed and paid online, linked to the VIP customer.
        $order3 = $placeOrder->execute([
            'type' => 'pickup',
            'customer_name' => $customers['elham']->name,
            'customer_phone' => $customers['elham']->phone,
            'items' => $products->random(min(2, $products->count()))->map(fn (Product $p) => [
                'product_id' => $p->id,
                'quantity' => 2,
            ])->all(),
        ], null);
        $order3->update(['customer_id' => $customers['elham']->id]);
        $transitionStatus->execute($order3, 'confirmed', $owner);
        $recordPayment->execute($order3, ['method' => 'online', 'amount' => $order3->grand_total, 'status' => 'paid'], $cashier);
    }
}
