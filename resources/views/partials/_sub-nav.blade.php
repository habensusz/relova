{{--
    Relova section sub-navigation strip.

    Required:
        $active   string   One of: overview | connections | mappings | schema
--}}
@php
    $active = $active ?? 'overview';

    $relovaUrl = tenancy()->initialized ? tenant()->route('relova.dashboard') : route('relova.dashboard');
    $connectionsUrl = tenancy()->initialized ? tenant()->route('relova.connections.index') : route('relova.connections.index');
    $mappingsUrl = tenancy()->initialized ? tenant()->route('relova.mappings.index') : route('relova.mappings.index');

    $tabs = [
        ['key' => 'overview',    'label' => __('relova::ui.tab_overview'),    'url' => $relovaUrl],
        ['key' => 'connections', 'label' => __('relova::ui.tab_connections'), 'url' => $connectionsUrl],
        ['key' => 'mappings',    'label' => __('relova::ui.tab_mappings'),    'url' => $mappingsUrl],
    ];

    if ($active === 'schema') {
        $tabs[] = ['key' => 'schema', 'label' => __('relova::ui.tab_schema'), 'url' => '#'];
    }
@endphp

<div class="border-b border-gray-200 dark:border-gray-700 mb-4">
    <nav class="-mb-px flex items-center gap-1 overflow-x-auto" aria-label="Relova sections">
        @foreach ($tabs as $tab)
            @php $isActive = $active === $tab['key']; @endphp
            <a href="{{ $tab['url'] }}"
               @if ($tab['key'] !== 'schema') wire:navigate @endif
               class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-150 whitespace-nowrap
                      {{ $isActive
                          ? 'border-sky-500 dark:border-sky-400 text-sky-700 dark:text-sky-300'
                          : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
