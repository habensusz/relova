<div class="relative mx-auto container pb-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="py-3 text-gray-900 dark:text-gray-100">
            <article class="container mx-auto" style="min-height: 100px;">

                {{-- ── Main card ── --}}
                <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg overflow-hidden mb-6">
                    <div class="h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500"></div>

                    {{-- Header --}}
                    <div class="px-6 py-5 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('relova::ui.module_mappings') }}</h1>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ __('relova::ui.module_mappings_subtitle') }}</p>
                            </div>
                        </div>
                        @if (!$showForm)
                            <button wire:click="openCreate" type="button"
                                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                {{ __('relova::ui.new_mapping') }}
                            </button>
                        @endif
                    </div>

                    @if ($showForm)
                        <div class="border-t border-gray-100 dark:border-gray-700">
                            <form wire:submit="save">
                                {{-- Form title bar --}}
                                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/40 flex items-center justify-between border-b border-gray-100 dark:border-gray-700">
                                    <div>
                                        <h2 class="text-base font-bold text-zinc-900 dark:text-white">
                                            {{ $editing ? __('relova::ui.edit_mapping') : __('relova::ui.new_mapping') }}
                                        </h2>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('relova::ui.mapping_form_subtitle') }}</p>
                                    </div>
                                    <button type="button" wire:click="closeForm"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors text-xl leading-none">&times;</button>
                                </div>

                                <div class="px-6 py-6 space-y-4">

                                    {{-- Step 1: Source --}}
                                    <div class="rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden">
                                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex items-center gap-3 border-b border-gray-200 dark:border-gray-600">
                                            <span class="w-6 h-6 rounded-full bg-sky-600 text-white text-xs font-bold flex items-center justify-center shrink-0">1</span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">Source configuration</p>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Which connection to use, which remote table to pull from, and which local table to feed.</p>
                                            </div>
                                            <span class="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 shrink-0">required</span>
                                        </div>
                                        <div class="p-4 space-y-4">
                                            <div>
                                                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-1.5 block">{{ __('relova::ui.connection') }}</label>
                                                <select wire:model.live="connectionUid"
                                                    class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-colors">
                                                    <option value="">— {{ __('relova::ui.connection') }} —</option>
                                                    @foreach ($connections as $c)
                                                        <option value="{{ $c->uid }}">{{ $c->name }} ({{ $c->driver }})</option>
                                                    @endforeach
                                                </select>
                                                @error('connectionUid') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <div class="flex items-center justify-between mb-1.5">
                                                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Local table</label>
                                                        @if ($editing && $moduleKey !== '')
                                                            <button type="button" wire:click="applyModuleDefaults('{{ $moduleKey }}')"
                                                                class="text-[11px] text-sky-600 dark:text-sky-400 hover:underline">{{ __('relova::ui.reload_defaults') }}</button>
                                                        @endif
                                                    </div>
                                                    <select wire:model.live="moduleKey"
                                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-colors">
                                                        <option value="">— Select —</option>
                                                        @foreach ($localTables as $tbl)
                                                            <option value="{{ $tbl }}">{{ $tbl }}</option>
                                                        @endforeach
                                                    </select>
                                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{{ __('relova::ui.module_key_hint') }}</p>
                                                    @error('moduleKey') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                                </div>
                                                <div>
                                                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-1.5 block">{{ __('relova::ui.remote_table') }}</label>
                                                    @if ($tablesError)
                                                        <div class="px-3 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 text-xs text-red-600 dark:text-red-400">{{ $tablesError }}</div>
                                                    @elseif (count($remoteTables) > 0)
                                                        <div wire:loading wire:target="connectionUid" class="px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-sm text-gray-400 dark:text-gray-500 animate-pulse">{{ __('relova::ui.loading_tables') }}</div>
                                                        <select wire:loading.remove wire:target="connectionUid" wire:model.live="remoteTable"
                                                            class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-colors">
                                                            <option value="">— Select —</option>
                                                            @foreach ($remoteTables as $t)
                                                                <option value="{{ $t['name'] }}">{{ $t['name'] }}@if(isset($t['row_count']) && $t['row_count'] !== null) (~{{ number_format($t['row_count']) }} rows)@endif</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($connectionUid !== '')
                                                        <div wire:loading wire:target="connectionUid" class="px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-sm text-gray-400 animate-pulse">{{ __('relova::ui.loading_tables') }}</div>
                                                        <div wire:loading.remove wire:target="connectionUid" class="px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-sm text-gray-400 dark:text-gray-500 italic">{{ __('relova::ui.no_tables') }}</div>
                                                    @else
                                                        <div class="px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-700 border border-dashed border-gray-300 dark:border-gray-600 text-sm text-gray-400 dark:text-gray-500 italic">{{ __('relova::ui.select_connection_first') }}</div>
                                                    @endif
                                                    @error('remoteTable') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                                    <div class="mt-2 flex items-center gap-2">
                                                        <label class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 shrink-0 whitespace-nowrap">{{ __('relova::ui.remote_pk_column') }}</label>
                                                        <input wire:model="remotePkColumn" type="text" placeholder="id"
                                                            class="flex-1 min-w-0 px-2.5 py-1.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-xs font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                    </div>
                                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{{ __('relova::ui.remote_pk_column_hint') }}</p>
                                                    @error('remotePkColumn') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                                </div>
                                            </div>
                                            @if ($columnsError)
                                                <div class="flex items-start gap-3 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50">
                                                    <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                                                    <p class="text-xs text-red-600 dark:text-red-400">{{ $columnsError }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Step 2: Field mappings (remote → local) --}}
                                    <div class="rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden">
                                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex items-center gap-3 border-b border-gray-200 dark:border-gray-600">
                                            <span class="w-6 h-6 rounded-full bg-sky-600 text-white text-xs font-bold flex items-center justify-center shrink-0">2</span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('relova::ui.field_mappings') }}</p>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Map each remote column to a local model field. Remote data flows left to right into your local table.</p>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @if (count($fieldMappingRows) > 0)
                                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300">{{ count($fieldMappingRows) }} {{ count($fieldMappingRows) === 1 ? 'field' : 'fields' }}</span>
                                                @endif
                                                <button type="button" wire:click="addFieldRow"
                                                    class="flex items-center gap-1 text-xs text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 px-2 py-1 rounded-lg transition-colors font-semibold">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                                    {{ __('relova::ui.add_field_mapping') }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            @if (count($fieldMappingRows) > 0)
                                                <div class="grid grid-cols-[1fr_auto_1fr_2rem] gap-2 mb-2 px-1">
                                                    <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">Remote column (source)</span>
                                                    <span></span>
                                                    <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">Local field (destination)</span>
                                                    <span></span>
                                                </div>
                                                <div class="space-y-2">
                                                    @foreach ($fieldMappingRows as $i => $row)
                                                        <div wire:key="frow-{{ $i }}" class="grid grid-cols-[1fr_auto_1fr_2rem] items-center gap-2">
                                                            @if (count($allColumns) > 0)
                                                                <select wire:model="fieldMappingRows.{{ $i }}.remote"
                                                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                                                    <option value="">— remote —</option>
                                                                    @foreach ($allColumns as $col)
                                                                        <option value="{{ $col['name'] }}">{{ $col['name'] }}@if ($col['type'] ?? '') ({{ $col['type'] }})@endif</option>
                                                                    @endforeach
                                                                </select>
                                                            @else
                                                                <input wire:model="fieldMappingRows.{{ $i }}.remote" type="text" placeholder="REMOTE_COLUMN"
                                                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                            @endif
                                                            <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                                            @if (count($localColumns) > 0)
                                                                <select wire:model="fieldMappingRows.{{ $i }}.local"
                                                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                                                    <option value="">— local field —</option>
                                                                    @foreach ($localColumns as $lcol)
                                                                        <option value="{{ $lcol }}">{{ $lcol }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @else
                                                                <input wire:model="fieldMappingRows.{{ $i }}.local" type="text" placeholder="local_field"
                                                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                            @endif
                                                            <button type="button" wire:click="removeFieldRow({{ $i }})"
                                                                class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">&times;</button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-sm text-gray-400 dark:text-gray-500 italic text-center py-4">No field mappings yet — click "Add mapping row" above to start.</p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Step 3: Display snapshot fields --}}
                                    <div class="rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden">
                                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex items-center gap-3 border-b border-gray-200 dark:border-gray-600">
                                            <span class="w-6 h-6 rounded-full bg-sky-600 text-white text-xs font-bold flex items-center justify-center shrink-0">3</span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('relova::ui.display_fields') }}</p>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Columns cached in the snapshot — shown in search results and pickers without hitting the remote DB on every render.</p>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @if (count($displayFieldSelections) > 0)
                                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300">{{ count($displayFieldSelections) }} selected</span>
                                                @endif
                                                @if (count($remoteColumns) > 0)
                                                    <button type="button" wire:click="selectAllDisplayFields" class="text-[11px] text-sky-600 dark:text-sky-400 hover:underline font-semibold">{{ __('relova::ui.select_all') }}</button>
                                                    <span class="text-gray-300 dark:text-gray-600">|</span>
                                                    <button type="button" wire:click="clearDisplayFields" class="text-[11px] text-gray-500 dark:text-gray-400 hover:underline">{{ __('relova::ui.clear_all') }}</button>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            @if (count($allColumns) > 0)
                                                @php
                                                    $columnGroups = collect($allColumns)->groupBy(function ($col) {
                                                        return str_contains($col['name'], '.') ? explode('.', $col['name'])[0] : '__primary__';
                                                    });
                                                @endphp
                                                <div class="max-h-56 overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 p-3 space-y-3">
                                                    @foreach ($columnGroups as $groupKey => $groupCols)
                                                        <div>
                                                            @if ($groupKey !== '__primary__')
                                                                <p class="text-[10px] uppercase tracking-wider font-bold text-indigo-500 dark:text-indigo-400 mb-1.5">{{ $groupKey }}</p>
                                                            @endif
                                                            <div class="grid grid-cols-3 gap-1">
                                                                @foreach ($groupCols as $col)
                                                                    <label wire:key="dcol-{{ $col['name'] }}"
                                                                        class="flex items-center gap-2 px-2 py-1.5 rounded-lg cursor-pointer transition-colors {{ in_array($col['name'], $displayFieldSelections) ? 'bg-sky-100 dark:bg-sky-900/30' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                                                        <input type="checkbox"
                                                                            wire:click="toggleDisplayField('{{ $col['name'] }}')"
                                                                            @checked(in_array($col['name'], $displayFieldSelections))
                                                                            class="rounded text-sky-600 focus:ring-sky-500 shrink-0" />
                                                                        <span class="font-mono text-xs truncate {{ in_array($col['name'], $displayFieldSelections) ? 'text-sky-700 dark:text-sky-300 font-semibold' : 'text-zinc-700 dark:text-gray-300' }}">{{ $col['name'] }}</span>
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                @if (count($displayFieldSelections) > 0)
                                                    <div class="flex flex-wrap gap-2 mb-3">
                                                        @foreach ($displayFieldSelections as $sel)
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 rounded-full text-xs font-mono">
                                                                {{ $sel }}
                                                                <button type="button" wire:click="toggleDisplayField('{{ $sel }}')" class="text-sky-500 hover:text-red-500 leading-none">&times;</button>
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                <div class="flex gap-2">
                                                    <input wire:model="newDisplayField" type="text" placeholder="COLUMN_NAME"
                                                        class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                    <button type="button" wire:click="addDisplayFieldManual"
                                                        class="px-3 py-2 bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 text-sm font-semibold rounded-lg hover:bg-sky-200 dark:hover:bg-sky-900/50 transition-colors">
                                                        Add
                                                    </button>
                                                </div>
                                                @if ($remoteTable === '')
                                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1.5 italic">{{ __('relova::ui.select_table_first') }}</p>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Step 4: Local relationship anchors --}}
                                    <div class="rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden">
                                        <button type="button" wire:click="toggleSection('showDefaultValues')"
                                            class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex items-center gap-3 border-b border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700/70 transition-colors text-left">
                                            <span class="w-6 h-6 rounded-full bg-amber-500 text-white text-xs font-bold flex items-center justify-center shrink-0">4</span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('relova::ui.default_values') }}</p>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Set which local {{ $moduleKey ?: 'entity' }} record each imported remote row should be linked to by default.</p>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @php $setCount = count(array_filter($defaultValues, fn($v) => $v !== '' && $v !== null)); @endphp
                                                @if ($setCount > 0)
                                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">{{ $setCount }} set</span>
                                                @else
                                                    <span class="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">optional</span>
                                                @endif
                                                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 transition-transform duration-200 {{ $showDefaultValues ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                            </div>
                                        </button>
                                        @if ($showDefaultValues)
                                        <div class="p-4">
                                            @if ($moduleKey === '')
                                                <p class="text-sm text-gray-400 dark:text-gray-500 italic text-center py-2">Select a local table first to see which relationship anchors are available.</p>
                                            @elseif (empty($localFkColumns))
                                                <p class="text-sm text-gray-400 dark:text-gray-500 italic text-center py-2">No known local FK relationships found in the <span class="font-mono">{{ $moduleKey }}</span> table.</p>
                                            @else
                                                <div class="space-y-3">
                                                    @foreach ($localFkColumns as $col)
                                                        <div wire:key="dfv-{{ $col }}" class="flex items-center gap-4">
                                                            <label class="w-44 shrink-0">
                                                                <span class="font-mono text-sm text-zinc-900 dark:text-white">{{ $col }}</span>
                                                                <span class="block text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ str_replace(['_id', '_'], ['', ' '], $col) }}</span>
                                                            </label>
                                                            <select wire:model="defaultValues.{{ $col }}"
                                                                class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:focus:border-amber-400 transition-colors">
                                                                <option value="">— none (leave NULL) —</option>
                                                                @foreach ($localFkOptions[$col] as $fkOpt)
                                                                    <option value="{{ $fkOpt->id }}">{{ $fkOpt->label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        @endif
                                    </div>

                                    {{-- Step 5: Always-on filters (collapsible, advanced) --}}
                                    <div class="rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden">
                                        <button type="button" wire:click="toggleSection('showFilters')"
                                            class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex items-center gap-3 border-b border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700/70 transition-colors text-left">
                                            <span class="w-6 h-6 rounded-full bg-violet-500 text-white text-xs font-bold flex items-center justify-center shrink-0">5</span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('relova::ui.always_on_filters') }}</p>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Appended to every remote query automatically — useful for excluding inactive rows. Remote data is never copied locally.</p>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @if (count($filterRows) > 0)
                                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">{{ count($filterRows) }} {{ count($filterRows) === 1 ? 'filter' : 'filters' }}</span>
                                                @else
                                                    <span class="text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">advanced</span>
                                                @endif
                                                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 transition-transform duration-200 {{ $showFilters ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                            </div>
                                        </button>
                                        @if ($showFilters)
                                        <div class="p-4">
                                            @if (count($filterRows) > 0)
                                                <div class="grid grid-cols-[1fr_auto_1fr_2rem] gap-2 mb-2 px-1">
                                                    <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.filter_column') }}</span>
                                                    <span></span>
                                                    <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.filter_value') }}</span>
                                                    <span></span>
                                                </div>
                                                <div class="space-y-2 mb-4">
                                                    @foreach ($filterRows as $i => $frow)
                                                        <div wire:key="filt-{{ $i }}" class="grid grid-cols-[1fr_auto_1fr_2rem] items-center gap-2">
                                                            @if (count($remoteColumns) > 0)
                                                                <select wire:model="filterRows.{{ $i }}.column"
                                                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:focus:border-violet-400">
                                                                    <option value="">—</option>
                                                                    @foreach ($remoteColumns as $col)
                                                                        <option value="{{ $col['name'] }}">{{ $col['name'] }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @else
                                                                <input wire:model="filterRows.{{ $i }}.column" type="text" placeholder="ACTIVE_FLAG"
                                                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:focus:border-violet-400" />
                                                            @endif
                                                            <span class="text-gray-400 dark:text-gray-500 text-sm font-mono shrink-0">=</span>
                                                            <input wire:model="filterRows.{{ $i }}.value" type="text" placeholder="1"
                                                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:focus:border-violet-400" />
                                                            <button type="button" wire:click="removeFilterRow({{ $i }})"
                                                                class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">&times;</button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-sm text-gray-400 dark:text-gray-500 italic mb-4">No filters yet — add one to restrict which remote rows are fetched.</p>
                                            @endif
                                            <button type="button" wire:click="addFilterRow"
                                                class="flex items-center gap-1.5 text-sm text-violet-600 dark:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20 px-3 py-1.5 rounded-lg transition-colors font-semibold">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                                {{ __('relova::ui.add_filter') }}
                                            </button>
                                        </div>
                                        @endif
                                    </div>

                                    {{-- Step 6: Settings --}}
                                    <div class="rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden">
                                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex items-center gap-3 border-b border-gray-200 dark:border-gray-600">
                                            <span class="w-6 h-6 rounded-full bg-gray-400 dark:bg-gray-500 text-white text-xs font-bold flex items-center justify-center shrink-0">6</span>
                                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">Settings</p>
                                        </div>
                                        <div class="p-4">
                                            <input type="hidden" wire:model="syncBehavior" />
                                            <input type="hidden" wire:model="cacheTtlMinutes" />
                                            <label class="flex items-center justify-between cursor-pointer px-3 py-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                                <div>
                                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('relova::ui.active') }}</p>
                                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">When disabled this mapping is ignored — remote data will not be fetched or displayed.</p>
                                                </div>
                                                <div class="relative shrink-0 ml-6">
                                                    <input type="checkbox" wire:model="active" class="sr-only peer" />
                                                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-checked:bg-sky-600 rounded-full transition-colors duration-200 peer-focus:ring-2 peer-focus:ring-sky-500/30"></div>
                                                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5"></div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex items-center justify-between pt-2">
                                        <button type="button" wire:click="closeForm"
                                            class="px-5 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200">
                                            {{ __('relova::ui.cancel') }}
                                        </button>
                                        <button type="submit"
                                            class="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                            {{ __('relova::ui.save') }}
                                        </button>
                                    </div>

                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                {{-- ── Mappings list ── --}}
                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
                    @if ($mappings->isEmpty())
                        <div class="text-center py-16">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('relova::ui.no_mappings_yet') }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Create a mapping to start pulling remote data into a local module.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40">
                                        <th class="text-left px-4 py-3 text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400">{{ __('relova::ui.module') }}</th>
                                        <th class="text-left px-4 py-3 text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400">{{ __('relova::ui.connection') }}</th>
                                        <th class="text-left px-4 py-3 text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400">{{ __('relova::ui.remote_table') }}</th>
                                        <th class="text-left px-4 py-3 text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400">Details</th>
                                        <th class="text-left px-4 py-3 text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400">{{ __('relova::ui.status') }}</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($mappings as $mapping)
                                        <tr wire:key="map-{{ $mapping->uid }}" class="hover:bg-sky-50/50 dark:hover:bg-gray-700/40 transition-colors">
                                            <td class="px-4 py-3.5">
                                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $mapping->module_key }}</span>
                                                @if ($mapping->premises_id)
                                                    <div class="mt-1">
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] rounded-md bg-violet-50 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400 border border-violet-100 dark:border-violet-800">
                                                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/></svg>
                                                            Premises #{{ $mapping->premises_id }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5 text-zinc-700 dark:text-gray-300">{{ optional($mapping->connection)->name ?? '—' }}</td>
                                            <td class="px-4 py-3.5">
                                                <span class="font-mono text-xs bg-gray-100 dark:bg-gray-700 text-zinc-700 dark:text-gray-300 px-2 py-0.5 rounded-md">{{ $mapping->remote_table }}</span>
                                                @if ($mapping->remote_pk_column && $mapping->remote_pk_column !== 'id')
                                                    <div class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">pk: {{ $mapping->remote_pk_column }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <div class="flex flex-wrap gap-1">
                                                    @if ($mapping->field_mappings && count($mapping->field_mappings))
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-semibold rounded-md bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-300 border border-sky-100 dark:border-sky-800/50">
                                                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                                            {{ count($mapping->field_mappings) }} {{ count($mapping->field_mappings) === 1 ? 'field' : 'fields' }}
                                                        </span>
                                                    @endif
                                                    @if ($mapping->joins && count($mapping->joins))
                                                        <span class="inline-flex px-1.5 py-0.5 text-[10px] font-semibold rounded-md bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800/50">{{ count($mapping->joins) }} {{ count($mapping->joins) === 1 ? 'join' : 'joins' }}</span>
                                                    @endif
                                                    @if ($mapping->default_values && count($mapping->default_values))
                                                        <span class="inline-flex px-1.5 py-0.5 text-[10px] font-semibold rounded-md bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border border-amber-100 dark:border-amber-800/50">{{ count($mapping->default_values) }} default{{ count($mapping->default_values) !== 1 ? 's' : '' }}</span>
                                                    @endif
                                                    @if ($mapping->filters && count($mapping->filters))
                                                        <span class="inline-flex px-1.5 py-0.5 text-[10px] font-semibold rounded-md bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-400 border border-violet-100 dark:border-violet-800/50">{{ count($mapping->filters) }} filter{{ count($mapping->filters) !== 1 ? 's' : '' }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                @if ($mapping->active)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                                        {{ __('relova::ui.active') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 shrink-0"></span>
                                                        {{ __('relova::ui.inactive') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button wire:click="toggle('{{ $mapping->uid }}')" type="button"
                                                        class="px-2.5 py-1.5 text-[11px] font-semibold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                                        {{ $mapping->active ? __('relova::ui.disable') : __('relova::ui.enable') }}
                                                    </button>
                                                    <button wire:click="openEdit('{{ $mapping->uid }}')" type="button"
                                                        class="px-2.5 py-1.5 text-[11px] font-semibold bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors">
                                                        {{ __('relova::ui.edit') }}
                                                    </button>
                                                    <button wire:click="delete('{{ $mapping->uid }}')" wire:confirm="{{ __('relova::ui.confirm_delete') }}" type="button"
                                                        class="px-2.5 py-1.5 text-[11px] font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                                        {{ __('relova::ui.delete') }}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            </article>
        </div>
    </div>
</div>
