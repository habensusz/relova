{{--
    Relova breadcrumb (mirrors iTenance's livewire/page-breadcrumb component).

    Required:
        $items   array<int, array{label: string, url?: string}>
                 The trailing item is rendered as the current (non-linked) page.

    The "Premises (or Dashboard) > Relova" prefix is added automatically.
--}}
@php
    $items = $items ?? [];
    $dashboardUrl = tenancy()->initialized
        ? tenant()->route('dashboard')
        : route('dashboard');
    $relovaUrl = tenancy()->initialized
        ? tenant()->route('relova.dashboard')
        : route('relova.dashboard');
    $homeLabel = optional(auth()->user()?->premises)->premises_name
        ?? __('ui.dashboard');
@endphp

<nav aria-label="breadcrumb"
     class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
    <a href="{{ $dashboardUrl }}" wire:navigate
       class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors duration-150">
        {{ $homeLabel }}
    </a>

    <span class="select-none">&rsaquo;</span>

    <a href="{{ $relovaUrl }}" wire:navigate
       class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors duration-150">
        {{ __('relova::ui.breadcrumb_relova') }}
    </a>

    @foreach ($items as $item)
        <span class="select-none">&rsaquo;</span>
        @if (! $loop->last && isset($item['url']))
            <a href="{{ $item['url'] }}" wire:navigate
               class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors duration-150 truncate max-w-[160px]">
                {{ $item['label'] }}
            </a>
        @else
            <span class="text-gray-600 dark:text-gray-300 font-medium truncate max-w-xs">
                {{ $item['label'] }}
            </span>
        @endif
    @endforeach
</nav>
