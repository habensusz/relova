<div x-data="{ open: false }" class="relative">
    {{-- Selected value display --}}
    @if($selectedReferenceId)
        <div class="flex items-center gap-2 p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20">
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $selectedDisplay }}</div>
                @if(!empty($selectedSnapshot))
                    <div class="flex items-center gap-2 mt-1">
                        @foreach(array_slice($selectedSnapshot, 0, 3) as $key => $val)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-600/50 text-xs text-gray-600 dark:text-gray-400">
                                {{ $key }}: {{ Str::limit((string) $val, 20) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
            <button wire:click="clearSelection" type="button"
                class="p-1.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200"
                title="{{ __('relova::relova.clear') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @else
        {{-- Search input --}}
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
            <input wire:model.live.debounce.300ms="searchTerm"
                type="text"
                @focus="open = true"
                @click.away="setTimeout(() => open = false, 200)"
                class="w-full pl-10 pr-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200"
                placeholder="{{ __('relova::relova.search_remote') }}...">

            {{-- Loading indicator --}}
            <div wire:loading wire:target="searchTerm" class="absolute inset-y-0 right-0 pr-3.5 flex items-center">
                <svg class="animate-spin w-4 h-4 text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        {{-- Error message --}}
        @if($errorMessage)
            <div class="mt-2 p-2.5 rounded-lg bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 text-xs">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- Search results dropdown --}}
        @if(count($searchResults) > 0)
            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-xl max-h-64 overflow-y-auto">
                @foreach($searchResults as $idx => $row)
                    @php
                        $pv = $row[$primaryColumn] ?? '';
                        $displayLabel = $row[$searchColumn] ?? $row[$primaryColumn] ?? 'Item';
                    @endphp
                    <button wire:click="selectResult('{{ $pv }}')" wire:key="res-{{ $idx }}"
                        type="button"
                        class="w-full text-left px-4 py-3 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition-colors duration-150 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $displayLabel }}</div>
                        @if(!empty($displayColumns))
                            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                @foreach($displayColumns as $dc)
                                    @if(isset($row[$dc]) && $dc !== $searchColumn)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $dc }}: {{ Str::limit((string) $row[$dc], 25) }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif
    @endif
</div>
