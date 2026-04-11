<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
{{-- Back to dashboard --}}
<div class="mb-3">
    <a href="{{ tenancy()->initialized ? tenant()->route('relova.dashboard') : route('relova.dashboard') }}" wire:navigate
        class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors duration-200">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        {{ __('relova.relova_connector') }}
    </a>
</div>
<div class="relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
    {{-- Accent bar --}}
    <div class="h-1 bg-gradient-to-r from-teal-500 to-emerald-400"></div>
    {{-- Header --}}
    <div class="flex flex-row justify-between items-center p-5 border-b border-gray-100 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-emerald-600 dark:text-emerald-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white uppercase tracking-wide">{{ __('relova.connections') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ count($connections) }} {{ __('relova.registered') }}</p>
            </div>
        </div>
        <button wire:click="openCreateForm" type="button"
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition-all duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ __('relova.new_connection') }}
        </button>
    </div>

    {{-- Connection cards --}}
    <div class="p-5">
        @if(count($connections) === 0)
            <div class="text-center py-12">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-gray-400 dark:text-gray-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                    </svg>
                </div>
                <p class="text-gray-500 dark:text-gray-400 mb-2">{{ __('relova.no_connections') }}</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('relova.no_connections_hint') }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($connections as $conn)
                    @php
                        $connEnabled = $conn['enabled'] ?? false;
                        $healthStatus = $conn['health_status'] ?? 'unknown';

                        $stripClass = match(true) {
                            ! $connEnabled => 'from-gray-300 to-gray-400',
                            $healthStatus === 'healthy' => 'from-teal-500 to-emerald-400',
                            $healthStatus === 'degraded' => 'from-amber-400 to-yellow-400',
                            $healthStatus === 'unhealthy' => 'from-red-500 to-red-400',
                            default => 'from-gray-300 to-gray-400',
                        };

                        $connBorder = match(true) {
                            ! $connEnabled => 'border-gray-100 dark:border-gray-700',
                            $healthStatus === 'healthy' => 'border-teal-200 dark:border-teal-800/50',
                            $healthStatus === 'unhealthy' => 'border-red-200 dark:border-red-800/50',
                            default => 'border-gray-100 dark:border-gray-700',
                        };

                        $pillBg = match(true) {
                            ! $connEnabled => 'bg-gray-100 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400',
                            $healthStatus === 'healthy' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
                            $healthStatus === 'degraded' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                            $healthStatus === 'unhealthy' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                            default => 'bg-gray-100 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400',
                        };

                        $statusLabel = match(true) {
                            ! $connEnabled => __('relova::relova.status_disabled'),
                            $healthStatus === 'healthy' => __('relova::relova.connected'),
                            $healthStatus === 'degraded' => __('relova::relova.degraded'),
                            $healthStatus === 'unhealthy' => __('relova::relova.status_error'),
                            default => __('relova::relova.status_unknown'),
                        };

                        $driverLabel = match($conn['driver_type'] ?? '') {
                            'mysql' => 'MySQL',
                            'pgsql' => 'PG',
                            'sqlsrv' => 'MSSQL',
                            'oracle' => 'ORA',
                            'sap_hana' => 'SAP',
                            'csv' => 'CSV',
                            'xlsx' => 'XLS',
                            default => 'DB',
                        };

                        $driverBg = match($conn['driver_type'] ?? '') {
                            'mysql' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
                            'pgsql' => 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-400',
                            'sqlsrv' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                            'oracle' => 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400',
                            'sap_hana' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                            'csv' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                            'xlsx' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
                            default => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
                        };
                    @endphp
                    <div wire:key="conn-{{ $conn['uid'] }}"
                        class="relative bg-white dark:bg-gray-800 rounded-2xl border {{ $connBorder }} shadow hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col">
                        {{-- Status strip --}}
                        <div class="h-1 bg-gradient-to-r {{ $stripClass }}"></div>

                        <div class="p-4 flex flex-col gap-3 flex-1">
                            {{-- Top row: driver badge + status pill --}}
                            <div class="flex items-start justify-between gap-2">
                                <div class="w-10 h-10 rounded-xl {{ $driverBg }} flex items-center justify-center font-mono text-xs font-bold flex-shrink-0">
                                    {{ $driverLabel }}
                                </div>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $pillBg }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {{ $statusLabel }}
                                </span>
                            </div>

                            {{-- Name and endpoint --}}
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white text-sm">{{ $conn['name'] }}</div>
                                @if(! empty($conn['host']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-mono">
                                        {{ $conn['host'] }}{{ ! empty($conn['port']) ? ':' . $conn['port'] : '' }}{{ ! empty($conn['database_name']) ? ' · ' . $conn['database_name'] : '' }}
                                    </div>
                                @endif
                                @if(! empty($conn['description']))
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $conn['description'] }}</div>
                                @endif
                            </div>

                            {{-- Last health check --}}
                            @if(! empty($conn['last_health_check_at']))
                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ __('relova::relova.last_checked') }}: {{ \Carbon\Carbon::parse($conn['last_health_check_at'])->diffForHumans() }}
                                </div>
                            @endif

                            <div class="flex-1"></div>

                            {{-- Footer: toggle + action buttons --}}
                            <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700">
                                {{-- Toggle enabled --}}
                                <div wire:click="toggleConnection('{{ $conn['uid'] }}')"
                                    style="display:inline-flex;width:2.75rem;height:1.5rem;min-width:2.75rem;border-radius:9999px;cursor:pointer;transition:background-color .2s ease-in-out;position:relative;flex-shrink:0;{{ $connEnabled ? 'background-color:#10b981;' : 'background-color:#d1d5db;' }}"
                                    title="{{ $connEnabled ? __('relova::relova.disable') : __('relova::relova.enable') }}">
                                    <span aria-hidden="true"
                                        style="position:absolute;top:0.125rem;{{ $connEnabled ? 'left:1.375rem;' : 'left:0.125rem;' }}width:1.25rem;height:1.25rem;border-radius:9999px;background-color:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:left .2s ease-in-out;">
                                    </span>
                                </div>

                                <div class="flex items-center gap-1">
                                    {{-- Test --}}
                                    <button wire:click="testExistingConnection('{{ $conn['uid'] }}')" type="button"
                                        class="p-1.5 text-gray-400 hover:text-amber-600 dark:text-gray-500 dark:hover:text-amber-400 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-all duration-200"
                                        title="{{ __('relova::relova.test_connection') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
                                        </svg>
                                    </button>
                                    {{-- Edit --}}
                                    <button wire:click="openEditForm('{{ $conn['uid'] }}')" type="button"
                                        class="p-1.5 text-gray-400 hover:text-sky-600 dark:text-gray-500 dark:hover:text-sky-400 rounded-lg hover:bg-sky-50 dark:hover:bg-sky-900/20 transition-all duration-200"
                                        title="{{ __('ui.edit') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                        </svg>
                                    </button>
                                    {{-- Delete --}}
                                    <button wire:click="deleteConnection('{{ $conn['uid'] }}')"
                                        wire:confirm="{{ __('relova::relova.delete_confirm') }}" type="button"
                                        class="p-1.5 text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200"
                                        title="{{ __('ui.delete') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Add connector card --}}
                <button wire:click="openCreateForm" type="button"
                    class="flex flex-col items-center justify-center gap-2.5 p-6 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-600 hover:border-teal-400 dark:hover:border-teal-500 hover:bg-teal-50/50 dark:hover:bg-teal-900/10 text-gray-400 dark:text-gray-500 hover:text-teal-600 dark:hover:text-teal-400 transition-all duration-200 min-h-[160px]">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    <div class="text-center">
                        <div class="text-sm font-medium">{{ __('relova::relova.new_connection') }}</div>
                        <div class="text-xs mt-0.5">{{ __('relova::relova.add_connector_hint') }}</div>
                    </div>
                </button>
            </div>
        @endif
    </div>

    {{-- Create / Edit Form Modal --}}
    @if($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true"
            x-data x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="flex items-start justify-center min-h-screen pt-12 px-4 pb-20">
                {{-- Overlay --}}
                <div class="fixed inset-0 bg-gray-900/60 dark:bg-gray-900/80 backdrop-blur-sm" wire:click="closeForm"></div>

                {{-- Panel --}}
                <div class="relative w-full max-w-2xl bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 z-10">
                    {{-- Modal header --}}
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 rounded-t-2xl">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $editing ? __('relova.edit_connection') : __('relova.new_connection') }}
                            </h3>
                            <button wire:click="closeForm" type="button" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Form body --}}
                    <form wire:submit.prevent="save" class="p-6 space-y-5">
                        {{-- Name --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.connection_name') }}</label>
                            <input wire:model="name" type="text"
                                class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                placeholder="{{ __('relova.connection_name_placeholder') }}">
                            @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.description') }}</label>
                            <textarea wire:model="description" rows="2"
                                class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200 resize-none"
                                placeholder="{{ __('relova.description_placeholder') }}"></textarea>
                        </div>

                        {{-- Driver --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.driver') }}</label>
                            <select wire:model="driver_type"
                                class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200">
                                <option value="pgsql">PostgreSQL</option>
                                <option value="mysql">MySQL / MariaDB</option>
                                <option value="sqlsrv">SQL Server</option>
                                <option value="oracle">Oracle</option>
                                <option value="sap_hana">SAP HANA</option>
                                <option value="csv">{{ __('relova.csv_file') }}</option>
                                <option value="xlsx">{{ __('relova.xlsx_file') }}</option>
                            </select>
                            @error('driver_type') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        {{-- Extension requirement hints --}}
                        <div x-show="$wire.driver_type === 'oracle'" x-cloak
                            class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 text-xs text-amber-800 dark:text-amber-300">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0 mt-0.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            <span>{{ __('relova.oracle_ext_hint') }}</span>
                        </div>
                        <div x-show="$wire.driver_type === 'sap_hana'" x-cloak
                            class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 text-xs text-amber-800 dark:text-amber-300">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0 mt-0.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            <span>{{ __('relova.sap_hana_ext_hint') }}</span>
                        </div>

                        {{-- File path (CSV / XLSX only) --}}
                        <div x-show="['csv', 'xlsx'].includes($wire.driver_type)" x-cloak>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.file_path') }}</label>
                            <div class="flex gap-2">
                                <input wire:model="host" type="text"
                                    class="flex-1 px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200 font-mono"
                                    placeholder="/var/www/storage/imports/data.csv">
                                <button wire:click="openFileBrowser" type="button"
                                    class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v8.25" />
                                    </svg>
                                    {{ __('relova.browse') }}
                                </button>
                            </div>
                            @error('host') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-400">{{ __('relova.file_path_hint') }}</p>
                        </div>

                        {{-- CSV delimiter (CSV only) --}}
                        <div x-show="$wire.driver_type === 'csv'" x-cloak>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.delimiter') }}</label>
                            <select wire:model="delimiter"
                                class="w-full max-w-xs px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200">
                                <option value=",">, {{ __('relova.delimiter_comma') }}</option>
                                <option value=";">; {{ __('relova.delimiter_semicolon') }}</option>
                                <option value="&#9;">{{ __('relova.delimiter_tab') }}</option>
                                <option value="|">| {{ __('relova.delimiter_pipe') }}</option>
                            </select>
                        </div>

                        {{-- Host / Port / DB (database drivers only) --}}
                        <div x-show="!['csv', 'xlsx'].includes($wire.driver_type)" x-cloak>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.host') }}</label>
                                <input wire:model="host" type="text"
                                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                    placeholder="db.example.com">
                                @error('host') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.port') }}</label>
                                <input wire:model="port" type="number"
                                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                    placeholder="5432">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.database') }}</label>
                                <input wire:model="database_name" type="text"
                                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                    placeholder="my_database">
                                @error('database_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            </div>
                        </div>{{-- /db host/port/db --}}

                        {{-- Schema / Username / Password (database drivers only) --}}
                        <div x-show="!['csv', 'xlsx'].includes($wire.driver_type)" x-cloak>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.schema') }}</label>
                                <input wire:model="schema_name" type="text"
                                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                    placeholder="public">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.username') }}</label>
                                <input wire:model="username" type="text"
                                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                    placeholder="{{ __('relova.username') }}">
                                @error('username') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.password') }}</label>
                                <input wire:model="password" type="password"
                                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                    placeholder="{{ $editing ? __('relova.password_unchanged') : __('relova.password') }}">
                                @error('password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            </div>
                        </div>{{-- /db schema/user/pass --}}

                        {{-- Advanced settings --}}
                        <div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.cache_ttl') }}</label>
                                <input wire:model="cache_ttl" type="number"
                                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                    placeholder="300" min="0" max="86400">
                                <p class="mt-1 text-xs text-gray-400">{{ __('relova.cache_ttl_hint') }}</p>
                            </div>
                        </div>

                        {{-- SSH Tunnel (database drivers only) --}}
                        <div x-show="!['csv', 'xlsx'].includes($wire.driver_type)" x-cloak>
                        <div x-data="{ open: @entangle('ssh_enabled') }"
                            class="rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden">
                            {{-- SSH toggle header --}}
                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700/50 cursor-pointer"
                                @click="open = !open; $wire.set('ssh_enabled', open)">
                                <div class="flex items-center gap-2.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500 dark:text-gray-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('relova.ssh_tunnel') }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('relova.ssh_tunnel_hint') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span x-show="open" class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ __('relova.ssh_enabled_label') }}</span>
                                    <span x-show="!open" class="text-xs font-medium text-gray-400">{{ __('relova.ssh_disabled_label') }}</span>
                                    <div :style="open ? 'background-color:#10b981;' : 'background-color:#d1d5db;'"
                                        style="display:inline-flex;width:2.25rem;height:1.25rem;min-width:2.25rem;border-radius:9999px;position:relative;transition:background-color .2s ease-in-out;flex-shrink:0;">
                                        <div :style="open ? 'left:1rem;' : 'left:0.125rem;'"
                                            style="position:absolute;top:0.125rem;width:1rem;height:1rem;border-radius:9999px;background-color:#ffffff;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:left .2s ease-in-out;"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- SSH fields (shown when enabled) --}}
                            <div x-show="open" x-collapse class="p-4 space-y-4 border-t border-gray-200 dark:border-gray-600">
                                <p class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2">
                                    {{ __('relova.ssh_info') }}
                                </p>

                                {{-- SSH Host / Port / User --}}
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.ssh_host') }}</label>
                                        <input wire:model="ssh_host" type="text"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                            placeholder="{{ __('relova.ssh_host_placeholder') }}">
                                        <p class="mt-1 text-xs text-gray-400">{{ __('relova.ssh_host_hint') }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.ssh_port') }}</label>
                                        <input wire:model="ssh_port" type="number"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                            placeholder="22">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.ssh_user') }}</label>
                                        <input wire:model="ssh_user" type="text"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                            placeholder="forge">
                                    </div>
                                </div>

                                {{-- Auth method --}}
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.ssh_auth_method') }}</label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input wire:model.live="ssh_auth_method" type="radio" value="key"
                                                class="text-emerald-600 focus:ring-emerald-500/20">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('relova.ssh_auth_key') }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input wire:model.live="ssh_auth_method" type="radio" value="password"
                                                class="text-emerald-600 focus:ring-emerald-500/20">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('relova.ssh_auth_password') }}</span>
                                        </label>
                                    </div>
                                </div>

                                {{-- Key auth fields --}}
                                @if($ssh_auth_method === 'key')
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.ssh_private_key') }}</label>
                                        <textarea wire:model="ssh_private_key" rows="5"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-xs font-mono text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200 resize-none"
                                            placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----">{{ $editing ? '' : '' }}</textarea>
                                        @if($editing)
                                            <p class="mt-1 text-xs text-gray-400">{{ __('relova.ssh_key_unchanged') }}</p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.ssh_passphrase') }}</label>
                                        <input wire:model="ssh_passphrase" type="password"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                            placeholder="{{ __('relova.ssh_passphrase_optional') }}">
                                    </div>
                                @else
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.ssh_password_label') }}</label>
                                        <input wire:model="ssh_password" type="password"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:focus:border-emerald-400 transition-all duration-200"
                                            placeholder="{{ $editing ? __('relova.password_unchanged') : __('relova.ssh_password_label') }}">
                                        <p class="mt-1 text-xs text-amber-500">{{ __('relova.ssh_password_warning') }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>{{-- /ssh x-data --}}
                        </div>{{-- /db-only SSH wrapper --}}

                        {{-- Test result --}}
                        @if($testResult)
                            <div class="p-3 rounded-xl text-sm {{ $testResult === 'success' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }}">
                                {{ $testMessage }}
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                            <button wire:click="testConnectionFromForm" type="button"
                                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/30 hover:bg-amber-200 dark:hover:bg-amber-900/50 rounded-xl transition-all duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
                                </svg>
                                {{ __('relova.test_connection') }}
                            </button>
                            <div class="flex items-center gap-3">
                                <button wire:click="closeForm" type="button"
                                    class="px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200">
                                    {{ __('ui.cancel') }}
                                </button>
                                <button type="submit"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition-all duration-200">
                                    <span wire:loading.remove wire:target="save">{{ __('ui.save') }}</span>
                                    <span wire:loading wire:target="save">{{ __('ui.saving') }}...</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- File Browser Modal --}}
@if($showFileBrowser)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 dark:bg-gray-900/80 backdrop-blur-sm">
        <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 flex flex-col" style="max-height: 80vh;">
            {{-- Header --}}
            <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-gray-700 flex-shrink-0">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v8.25" />
                    </svg>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('relova.browse_files') }}</span>
                </div>
                <button wire:click="$set('showFileBrowser', false)" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Current path --}}
            <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700 flex-shrink-0">
                <p class="text-xs font-mono text-gray-500 dark:text-gray-400 truncate" title="{{ $fileBrowserPath }}">{{ $fileBrowserPath ?: '/' }}</p>
            </div>

            {{-- Error --}}
            @if($fileBrowserError)
                <div class="px-4 py-2 bg-red-50 dark:bg-red-900/20 text-xs text-red-600 dark:text-red-400 flex-shrink-0">{{ $fileBrowserError }}</div>
            @endif

            {{-- Entries --}}
            <div class="overflow-y-auto flex-1">
                @if(empty($fileBrowserEntries) && !$fileBrowserError)
                    <p class="text-center py-8 text-sm text-gray-400">{{ __('relova.browser_empty') }}</p>
                @endif
                @foreach($fileBrowserEntries as $entry)
                    @if($entry['type'] === 'dir')
                        <button wire:click="fileBrowserNavigate('{{ addslashes($entry['path']) }}')" type="button"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-amber-500 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v8.25" />
                            </svg>
                            <span class="truncate font-mono">{{ $entry['name'] }}</span>
                        </button>
                    @else
                        <button wire:click="fileBrowserSelect('{{ addslashes($entry['path']) }}')" type="button"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors duration-150 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-emerald-500 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <span class="truncate font-mono">{{ $entry['name'] }}</span>
                        </button>
                    @endif
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex-shrink-0 flex justify-end">
                <button wire:click="$set('showFileBrowser', false)" type="button"
                    class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200">
                    {{ __('ui.cancel') }}
                </button>
            </div>
        </div>
    </div>
@endif
</div>
