{{--
    One trigger rule in the list.

    Required:
        $rule   Relova\Models\TriggerRule  (with `mapping` loaded)
--}}
@php
    $opLabels = [
        'changed' => __('relova::ui.trigger_op_changed'),
        'eq' => '=', 'neq' => '≠',
        'gt' => '>', 'gte' => '≥', 'lt' => '<', 'lte' => '≤',
        'above_threshold' => '>', 'below_threshold' => '<',
        'contains' => __('relova::ui.trigger_op_contains'),
        'in' => __('relova::ui.trigger_op_in'), 'not_in' => __('relova::ui.trigger_op_not_in'),
    ];
    $opLabel = $opLabels[$rule->operator] ?? $rule->operator;

    $val = $rule->value;
    if (is_array($val)) {
        $val = array_key_exists('v', $val) ? $val['v'] : implode(', ', $val);
    }

    $priorityStyles = [
        'low' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        'normal' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        'high' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'critical' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];
@endphp

<div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm px-4 py-3 flex items-start gap-3">

    {{-- Active dot --}}
    <span class="mt-1.5 w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $rule->active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600' }}"
          title="{{ $rule->active ? __('relova::ui.status_active') : __('relova::ui.inactive') }}"></span>

    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $rule->name }}</span>
            <span class="text-[11px] px-1.5 py-0.5 rounded font-medium {{ $priorityStyles[$rule->priority] ?? $priorityStyles['normal'] }}">
                {{ __('relova::ui.priority_'.$rule->priority) }}
            </span>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <span class="font-mono text-gray-700 dark:text-gray-300">{{ $rule->target_field }}</span>
            <span class="mx-1">{{ $opLabel }}</span>
            @if ($rule->operator !== 'changed')
                <span class="font-mono text-gray-700 dark:text-gray-300">{{ $val }}</span>
            @endif
            <span class="mx-1.5 text-gray-300 dark:text-gray-600">•</span>
            {{ optional($rule->mapping)->module_key ?? '—' }}
            <span class="text-gray-400 dark:text-gray-500">({{ optional($rule->mapping)->remote_table }})</span>
            <span class="mx-1.5 text-gray-300 dark:text-gray-600">•</span>
            {{ __('relova::ui.trigger_cooldown_short', ['minutes' => $rule->cooldown_minutes]) }}
        </p>

        @if ($rule->last_triggered_at)
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">
                {{ __('relova::ui.trigger_last_fired', ['time' => $rule->last_triggered_at->diffForHumans()]) }}
            </p>
        @endif
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-1 flex-shrink-0">
        <button wire:click="openEdit('{{ $rule->uid }}')" type="button"
            class="p-1.5 rounded-lg text-gray-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-900/30 dark:hover:text-sky-400 transition-colors"
            title="{{ __('relova::ui.edit') }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
        </button>
        <button wire:click="toggle('{{ $rule->uid }}')" type="button"
            class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-200 transition-colors"
            title="{{ $rule->active ? __('relova::ui.disable') : __('relova::ui.enable') }}">
            @if ($rule->active)
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/></svg>
            @else
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
            @endif
        </button>
        <button wire:click="delete('{{ $rule->uid }}')" wire:confirm="{{ __('relova::ui.trigger_delete_confirm') }}" type="button"
            class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 dark:hover:text-red-400 transition-colors"
            title="{{ __('relova::ui.delete') }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
        </button>
    </div>
</div>
