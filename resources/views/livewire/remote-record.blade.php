<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto space-y-5">

    {{-- Back button --}}
    <div>
        <button type="button"
            onclick="window.history.back()"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-800 dark:hover:text-sky-200 transition-colors duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            {{ __('relova.back') }}
        </button>
    </div>

    {{-- Page header --}}
    <div class="relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500"></div>
        <div class="p-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-sky-600 dark:text-sky-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ __('relova.remote_record') }}
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ __('relova.data_from_connection', ['connection' => $connectionName ?? '—']) }}
                            <span class="mx-1.5 text-gray-300 dark:text-gray-600">·</span>
                            <span class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ $remoteTable }}</span>
                            @if($mappingName)
                                <span class="mx-1.5 text-gray-300 dark:text-gray-600">·</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $mappingName }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Refresh button --}}
                <div class="flex-shrink-0">
                    <button type="button"
                        wire:click="refresh"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 shadow-sm transition-all duration-200 disabled:opacity-60">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                            class="w-4 h-4"
                            wire:loading.class="animate-spin"
                            wire:target="refresh">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span wire:loading.remove wire:target="refresh">{{ __('relova.refresh_from_source') }}</span>
                        <span wire:loading wire:target="refresh">{{ __('relova.refreshing') }}</span>
                    </button>
                </div>
            </div>

            {{-- Last refreshed --}}
            <div class="mt-4 flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{ __('relova.last_refreshed') }}:
                {{ $reference?->snapshot_refreshed_at
                    ? $reference->snapshot_refreshed_at->diffForHumans()
                    : __('relova.never') }}
            </div>
        </div>
    </div>

    {{-- Error message --}}
    @if($errorMessage)
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-500 dark:text-red-400 flex-shrink-0 mt-0.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <span class="text-sm text-red-700 dark:text-red-300">{{ $errorMessage }}</span>
        </div>
    @endif

    {{-- Snapshot notice --}}
    <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200/60 dark:border-amber-700/40 rounded-xl px-4 py-3 flex items-start gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-amber-500 dark:text-amber-400 flex-shrink-0 mt-0.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
        </svg>
        <span class="text-xs text-amber-700 dark:text-amber-300">{{ __('relova.snapshot_notice') }}</span>
    </div>

    @if (empty($snapshot))

        {{-- Empty state --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden p-10 text-center">
            <div class="w-12 h-12 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25" />
                </svg>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('relova.no_snapshot_data') }}</p>
        </div>

    @else

        @php
            // Build a map: remote_column => local_field for quick lookup
            $mappedColumns = collect($columnMappings)
                ->keyBy(fn ($cm) => $cm['remote_column'] ?? '')
                ->mapWithKeys(fn ($cm, $col) => [$col => $cm['local_field'] ?? null])
                ->all();

            $mappedSection = collect($snapshot)
                ->filter(fn ($v, $k) => array_key_exists($k, $mappedColumns));

            $unmappedSection = collect($snapshot)
                ->reject(fn ($v, $k) => array_key_exists($k, $mappedColumns));
        @endphp

        {{-- Mapped fields card --}}
        @if ($mappedSection->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
                <div class="h-1 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500"></div>
                <div class="p-5">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">{{ __('relova.mapped_fields') }}</h2>
                    <div class="space-y-0 divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($mappedSection as $column => $value)
                            @php $localField = $mappedColumns[$column] ?? null; @endphp
                            <div class="py-3 grid grid-cols-3 gap-4 items-start">
                                <div>
                                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-0.5">
                                        {{ __('relova.remote_column') }}
                                    </div>
                                    <div class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ $column }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-0.5">
                                        {{ __('relova.local_field_label') }}
                                    </div>
                                    @if ($localField)
                                        <div class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 font-mono text-xs">
                                            {{ $localField }}
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('relova.unmapped') }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-0.5">
                                        {{ __('relova.remote_value') }}
                                    </div>
                                    <div class="text-sm text-gray-900 dark:text-white break-all">
                                        @if (is_null($value))
                                            <span class="text-xs italic text-gray-400 dark:text-gray-500">null</span>
                                        @elseif (is_bool($value))
                                            <span class="text-xs font-mono {{ $value ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                                {{ $value ? 'true' : 'false' }}
                                            </span>
                                        @elseif (is_array($value))
                                            <code class="text-xs text-gray-500 dark:text-gray-400">{{ json_encode($value) }}</code>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- All remote columns card --}}
        @if ($unmappedSection->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
                <div class="h-1 bg-gradient-to-r from-violet-500 via-purple-500 to-fuchsia-500"></div>
                <div class="p-5">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-4">{{ __('relova.all_fields') }}</h2>
                    <div class="space-y-0 divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($unmappedSection as $column => $value)
                            <div class="py-3 grid grid-cols-2 gap-4 items-start">
                                <div class="font-mono text-xs text-violet-600 dark:text-violet-400">{{ $column }}</div>
                                <div class="text-sm text-gray-700 dark:text-gray-300 break-all">
                                    @if (is_null($value))
                                        <span class="text-xs italic text-gray-400 dark:text-gray-500">null</span>
                                    @elseif (is_bool($value))
                                        <span class="text-xs font-mono {{ $value ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                            {{ $value ? 'true' : 'false' }}
                                        </span>
                                    @elseif (is_array($value))
                                        <code class="text-xs text-gray-500 dark:text-gray-400">{{ json_encode($value) }}</code>
                                    @else
                                        {{ $value }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

    @endif

</div>
