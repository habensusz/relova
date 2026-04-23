<div class="relative mx-auto container pb-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="py-3 text-gray-900 dark:text-gray-100">
            <article class="container mx-auto" style="min-height: 100px;">

                {{-- Main card: header + inline form --}}
                <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg overflow-hidden mb-6">
                    <div class="h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500"></div>

                    {{-- Card header --}}
                    <div class="px-6 py-5 flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('relova::ui.module_mappings') }}</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ __('relova::ui.module_mappings_subtitle') }}</p>
                        </div>
                        @if (!$showForm)
                            <button wire:click="openCreate" type="button"
                                class="px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                                {{ __('relova::ui.new_mapping') }}
                            </button>
                        @endif
                    </div>

                    {{-- Inline form --}}
                    @if ($showForm)
                        <div class="border-t border-gray-100 dark:border-gray-700">
                            <form wire:submit="save" class="px-6 py-6 space-y-6">

                                {{-- Title row --}}
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-base font-bold text-zinc-900 dark:text-white">
                                            {{ $editing ? __('relova::ui.edit_mapping') : __('relova::ui.new_mapping') }}
                                        </h2>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('relova::ui.mapping_form_subtitle') }}</p>
                                    </div>
                                    <button type="button" wire:click="closeForm"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl leading-none">&times;</button>
                                </div>

                                {{-- ① Connection --}}
                                <div>
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1 block">{{ __('relova::ui.connection') }}</label>
                                    <select wire:model.live="connectionUid"
                                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                        <option value="">—</option>
                                        @foreach ($connections as $c)
                                            <option value="{{ $c->uid }}">{{ $c->name }} ({{ $c->driver }})</option>
                                        @endforeach
                                    </select>
                                    @error('connectionUid') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>

                                {{-- ② Module key + Remote table --}}
                                <div class="grid grid-cols-2 gap-4">

                                    {{-- Module key --}}
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('relova::ui.module_key') }}</label>
                                            @if ($editing && $moduleKey !== '')
                                                <button type="button" wire:click="applyModuleDefaults('{{ $moduleKey }}')"
                                                    class="text-xs text-sky-600 dark:text-sky-400 hover:underline">{{ __('relova::ui.reload_defaults') }}</button>
                                            @endif
                                        </div>
                                        <select wire:model.live="moduleKey"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                            <option value="">—</option>
                                            @foreach ($localTables as $tbl)
                                                <option value="{{ $tbl }}">{{ $tbl }}</option>
                                            @endforeach
                                        </select>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{{ __('relova::ui.module_key_hint') }}</p>
                                        @error('moduleKey') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    {{-- Remote table --}}
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1 block">{{ __('relova::ui.remote_table') }}</label>

                                        @if ($tablesError)
                                            <div class="px-3 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 text-xs text-red-600 dark:text-red-400">{{ $tablesError }}</div>
                                        @elseif (count($remoteTables) > 0)
                                            <div wire:loading wire:target="connectionUid" class="px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-sm text-gray-400 dark:text-gray-500">{{ __('relova::ui.loading_tables') }}</div>
                                            <select wire:loading.remove wire:target="connectionUid" wire:model.live="remoteTable"
                                                class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                                <option value="">—</option>
                                                @foreach ($remoteTables as $t)
                                                    <option value="{{ $t['name'] }}">{{ $t['name'] }}@if(isset($t['row_count']) && $t['row_count'] !== null) (~{{ number_format($t['row_count']) }})@endif</option>
                                                @endforeach
                                            </select>
                                        @elseif ($connectionUid !== '')
                                            <div wire:loading wire:target="connectionUid" class="px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-sm text-gray-400 animate-pulse">{{ __('relova::ui.loading_tables') }}</div>
                                            <div wire:loading.remove wire:target="connectionUid" class="px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-sm text-gray-400 dark:text-gray-500 italic">{{ __('relova::ui.no_tables') }}</div>
                                        @else
                                            <div class="px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-700 border border-dashed border-gray-300 dark:border-gray-600 text-sm text-gray-400 dark:text-gray-500 italic">{{ __('relova::ui.select_connection_first') }}</div>
                                        @endif

                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{{ __('relova::ui.remote_table_hint') }}</p>
                                        @error('remoteTable') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror

                                        {{-- Remote PK column --}}
                                        <div class="mt-3 flex items-center gap-2">
                                            <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 shrink-0 whitespace-nowrap">{{ __('relova::ui.remote_pk_column') }}</label>
                                            <input wire:model="remotePkColumn" type="text" placeholder="id"
                                                class="flex-1 min-w-0 px-3 py-1.5 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-xs font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                        </div>
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{{ __('relova::ui.remote_pk_column_hint') }}</p>
                                        @error('remotePkColumn') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                {{-- Columns error --}}
                                @if ($columnsError)
                                    <div class="flex items-start gap-3 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50">
                                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                                        <p class="text-xs text-red-600 dark:text-red-400">{{ $columnsError }}</p>
                                    </div>
                                @endif

                                {{-- ③ Join tables (optional) --}}
                                @if ($remoteTable !== '')
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('relova::ui.joins') }}</label>
                                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ __('relova::ui.joins_hint') }}</p>
                                        </div>
                                        <button type="button" wire:click="addJoinRow"
                                            class="text-xs text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                            {{ __('relova::ui.add_join') }}
                                        </button>
                                    </div>

                                    @if (count($joinRows) > 0)
                                        {{-- Column headers --}}
                                        <div class="grid grid-cols-[1.5fr_1fr_1fr_0.5fr_2rem] gap-2 mb-1 px-1">
                                            <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.join_table') }}</span>
                                            <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.join_foreign_key') }}</span>
                                            <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.join_references') }}</span>
                                            <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.join_type') }}</span>
                                            <span></span>
                                        </div>

                                        <div class="space-y-2">
                                            @foreach ($joinRows as $ji => $jrow)
                                                @php $jTable = $jrow['table'] ?? ''; @endphp
                                                <div wire:key="jrow-{{ $ji }}" class="grid grid-cols-[1.5fr_1fr_1fr_0.5fr_2rem] items-center gap-2">
                                                    {{-- Join table name --}}
                                                    @if (count($remoteTables) > 0)
                                                        <select wire:model="joinRows.{{ $ji }}.table"
                                                            wire:change="loadJoinTableColumns({{ $ji }})"
                                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                                            <option value="">— table —</option>
                                                            @foreach ($remoteTables as $t)
                                                                <option value="{{ $t['name'] }}">{{ $t['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input wire:model.blur="joinRows.{{ $ji }}.table"
                                                            wire:blur="loadJoinTableColumns({{ $ji }})"
                                                            type="text" placeholder="manufacturers"
                                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                    @endif

                                                    {{-- FK in main table --}}
                                                    @if (count($remoteColumns) > 0)
                                                        <select wire:model="joinRows.{{ $ji }}.foreign_key"
                                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                                            <option value="">— fk col —</option>
                                                            @foreach ($remoteColumns as $col)
                                                                <option value="{{ $col['name'] }}">{{ $col['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input wire:model="joinRows.{{ $ji }}.foreign_key" type="text" placeholder="manufacturer_id"
                                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                    @endif

                                                    {{-- Referenced column in joined table --}}
                                                    @if ($jTable !== '' && !empty($joinedTableColumns[$jTable]))
                                                        <select wire:model="joinRows.{{ $ji }}.references"
                                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                                            @foreach ($joinedTableColumns[$jTable] as $col)
                                                                <option value="{{ $col['name'] }}">{{ $col['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input wire:model="joinRows.{{ $ji }}.references" type="text" placeholder="id"
                                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                    @endif

                                                    {{-- Join type --}}
                                                    <select wire:model="joinRows.{{ $ji }}.type"
                                                        class="w-full px-2 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-xs font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                                        <option value="LEFT">LEFT</option>
                                                        <option value="INNER">INNER</option>
                                                    </select>

                                                    {{-- Remove row --}}
                                                    <button type="button" wire:click="removeJoinRow({{ $ji }})"
                                                        class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">&times;</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="px-4 py-3 rounded-xl border border-dashed border-gray-200 dark:border-gray-600 text-sm text-gray-400 dark:text-gray-500 italic">
                                            {{ __('relova::ui.joins_empty') }}
                                        </div>
                                    @endif
                                </div>
                                @endif

                                {{-- ④ Field mappings builder --}}
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('relova::ui.field_mappings') }}</label>
                                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ __('relova::ui.field_mappings_hint') }}</p>
                                        </div>
                                    </div>

                                    {{-- Column headers --}}
                                    <div class="grid grid-cols-[1fr_auto_1fr_2rem] gap-2 mb-1 px-1">
                                        <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.local_field') }}</span>
                                        <span></span>
                                        <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.remote_column') }}</span>
                                        <span></span>
                                    </div>

                                    <div class="space-y-2">
                                        @foreach ($fieldMappingRows as $i => $row)
                                            <div wire:key="frow-{{ $i }}" class="grid grid-cols-[1fr_auto_1fr_2rem] items-center gap-2">
                                                <input wire:model="fieldMappingRows.{{ $i }}.local" type="text" placeholder="machine_name"
                                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                                @if (count($allColumns) > 0)
                                                    <select wire:model="fieldMappingRows.{{ $i }}.remote"
                                                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                                        <option value="">—</option>
                                                        @foreach ($allColumns as $col)
                                                            <option value="{{ $col['name'] }}">{{ $col['name'] }}@if ($col['type'] ?? '') ({{ $col['type'] }})@endif</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input wire:model="fieldMappingRows.{{ $i }}.remote" type="text" placeholder="EQUIPMENT_NAME"
                                                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                @endif
                                                <button type="button" wire:click="removeFieldRow({{ $i }})"
                                                    class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">&times;</button>
                                            </div>
                                        @endforeach
                                    </div>

                                    <button type="button" wire:click="addFieldRow"
                                        class="mt-3 text-sm text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                        {{ __('relova::ui.add_field_mapping') }}
                                    </button>
                                </div>

                                {{-- ④½ Default values for required columns --}}
                                <div>
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1 block">{{ __('relova::ui.default_values') }}</label>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mb-3">{{ __('relova::ui.default_values_hint') }}</p>

                                    @if (count($defaultValueRows) > 0)
                                        {{-- Column headers --}}
                                        <div class="grid grid-cols-[1fr_auto_1fr_2rem] gap-2 mb-1 px-1">
                                            <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.local_field') }}</span>
                                            <span></span>
                                            <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.default_value') }}</span>
                                            <span></span>
                                        </div>

                                        <div class="space-y-2 mb-3">
                                            @foreach ($defaultValueRows as $i => $drow)
                                                <div wire:key="dv-{{ $i }}" class="grid grid-cols-[1fr_auto_1fr_2rem] items-center gap-2">
                                                    <input wire:model="defaultValueRows.{{ $i }}.column" type="text" placeholder="manufacturer_id"
                                                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:focus:border-amber-400" />
                                                    <span class="text-gray-400 dark:text-gray-500 text-sm font-mono shrink-0">=</span>
                                                    <input wire:model="defaultValueRows.{{ $i }}.value" type="text" placeholder="1"
                                                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:focus:border-amber-400" />
                                                    <button type="button" wire:click="removeDefaultValueRow({{ $i }})"
                                                        class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">&times;</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <button type="button" wire:click="addDefaultValueRow"
                                        class="text-sm text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                        {{ __('relova::ui.add_default_value') }}
                                    </button>
                                </div>

                                {{-- ④ Display fields --}}
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('relova::ui.display_fields') }}</label>
                                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ __('relova::ui.display_fields_hint') }}</p>
                                        </div>
                                    @if (count($remoteColumns) > 0)
                                        <div class="flex items-center gap-2 shrink-0">
                                            <button type="button" wire:click="selectAllDisplayFields" class="text-xs text-sky-600 dark:text-sky-400 hover:underline">{{ __('relova::ui.select_all') }}</button>
                                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                                <button type="button" wire:click="clearDisplayFields" class="text-xs text-gray-500 dark:text-gray-400 hover:underline">{{ __('relova::ui.clear_all') }}</button>
                                                @if (count($displayFieldSelections) > 0)
                                                    <span class="text-[11px] bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 px-2 py-0.5 rounded-full font-medium">{{ count($displayFieldSelections) }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    @if (count($allColumns) > 0)
                                        {{-- Checkbox grid from loaded columns, grouped by table when joins are present --}}
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
                                        {{-- No columns loaded — show existing selections as tags + manual add --}}
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
                                                {{ __('relova::ui.add_field_mapping') }}
                                            </button>
                                        </div>

                                        @if ($remoteTable === '')
                                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1.5 italic">{{ __('relova::ui.select_table_first') }}</p>
                                        @endif
                                    @endif
                                </div>

                                {{-- ⑤ Always-on filters --}}
                                <div>
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1 block">{{ __('relova::ui.always_on_filters') }}</label>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mb-3">{{ __('relova::ui.filters_hint') }}</p>

                                    @if (count($filterRows) > 0)
                                        {{-- Column headers --}}
                                        <div class="grid grid-cols-[1fr_auto_1fr_2rem] gap-2 mb-1 px-1">
                                            <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.filter_column') }}</span>
                                            <span></span>
                                            <span class="text-[11px] uppercase tracking-wide font-semibold text-gray-400 dark:text-gray-500">{{ __('relova::ui.filter_value') }}</span>
                                            <span></span>
                                        </div>

                                        <div class="space-y-2 mb-3">
                                            @foreach ($filterRows as $i => $frow)
                                                <div wire:key="filt-{{ $i }}" class="grid grid-cols-[1fr_auto_1fr_2rem] items-center gap-2">
                                                    @if (count($remoteColumns) > 0)
                                                        <select wire:model="filterRows.{{ $i }}.column"
                                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                                            <option value="">—</option>
                                                            @foreach ($remoteColumns as $col)
                                                                <option value="{{ $col['name'] }}">{{ $col['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input wire:model="filterRows.{{ $i }}.column" type="text" placeholder="ACTIVE_FLAG"
                                                            class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                    @endif
                                                    <span class="text-gray-400 dark:text-gray-500 text-sm font-mono shrink-0">=</span>
                                                    <input wire:model="filterRows.{{ $i }}.value" type="text" placeholder="1"
                                                        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 font-mono focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                    <button type="button" wire:click="removeFilterRow({{ $i }})"
                                                        class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">&times;</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <button type="button" wire:click="addFilterRow"
                                        class="text-sm text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                        {{ __('relova::ui.add_filter') }}
                                    </button>
                                </div>

                                {{-- ⑥ Sync behavior — hidden; defaults to snapshot_cache (best general performance: Redis-cached, lazy-filled, TTL-refreshed). --}}
                                <input type="hidden" wire:model="syncBehavior" />
                                <input type="hidden" wire:model="cacheTtlMinutes" />

                                {{-- ⑦ Active --}}
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                    <input type="checkbox" wire:model="active" class="rounded text-sky-600 focus:ring-sky-500" />
                                    {{ __('relova::ui.active') }}
                                </label>

                                {{-- Actions --}}
                                <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100 dark:border-gray-700">
                                    <button type="button" wire:click="closeForm"
                                        class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200">{{ __('relova::ui.cancel') }}</button>
                                    <button type="submit"
                                        class="px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">{{ __('relova::ui.save') }}</button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                {{-- Mappings list --}}
                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">

                    @if ($mappings->isEmpty())
                        <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">{{ __('relova::ui.no_mappings_yet') }}</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <th class="text-left px-4 py-3">{{ __('relova::ui.module') }}</th>
                                        <th class="text-left px-4 py-3">{{ __('relova::ui.connection') }}</th>
                                        <th class="text-left px-4 py-3">{{ __('relova::ui.remote_table') }}</th>
                                        <th class="text-left px-4 py-3">{{ __('relova::ui.status') }}</th>
                                        <th class="text-right px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($mappings as $mapping)
                                        <tr wire:key="map-{{ $mapping->uid }}" class="odd:bg-white even:bg-gray-50/60 dark:odd:bg-gray-800 dark:even:bg-gray-700/30 hover:bg-sky-50 dark:hover:bg-gray-700/60 transition-colors">
                                            <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">
                                                {{ $mapping->module_key }}
                                                @if ($mapping->premises_id)
                                                    <span class="ml-1.5 inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] rounded bg-violet-50 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400 border border-violet-100 dark:border-violet-800">
                                                        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/></svg>
                                                        {{ __('relova::ui.premises') }} #{{ $mapping->premises_id }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-zinc-700 dark:text-gray-300">{{ optional($mapping->connection)->name ?? '—' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="font-mono text-xs text-zinc-700 dark:text-gray-300">{{ $mapping->remote_table }}</span>
                                                @if ($mapping->remote_pk_column && $mapping->remote_pk_column !== 'id')
                                                    <span class="ml-1 text-[10px] text-gray-400 dark:text-gray-500">·pk: {{ $mapping->remote_pk_column }}</span>
                                                @endif
                                                @if ($mapping->joins && count($mapping->joins))
                                                    <span class="ml-1 inline-flex px-1.5 py-0.5 text-[10px] rounded bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400">{{ count($mapping->joins) }} join{{ count($mapping->joins) > 1 ? 's' : '' }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if ($mapping->active)
                                                    <span class="inline-flex px-2 py-0.5 text-[11px] rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">{{ __('relova::ui.active') }}</span>
                                                @else
                                                    <span class="inline-flex px-2 py-0.5 text-[11px] rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ __('relova::ui.inactive') }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right space-x-1">
                                                <button wire:click="toggle('{{ $mapping->uid }}')" type="button" class="px-2 py-1 text-[11px] font-semibold text-sky-700 dark:text-sky-400 hover:underline">{{ $mapping->active ? __('relova::ui.disable') : __('relova::ui.enable') }}</button>
                                                <button wire:click="openEdit('{{ $mapping->uid }}')" type="button" class="px-2 py-1 text-[11px] font-semibold bg-sky-600 hover:bg-sky-700 text-white rounded-lg">{{ __('relova::ui.edit') }}</button>
                                                <button wire:click="delete('{{ $mapping->uid }}')" wire:confirm="{{ __('relova::ui.confirm_delete') }}" type="button" class="px-2 py-1 text-[11px] font-semibold bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 border border-red-100 dark:border-red-800 rounded-lg">{{ __('relova::ui.delete') }}</button>
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