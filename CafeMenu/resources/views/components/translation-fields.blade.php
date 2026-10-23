@props(['model', 'fields'])

@php
    $allLocaleNames = ['en' => 'English', 'fa' => 'فارسی', 'ar' => 'العربية'];
    $homeLocale = \App\Models\BusinessSetting::current()->locale;
    $otherLocales = collect($allLocaleNames)->except($homeLocale);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4 rounded-xl border border-gray-200 dark:border-gray-700 p-4']) }}>
    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.translations.title') }}</p>
    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.translations.hint') }}</p>

    @foreach ($otherLocales as $code => $localeName)
        <div class="space-y-2 border-t border-gray-100 dark:border-gray-800 pt-3 first:border-0 first:pt-0">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $localeName }}</p>

            @foreach ($fields as $field => $label)
                @php $value = old("translations.$code.$field", data_get($model->translations, "$code.$field")); @endphp

                @if ($field === 'description')
                    <textarea
                        name="translations[{{ $code }}][{{ $field }}]"
                        rows="2"
                        placeholder="{{ $label }}"
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary focus:ring-primary/30"
                    >{{ $value }}</textarea>
                @else
                    <input
                        type="text"
                        name="translations[{{ $code }}][{{ $field }}]"
                        value="{{ $value }}"
                        placeholder="{{ $label }}"
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary focus:ring-primary/30"
                    >
                @endif
            @endforeach
        </div>
    @endforeach
</div>
