@php
    $connectionsUrl = tenancy()->initialized ? tenant()->route('relova.connections.index') : route('relova.connections.index');
    $mappingsUrl = tenancy()->initialized ? tenant()->route('relova.mappings.index') : route('relova.mappings.index');
@endphp

<a href="{{ $connectionsUrl }}" wire:navigate
   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-all duration-150">
    {{ __('relova::ui.manage_connections') }}
</a>
<a href="{{ $mappingsUrl }}" wire:navigate
   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm shadow-sky-500/25 transition-all duration-150">
    {{ __('relova::ui.module_mappings') }}
</a>
