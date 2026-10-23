<a
    href="{{ route('menu.show', $product) }}"
    data-product-card
    data-product-name="{{ $product->translate('name') }}"
    class="group flex flex-col overflow-hidden rounded-xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
>
    <div class="aspect-4/3 w-full shrink-0 overflow-hidden bg-gray-100 dark:bg-gray-800">
        @if ($product->primaryImage())
            <img
                src="{{ $product->primaryImage()->url() }}"
                alt="{{ $product->translate('name') }}"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                loading="lazy"
            >
        @else
            <div class="flex h-full w-full items-center justify-center text-gray-300 dark:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-10 w-10">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5 8 11l3.5 3.5L16 10l5 6.5M3 6h18v12H3z" />
                </svg>
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-3">
        <div class="flex items-start justify-between gap-2">
            <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $product->translate('name') }}</p>
            @if ($product->label)
                <span class="shrink-0 rounded-full bg-secondary/10 px-2 py-0.5 text-[11px] font-medium text-secondary">
                    {{ ucfirst($product->label) }}
                </span>
            @endif
        </div>

        @if ($product->translate('description'))
            <p class="mt-1 line-clamp-2 flex-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $product->translate('description') }}</p>
        @endif

        @php $price = $product->priceFor($business ?? null); @endphp
        <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
            {{ \App\Support\Money::formatAs($price['amount'], $price['currency_code'], $price['minor_units'], $price['currency_symbol']) }}
            @if ($price['original_amount'])
                <span class="ms-1 text-xs font-normal text-gray-400 dark:text-gray-500 line-through">{{ \App\Support\Money::formatAs($price['original_amount'], $price['currency_code'], $price['minor_units'], $price['currency_symbol']) }}</span>
            @endif
        </p>
    </div>
</a>
