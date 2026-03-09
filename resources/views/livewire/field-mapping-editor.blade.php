<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
{{-- Back to dashboard --}}
<div class="mb-3">
    <a href="{{ tenancy()->initialized ? tenant()->route('relova.dashboard') : route('relova.dashboard') }}" wire:navigate
        class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-sky-600 dark:hover:text-sky-400 transition-colors duration-200">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        {{ __('relova.relova_connector') }}
    </a>
</div>
<div class="relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg">
    {{-- Header --}}
    <div class="flex flex-row justify-between items-center p-5 border-b border-gray-100 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-sky-600 dark:text-sky-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white uppercase tracking-wide">{{ __('relova.field_mappings') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('relova.configure_mappings') }}</p>
            </div>
        </div>
    </div>

    <div class="p-5 space-y-5">
        {{-- Error --}}
        @if($errorMessage)
            <div class="p-3 rounded-xl bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 text-sm">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- Connection + source table --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.connection') }}</label>
                <select wire:model.live="connectionUid"
                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200">
                    <option value="">{{ __('relova.choose_connection') }}</option>
                    @foreach($connections as $conn)
                        <option value="{{ $conn['uid'] }}">{{ $conn['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.source_table') }}</label>
                <select wire:model.live="source_table"
                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200">
                    <option value="">{{ __('relova.choose_table') }}</option>
                    @foreach($remoteTables as $table)
                        <option value="{{ $table['name'] }}">{{ $table['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Name & target module --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.mapping_name') }}</label>
                <input wire:model="name" type="text"
                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200"
                    placeholder="{{ __('relova.mapping_name_placeholder') }}">
                @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    {{ __('relova.target_table') }}
                    <span wire:loading wire:target="updatedTargetModule" class="inline-flex items-center gap-1 ml-1 text-xs font-normal text-sky-500 dark:text-sky-400">
                        <svg class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ __('relova.loading') }}
                    </span>
                </label>
                <select wire:model.live="target_module"
                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200">
                    <option value="">{{ __('relova.choose_table') }}</option>
                    @foreach($localTables as $table)
                        <option value="{{ $table['name'] }}">{{ $table['name'] }}</option>
                    @endforeach
                </select>
                @error('target_module') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('relova.description') }}</label>
            <textarea wire:model="description" rows="2"
                class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200 resize-none"
                placeholder="{{ __('relova.description_placeholder') }}"></textarea>
        </div>

        {{-- Column mapping table --}}
        @if($source_table && $target_module)
            @php
                $requiredFields = collect($localColumns)->filter(fn($c) => $c['required'] ?? false)->pluck('name');
                $mappedLocalFields = collect($column_mappings)->pluck('local_field')->filter();
                $unmappedRequired = $requiredFields->diff($mappedLocalFields)->values();
            @endphp

            {{-- Required fields coverage warning --}}
            @if($requiredFields->isNotEmpty())
                @if($unmappedRequired->isNotEmpty())
                    <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">{{ __('relova.required_fields_unmapped') }}</p>
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                                {{ $unmappedRequired->join(', ') }}
                            </p>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ __('relova.all_required_mapped') }}</p>
                    </div>
                @endif
            @endif

            <div class="border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('relova.column_mappings') }}</h4>
                        @if($requiredFields->isNotEmpty())
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __('relova.required_field_legend') }}</p>
                        @endif
                    </div>
                    <button wire:click="addColumnMapping" type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-100 dark:bg-sky-900/30 hover:bg-sky-200 dark:hover:bg-sky-900/50 rounded-lg transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ __('relova.add_mapping') }}
                    </button>
                </div>

                @if(count($column_mappings) === 0)
                    <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                        {{ __('relova.no_mappings_hint') }}
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($column_mappings as $idx => $mapping)
                            <div wire:key="map-{{ $idx }}" class="px-4 py-3 flex items-center gap-3">
                                {{-- Remote column --}}
                                <div class="flex-1">
                                    <select wire:model="column_mappings.{{ $idx }}.remote_column"
                                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500/20 transition-all duration-200">
                                        <option value="">{{ __('relova.remote_column') }}</option>
                                        @foreach($remoteColumns as $col)
                                            <option value="{{ $col['name'] }}">{{ $col['name'] }} ({{ $col['type'] ?? '' }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Arrow --}}
                                <div class="shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-sky-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </div>

                                {{-- Local field --}}
                                <div class="flex-1">
                                    <div wire:loading.remove wire:target="updatedTargetModule">
                                        <select wire:model="column_mappings.{{ $idx }}.local_field"
                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500/20 transition-all duration-200">
                                            <option value="">{{ __('relova.local_field') }}</option>
                                            @foreach($localColumns as $col)
                                                @if(!($col['primary'] ?? false))
                                                <option value="{{ $col['name'] }}"
                                                    title="{{ ($col['required'] ?? false) ? __('relova.required_field_hint') : '' }}">
                                                    {{ $col['name'] }}{{ ($col['required'] ?? false) ? ' *' : '' }} ({{ $col['type'] ?? '' }})
                                                </option>
                                                @endif
                                            @endforeach
                                        </select>
                                        @if(($col = collect($localColumns)->firstWhere('name', $mapping['local_field'] ?? '')) && ($col['required'] ?? false))
                                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ __('relova.required_field_hint') }}</p>
                                        @endif
                                    </div>
                                    <div wire:loading wire:target="updatedTargetModule" class="flex items-center gap-2 px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <svg class="w-3.5 h-3.5 animate-spin text-sky-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('relova.loading') }}</span>
                                    </div>
                                </div>

                                {{-- Remove --}}
                                <button wire:click="removeColumnMapping({{ $idx }})" type="button"
                                    class="shrink-0 p-1.5 text-gray-400 hover:text-red-500 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                @error('column_mappings') <p class="px-4 py-2 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        @endif

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
            @if($source_table && $target_module && count($column_mappings) > 0)
                <button wire:click="preview" type="button"
                    wire:loading.attr="disabled"
                    wire:target="preview"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-violet-700 dark:text-violet-300 bg-violet-100 dark:bg-violet-900/30 hover:bg-violet-200 dark:hover:bg-violet-900/50 rounded-xl transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed">
                    {{-- Eye icon when idle --}}
                    <svg wire:loading.remove wire:target="preview" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    {{-- Spinner when loading --}}
                    <svg wire:loading wire:target="preview" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="preview">{{ __('relova.preview') }}</span>
                    <span wire:loading wire:target="preview">{{ __('relova.loading') }}</span>
                </button>
            @else
                <div></div>
            @endif

            <button wire:click="save" type="button"
                wire:loading.attr="disabled"
                wire:target="save"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 rounded-xl shadow-sm transition-all duration-200 disabled:opacity-60">
                <svg wire:loading wire:target="save" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span wire:loading.remove wire:target="save">{{ __('ui.save') }}</span>
                <span wire:loading wire:target="save">{{ __('ui.saving') }}...</span>
            </button>
        </div>

        {{-- Preview results (shown below actions so they appear after clicking) --}}
        @if($showPreview)
            <div class="border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h5 class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 uppercase">{{ __('relova.mapping_preview') }}</h5>
                    <button wire:click="$set('showPreview', false)" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                @if(count($previewMapped) === 0)
                    <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('relova.no_preview_data') }}</div>
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 divide-y lg:divide-y-0 lg:divide-x divide-gray-100 dark:divide-gray-700">
                        {{-- Raw data --}}
                        <div>
                            <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">{{ __('relova.raw_data') }}</div>
                            <div class="overflow-x-auto max-h-48">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50/50 dark:bg-gray-700/30 sticky top-0">
                                        <tr>
                                            @foreach(array_keys($previewRaw[0] ?? []) as $col)
                                                <th class="px-2 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $col }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @foreach($previewRaw as $ri => $row)
                                            <tr wire:key="raw-{{ $ri }}">
                                                @foreach($row as $v)
                                                    <td class="px-2 py-1 text-gray-700 dark:text-gray-300 whitespace-nowrap truncate max-w-[120px]">{{ $v }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        {{-- Mapped data --}}
                        <div>
                            <div class="px-3 py-2 bg-emerald-50/50 dark:bg-emerald-900/10 text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase">{{ __('relova.mapped_data') }}</div>
                            <div class="overflow-x-auto max-h-48">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50/50 dark:bg-gray-700/30 sticky top-0">
                                        <tr>
                                            @foreach(array_keys($previewMapped[0] ?? []) as $col)
                                                <th class="px-2 py-1.5 text-left font-semibold text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $col }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @foreach($previewMapped as $mi => $row)
                                            <tr wire:key="mapped-{{ $mi }}">
                                                @foreach($row as $v)
                                                    <td class="px-2 py-1 text-emerald-700 dark:text-emerald-300 whitespace-nowrap truncate max-w-[120px]">{{ $v }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
</div>
