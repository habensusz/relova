<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">

    {{-- Page header --}}
    <div class="relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500"></div>
        <div class="p-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-100 to-teal-200 dark:from-emerald-900/50 dark:to-teal-800/50 flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-600 dark:text-emerald-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('relova::relova.relova_connector') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ __('relova::relova.connector_description') }}</p>
            </div>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-emerald-600 dark:text-emerald-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75" />
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalConnections }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('relova::relova.connections') }}</div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-sky-50 dark:bg-sky-900/30 flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-sky-600 dark:text-sky-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $activeConnections }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('relova::relova.active') }}</div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-teal-50 dark:bg-teal-900/30 flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-teal-600 dark:text-teal-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $healthyConnections }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('relova::relova.healthy') }}</div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-indigo-600 dark:text-indigo-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalMappings }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('relova::relova.field_mappings') }}</div>
            </div>
        </div>
    </div>

    {{-- Feature cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        {{-- Connections --}}
        <a href="{{ tenancy()->initialized ? tenant()->route('relova.connections') : route('relova.connections') }}"
            wire:navigate
            class="group relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden hover:shadow-xl hover:border-emerald-200 dark:hover:border-emerald-700 transition-all duration-200">
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500"></div>
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-100 to-teal-200 dark:from-emerald-900/50 dark:to-teal-800/50 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-600 dark:text-emerald-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">{{ __('relova::relova.connections') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('relova::relova.connections_description') }}</p>
                <div class="mt-4 flex items-center gap-1.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ __('relova::relova.manage') }}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </div>
            </div>
        </a>

        {{-- Schema Browser --}}
        <a href="{{ tenancy()->initialized ? tenant()->route('relova.schema') : route('relova.schema') }}"
            wire:navigate
            class="group relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden hover:shadow-xl hover:border-violet-200 dark:hover:border-violet-700 transition-all duration-200">
            <div class="h-1 bg-gradient-to-r from-violet-500 via-purple-500 to-fuchsia-500"></div>
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-100 to-purple-200 dark:from-violet-900/50 dark:to-purple-800/50 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-violet-600 dark:text-violet-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">{{ __('relova::relova.schema_browser') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('relova::relova.browse_remote_schemas') }}</p>
                <div class="mt-4 flex items-center gap-1.5 text-sm font-semibold text-violet-600 dark:text-violet-400">
                    {{ __('relova::relova.explore') }}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </div>
            </div>
        </a>

        {{-- Field Mappings --}}
        <a href="{{ tenancy()->initialized ? tenant()->route('relova.mappings') : route('relova.mappings') }}"
            wire:navigate
            class="group relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden hover:shadow-xl hover:border-sky-200 dark:hover:border-sky-700 transition-all duration-200">
            <div class="h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500"></div>
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-sky-600 dark:text-sky-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">{{ __('relova::relova.field_mappings') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('relova::relova.configure_mappings') }}</p>
                <div class="mt-4 flex items-center gap-1.5 text-sm font-semibold text-sky-600 dark:text-sky-400">
                    {{ __('relova::relova.configure') }}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </div>
            </div>
        </a>

    </div>

    {{-- Configured mappings list --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
        <div class="h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500"></div>
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ __('relova::relova.configured_mappings') }}</h2>
                <a href="{{ tenancy()->initialized ? tenant()->route('relova.mappings') : route('relova.mappings') }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white rounded-lg shadow transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('relova::relova.new_mapping') }}
                </a>
            </div>

            @if (empty($mappings))
                <div class="text-center py-10">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/30 dark:to-indigo-800/30 flex items-center justify-center mx-auto mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-sky-500 dark:text-sky-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('relova::relova.no_configured_mappings') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($mappings as $mapping)
                        @php
                            $editUrl = tenancy()->initialized
                                ? tenant()->route('relova.mappings.edit', [$mapping['connection']['uid'] ?? '', $mapping['uid']])
                                : route('relova.mappings.edit', [$mapping['connection']['uid'] ?? '', $mapping['uid']]);
                        @endphp
                        <div class="flex items-center justify-between py-3 gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center
                                    {{ $mapping['enabled'] ? 'bg-emerald-50 dark:bg-emerald-900/30' : 'bg-gray-100 dark:bg-gray-700' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                        class="w-4 h-4 {{ $mapping['enabled'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $mapping['name'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                        {{ $mapping['connection']['name'] ?? '—' }}
                                        <span class="mx-1 text-gray-300 dark:text-gray-600">›</span>
                                        {{ $mapping['source_table'] }}
                                        <span class="mx-1 text-gray-300 dark:text-gray-600">›</span>
                                        {{ $mapping['target_module'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if (! $mapping['enabled'])
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                        {{ __('relova::relova.inactive') }}
                                    </span>
                                @endif
                                <a href="{{ $editUrl }}"
                                    wire:navigate
                                    class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-800 dark:hover:text-sky-200 transition-colors">
                                    {{ __('relova::relova.edit_mapping') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>
