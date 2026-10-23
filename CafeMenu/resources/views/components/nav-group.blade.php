@props(['label'])

{{--
    A labelled section of the admin sidebar. Each link inside keeps its own
    @can check; the whole group (heading included) hides itself through CSS
    when the current user may see none of its links (`.nav-group:not(:has(a))`
    in app.css), so permission logic is never duplicated here.
--}}
<div {{ $attributes->merge(['class' => 'nav-group pt-4 first:pt-0']) }}>
    <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $label }}</p>
    <div class="space-y-0.5">
        {{ $slot }}
    </div>
</div>
