{{-- Page-header action button(s) for the Schema browser --}}
@php
    $connectionsUrl = tenancy()->initialized
        ? tenant()->route('relova.connections.index')
        : route('relova.connections.index');
@endphp
<a href="{{ $connectionsUrl }}" wire:navigate
    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-all duration-200">
    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
    </svg>
    {{ __('relova::ui.back_to_connections') }}
</a>
