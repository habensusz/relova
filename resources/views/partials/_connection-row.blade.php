{{--
    Reusable connection list row (iTenance index-list-row pattern).

    Required:
        $c   array{uid: string, name: string, driver: string, host: ?string, port?: int|null,
                   database: ?string, status: string, last_checked_at: ?string, last_error: ?string}
             OR a Relova\Models\RelovaConnection instance (both array-access patterns supported).

    Optional:
        $showActions  bool   When true, renders Test/Edit/Delete inline action buttons
                             (set by ConnectionManager). Defaults to false → only "Schema" link.
--}}
@php
    $get = function ($key) use ($c) {
        if (is_array($c)) {
            return $c[$key] ?? null;
        }
        return $c->{$key} ?? null;
    };

    $uid = $get('uid');
    $name = $get('name');
    $driver = $get('driver');
    $host = $get('host');
    $port = $get('port');
    $database = $get('database');
    $status = $get('status') ?? 'pending';
    $lastChecked = $get('last_checked_at');
    $lastError = $get('last_error');

    if ($lastChecked instanceof \Carbon\CarbonInterface) {
        $lastChecked = $lastChecked->diffForHumans();
    }

    $statusColors = [
        'active'      => ['dot' => 'bg-emerald-500', 'pill' => 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300', 'accent' => 'bg-emerald-500', 'label' => __('relova::ui.status_active')],
        'error'       => ['dot' => 'bg-amber-500',   'pill' => 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',         'accent' => 'bg-amber-500',   'label' => __('relova::ui.status_error')],
        'unreachable' => ['dot' => 'bg-red-500',     'pill' => 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300',                 'accent' => 'bg-red-500',     'label' => __('relova::ui.status_unreachable')],
    ];
    $sc = $statusColors[$status] ?? ['dot' => 'bg-gray-400 dark:bg-gray-500', 'pill' => 'bg-gray-100 dark:bg-gray-700/50 text-gray-600 dark:text-gray-400', 'accent' => 'bg-gray-300 dark:bg-gray-600', 'label' => __('relova::ui.status_pending')];

    $schemaUrl = tenancy()->initialized
        ? tenant()->route('relova.connections.schema', $uid)
        : route('relova.connections.schema', $uid);

    $showActions = $showActions ?? false;
@endphp

<div wire:key="conn-row-{{ $uid }}"
     class="group relative bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl px-4 py-3.5 grid grid-cols-[28px_1fr_auto] items-center gap-3.5 transition-all duration-200 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-md overflow-hidden">

    {{-- Left accent stripe --}}
    <div class="absolute left-0 top-0 bottom-0 w-[3px] rounded-l-2xl {{ $sc['accent'] }}"></div>

    {{-- Status icon square --}}
    <div class="w-7 h-7 rounded-lg flex items-center justify-center {{ $sc['pill'] }}">
        <span class="w-2 h-2 rounded-full bg-current opacity-90"></span>
    </div>

    {{-- Main content --}}
    <div class="flex flex-col gap-1 min-w-0">
        {{-- Top row: status pill + driver pill --}}
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sc['pill'] }}">
                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                {{ $sc['label'] }}
            </span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-mono uppercase font-semibold bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 border border-sky-100 dark:border-sky-800/50">
                {{ $driver }}
            </span>
        </div>

        {{-- Title --}}
        <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $name }}</div>

        {{-- Bottom row: host / db / last checked --}}
        <div class="flex items-center gap-3 flex-wrap text-xs text-gray-500 dark:text-gray-400">
            @if ($host)
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3 h-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z"/></svg>
                    <span class="truncate font-mono text-[11px]">{{ $host }}@if ($port):{{ $port }}@endif</span>
                </span>
            @endif
            @if ($database)
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3 h-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                    <span class="truncate font-mono text-[11px]">{{ $database }}</span>
                </span>
            @endif
            @if ($lastChecked)
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3 h-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/></svg>
                    {{ __('relova::ui.last_checked') }} {{ $lastChecked }}
                </span>
            @endif
        </div>

        {{-- Error line (if any) --}}
        @if ($lastError)
            <p class="text-xs text-red-600 dark:text-red-400 mt-0.5 line-clamp-2" title="{{ $lastError }}">
                {{ $lastError }}
            </p>
        @endif
    </div>

    {{-- Right actions --}}
    <div class="flex items-center gap-1.5 flex-shrink-0">
        @if ($showActions)
            <button wire:click="test('{{ $uid }}')" type="button"
                class="px-2.5 py-1.5 text-[11.5px] font-semibold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-all duration-150">
                <span wire:loading.remove wire:target="test('{{ $uid }}')">{{ __('relova::ui.test') }}</span>
                <span wire:loading wire:target="test('{{ $uid }}')">&#8230;</span>
            </button>
        @endif
        <a href="{{ $schemaUrl }}" wire:navigate
            class="px-2.5 py-1.5 text-[11.5px] font-semibold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-all duration-150">
            {{ __('relova::ui.view_schema') }}
        </a>
        @if ($showActions)
            <button wire:click="openEdit('{{ $uid }}')" type="button"
                class="px-2.5 py-1.5 text-[11.5px] font-semibold bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-all duration-150">
                {{ __('relova::ui.edit') }}
            </button>
            <button wire:click="delete('{{ $uid }}')" wire:confirm="{{ __('relova::ui.confirm_delete') }}" type="button"
                class="px-2.5 py-1.5 text-[11.5px] font-semibold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/40 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition-all duration-150">
                {{ __('relova::ui.delete') }}
            </button>
        @endif
    </div>
</div>
