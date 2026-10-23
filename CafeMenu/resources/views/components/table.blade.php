@props(['headers' => []])

<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm']) }}>
    <table class="w-full text-start text-sm">
        @if (count($headers))
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/80 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    @foreach ($headers as $header)
                        <th class="px-4 py-3 text-start font-medium">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 [&>tr]:transition [&>tr]:hover:bg-gray-50 dark:[&>tr]:hover:bg-gray-800">
            {{ $slot }}
        </tbody>
    </table>
</div>
