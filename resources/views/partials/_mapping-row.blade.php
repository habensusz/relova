{{-- Reusable mapping list row — iTenance index-list-row pattern.
     Required: $m (RelovaConnectorModuleMapping model)
     Optional: $showActions (bool) — render Toggle/Edit/Delete inline actions --}}
@php($showActions = $showActions ?? false)
<div wire:key="map-row-{{ $m->uid }}"
    @class([
        'group relative flex items-stretch gap-0 rounded-2xl border bg-white dark:bg-gray-800 overflow-hidden transition-all duration-200',
        'border-emerald-200 dark:border-emerald-800/50 hover:shadow-md hover:shadow-emerald-500/10' => $m->active,
        'border-gray-200 dark:border-gray-700 hover:shadow-md' => ! $m->active,
    ])>

    {{-- Left accent stripe (color reflects active state) --}}
    <div @class([
        'w-1 shrink-0',
        'bg-emerald-500' => $m->active,
        'bg-gray-300 dark:bg-gray-600' => ! $m->active,
    ])></div>

    {{-- Module icon --}}
    <div class="flex items-center px-4 shrink-0">
        <div @class([
            'w-9 h-9 rounded-xl flex items-center justify-center',
            'bg-gradient-to-br from-emerald-100 to-sky-200 dark:from-emerald-900/40 dark:to-sky-900/40' => $m->active,
            'bg-gray-100 dark:bg-gray-700' => ! $m->active,
        ])>
            <svg @class([
                'w-4.5 h-4.5',
                'text-emerald-700 dark:text-emerald-300' => $m->active,
                'text-gray-400 dark:text-gray-500' => ! $m->active,
            ]) fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
        </div>
    </div>

    {{-- Body --}}
    <div class="flex-1 min-w-0 py-3 pr-4">
        {{-- Title row --}}
        <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ $m->module_key }}</h3>

            @if ($m->active)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    {{ __('relova::ui.active') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    {{ __('relova::ui.inactive') }}
                </span>
            @endif

            @if ($m->premises_id)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] rounded-md bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-300 border border-violet-100 dark:border-violet-800/50">
                    Premises #{{ $m->premises_id }}
                </span>
            @endif
        </div>

        {{-- Meta row --}}
        <div class="mt-1 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400 flex-wrap">
            <span class="inline-flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                {{ optional($m->connection)->name ?? '—' }}
            </span>
            <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
            <span class="inline-flex items-center gap-1">
                <span class="font-mono bg-gray-100 dark:bg-gray-700 text-zinc-700 dark:text-gray-200 px-1.5 py-0.5 rounded">{{ $m->remote_table }}</span>
                @if ($m->remote_pk_column && $m->remote_pk_column !== 'id')
                    <span class="text-[10px]">pk: {{ $m->remote_pk_column }}</span>
                @endif
            </span>
        </div>

        {{-- Counts row --}}
        <div class="mt-2 flex items-center gap-1 flex-wrap">
            @if ($m->field_mappings && count($m->field_mappings))
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-semibold rounded-md bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-300 border border-sky-100 dark:border-sky-800/50">
                    {{ count($m->field_mappings) }} {{ count($m->field_mappings) === 1 ? __('relova::ui.field_singular') : __('relova::ui.field_plural') }}
                </span>
            @endif
            @if ($m->joins && count($m->joins))
                <span class="inline-flex px-1.5 py-0.5 text-[10px] font-semibold rounded-md bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800/50">
                    {{ count($m->joins) }} {{ count($m->joins) === 1 ? __('relova::ui.join_singular') : __('relova::ui.join_plural') }}
                </span>
            @endif
            @if ($m->default_values && count($m->default_values))
                <span class="inline-flex px-1.5 py-0.5 text-[10px] font-semibold rounded-md bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border border-amber-100 dark:border-amber-800/50">
                    {{ count($m->default_values) }} {{ count($m->default_values) === 1 ? __('relova::ui.default_singular') : __('relova::ui.default_plural') }}
                </span>
            @endif
            @if ($m->filters && count($m->filters))
                <span class="inline-flex px-1.5 py-0.5 text-[10px] font-semibold rounded-md bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-300 border border-violet-100 dark:border-violet-800/50">
                    {{ count($m->filters) }} {{ count($m->filters) === 1 ? __('relova::ui.filter_singular') : __('relova::ui.filter_plural') }}
                </span>
            @endif
        </div>
    </div>

    {{-- Action buttons --}}
    @if ($showActions)
        <div class="flex items-center gap-1.5 px-4 shrink-0 border-l border-gray-100 dark:border-gray-700">
            <button wire:click="toggle('{{ $m->uid }}')" type="button"
                class="px-2.5 py-1.5 text-[11px] font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                {{ $m->active ? __('relova::ui.disable') : __('relova::ui.enable') }}
            </button>
            <button wire:click="openEdit('{{ $m->uid }}')" type="button"
                class="px-2.5 py-1.5 text-[11px] font-semibold bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors">
                {{ __('relova::ui.edit') }}
            </button>
            <button wire:click="delete('{{ $m->uid }}')" wire:confirm="{{ __('relova::ui.confirm_delete') }}" type="button"
                class="px-2.5 py-1.5 text-[11px] font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                {{ __('relova::ui.delete') }}
            </button>
        </div>
    @endif
</div>
