{{--
    Relova page header (mirrors iTenance's livewire/page-header component).

    Required:
        $icon      string   Heroicon path-d (24/outline) — rendered inside the gradient square.
        $title     string   Page title.
    Optional:
        $subtitle  string   Smaller line under the title.
        $actions   string   View name (e.g. "relova::partials._dashboard-actions") rendered on the right.
        $metaText  string   Inline meta string rendered after the title.
--}}
@php
    $subtitle = $subtitle ?? null;
    $actions = $actions ?? null;
    $metaText = $metaText ?? null;
@endphp

<div class="mb-4 flex items-center gap-3">
    {{-- Gradient icon square (matches iTenance page-header) --}}
    <div class="w-9 h-9 flex-shrink-0 rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center">
        <svg class="w-4 h-4 text-sky-700 dark:text-sky-300"
             xmlns="http://www.w3.org/2000/svg"
             fill="none" viewBox="0 0 24 24"
             stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
        </svg>
    </div>

    {{-- Title + subtitle --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white truncate">
                {{ $title }}
            </h1>
            @if ($metaText)
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ $metaText }}</span>
            @endif
        </div>
        @if ($subtitle)
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $subtitle }}</p>
        @endif
    </div>

    {{-- Right-aligned actions --}}
    @if ($actions)
        <div class="flex items-center gap-2 flex-shrink-0">
            @include($actions)
        </div>
    @endif
</div>
