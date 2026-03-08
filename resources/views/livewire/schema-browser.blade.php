<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
{{-- Back to dashboard --}}
<div class="mb-3">
    <a href="{{ tenancy()->initialized ? tenant()->route('relova.dashboard') : route('relova.dashboard') }}" wire:navigate
        class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-violet-600 dark:hover:text-violet-400 transition-colors duration-200">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        {{ __('relova::relova.relova_connector') }}
    </a>
</div>
<div class="relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
    {{-- Gradient accent --}}
    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-violet-500 via-purple-500 to-fuchsia-500"></div>

    {{-- Header --}}
    <div class="flex flex-row justify-between items-center p-5 border-b border-gray-100 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-100 to-purple-200 dark:from-violet-900/50 dark:to-purple-800/50 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-600 dark:text-violet-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M10.875 12h-1.5m1.5 0c.621 0 1.125.504 1.125 1.125m-2.625 0h1.5M10.875 12c-.621 0-1.125.504-1.125 1.125m0 0v1.5c0 .621.504 1.125 1.125 1.125m-2.25 0c-.621 0-1.125.504-1.125 1.125M12 13.875c0-.621.504-1.125 1.125-1.125" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white uppercase tracking-wide">{{ __('relova::relova.schema_browser') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('relova::relova.browse_remote_schemas') }}</p>
            </div>
        </div>
        @if($connectionUid)
            <button wire:click="refreshSchema" type="button"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-violet-700 dark:text-violet-300 bg-violet-100 dark:bg-violet-900/30 hover:bg-violet-200 dark:hover:bg-violet-900/50 rounded-xl transition-all duration-200">
                <svg wire:loading.remove wire:target="refreshSchema" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                </svg>
                <svg wire:loading wire:target="refreshSchema" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ __('relova::relova.refresh') }}
            </button>
        @endif
    </div>

    <div class="p-5">
        {{-- Connection selector --}}
        <div class="mb-5">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova::relova.select_connection') }}</label>
            <div class="relative flex items-center gap-3">
                <select wire:model.live="connectionUid" wire:change="selectConnection($event.target.value)"
                    class="w-full max-w-md px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:focus:border-violet-400 transition-all duration-200">
                    <option value="">{{ __('relova::relova.choose_connection') }}</option>
                    @foreach($connections as $conn)
                        <option value="{{ $conn['uid'] }}">
                            {{ $conn['name'] }} ({{ $conn['driver_type'] }})
                        </option>
                    @endforeach
                </select>
                <div wire:loading wire:target="selectConnection,loadTables,refreshSchema" class="flex items-center gap-2 text-sm text-violet-600 dark:text-violet-400">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ __('relova::relova.loading') }}</span>
                </div>
            </div>
        </div>

        {{-- Error message --}}
        @if($errorMessage)
            <div class="mb-4 p-3 rounded-xl bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 text-sm">
                {{ $errorMessage }}
            </div>
        @endif

        @if($connectionUid)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {{-- Tables panel --}}
                <div class="lg:col-span-1 border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                            {{ __('relova::relova.tables') }} ({{ count($tables) }})
                            <svg wire:loading wire:target="selectConnection,loadTables,refreshSchema" class="w-3.5 h-3.5 animate-spin text-violet-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </h4>
                    </div>
                    <div class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                        {{-- Loading skeleton --}}
                        <div wire:loading wire:target="selectConnection,loadTables,refreshSchema" class="px-4 py-3 space-y-2">
                            @for($i = 0; $i < 6; $i++)
                                <div class="h-8 bg-gray-100 dark:bg-gray-700 rounded animate-pulse"></div>
                            @endfor
                        </div>
                        <div wire:loading.remove wire:target="selectConnection,loadTables,refreshSchema">
                        @forelse($tables as $table)
                            <button wire:click="selectTable('{{ $table['name'] }}')" wire:key="tbl-{{ $table['name'] }}"
                                type="button"
                                class="w-full text-left px-4 py-2.5 text-sm hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors duration-150 flex items-center justify-between {{ $selectedTable === $table['name'] ? 'bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-300 font-semibold' : 'text-gray-700 dark:text-gray-300' }}">
                                <span class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25" />
                                    </svg>
                                    {{ $table['name'] }}
                                </span>
                                @if(isset($table['row_count']))
                                    <span class="text-xs text-gray-400 dark:text-gray-500 font-normal">{{ number_format($table['row_count']) }}</span>
                                @endif
                            </button>
                        @empty
                            <div class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('relova::relova.no_tables') }}</div>
                        @endforelse
                        </div>
                    </div>
                </div>

                {{-- Columns panel --}}
                <div class="lg:col-span-2 border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden">
                    @if($selectedTable)
                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                <span class="text-violet-600 dark:text-violet-400">{{ $selectedTable }}</span>
                                &mdash; {{ count($columns) }} {{ __('relova::relova.columns') }}
                                <svg wire:loading wire:target="selectTable,loadColumns,loadPreview" class="inline w-3.5 h-3.5 animate-spin text-violet-500 ml-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </h4>
                            <button wire:click="loadPreview" type="button"
                                wire:loading.attr="disabled" wire:target="loadPreview"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-violet-700 dark:text-violet-300 bg-violet-100 dark:bg-violet-900/30 hover:bg-violet-200 dark:hover:bg-violet-900/50 rounded-lg transition-all duration-200 disabled:opacity-50">
                                <svg wire:loading.remove wire:target="loadPreview" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg wire:loading wire:target="loadPreview" class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('relova::relova.preview_data') }}
                            </button>
                        </div>

                        {{-- Column list --}}
                        <div class="max-h-64 overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50/50 dark:bg-gray-700/30 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">{{ __('relova::relova.column_name') }}</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">{{ __('relova::relova.column_type') }}</th>
                                        <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">{{ __('relova::relova.nullable') }}</th>
                                        <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400 text-xs uppercase">{{ __('relova::relova.key') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($columns as $col)
                                        <tr wire:key="col-{{ $col['name'] }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                            <td class="px-4 py-2 font-mono text-gray-900 dark:text-gray-100">{{ $col['name'] }}</td>
                                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $col['type'] ?? '' }}</td>
                                            <td class="px-4 py-2 text-center">
                                                @if($col['nullable'] ?? false)
                                                    <span class="text-gray-400">{{ __('relova::relova.yes') }}</span>
                                                @else
                                                    <span class="text-gray-600 dark:text-gray-300 font-semibold">{{ __('relova::relova.no') }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if(($col['key'] ?? '') === 'PRI' || ($col['key'] ?? '') === 'PRIMARY KEY')
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 text-xs font-semibold">PK</span>
                                                @elseif($col['key'] ?? '')
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300 text-xs">{{ $col['key'] }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Data preview --}}
                        @if(count($previewRows) > 0)
                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <div class="px-4 py-3 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900/10 dark:to-purple-900/10 border-b border-gray-100 dark:border-gray-700">
                                    <h5 class="text-xs font-semibold text-violet-700 dark:text-violet-300 uppercase">
                                        {{ __('relova::relova.data_preview') }} ({{ count($previewRows) }} {{ __('relova::relova.rows') }})
                                    </h5>
                                </div>
                                <div class="overflow-x-auto max-h-72">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                                            <tr>
                                                @foreach(array_keys($previewRows[0] ?? []) as $colName)
                                                    <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $colName }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @foreach($previewRows as $i => $row)
                                                <tr wire:key="prev-{{ $i }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                    @foreach($row as $val)
                                                        <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300 whitespace-nowrap max-w-xs truncate">{{ $val }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672Zm-7.518-.267A8.25 8.25 0 1 1 20.25 10.5M8.288 14.212A5.25 5.25 0 1 1 17.25 10.5" />
                            </svg>
                            {{ __('relova::relova.select_table_hint') }}
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="text-center py-12">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-violet-100 to-purple-200 dark:from-violet-700 dark:to-purple-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-violet-500 dark:text-violet-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375" />
                    </svg>
                </div>
                <p class="text-gray-500 dark:text-gray-400 mb-1">{{ __('relova::relova.no_connection_selected') }}</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('relova::relova.select_connection_hint') }}</p>
            </div>
        @endif
    </div>
</div>
</div>
