<div class="px-4 sm:px-6 lg:px-8 pt-4 pb-12 max-w-7xl mx-auto">

        {{-- ── Breadcrumb ───────────────────────────────────────────── --}}
        @include('relova::partials._breadcrumb', [
            'items' => [
                ['label' => __('relova::ui.breadcrumb_connections'), 'url' => tenancy()->initialized ? tenant()->route('relova.connections.index') : route('relova.connections.index')],
                ['label' => __('relova::ui.breadcrumb_schema')],
            ],
        ])

        {{-- ── Page header ──────────────────────────────────────────── --}}
        <div class="mt-3 mb-4">
            @include('relova::partials._page-header', [
                'icon'     => 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125',
                'title'    => __('relova::ui.schema_browser'),
                'subtitle' => __('relova::ui.schema_browser_subtitle'),
                'actions'  => 'relova::partials._schema-browser-actions',
            ])
        </div>

        {{-- ── Sub-navigation tabs ──────────────────────────────────── --}}
        @include('relova::partials._sub-nav', ['active' => 'schema'])

        <article style="min-height: 100px;">

                <div class="flex gap-4 items-start">

                    {{-- Connections --}}
                    <div class="w-52 shrink-0 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            {{ __('relova::ui.connections') }}
                        </div>
                        @if ($connections->isEmpty())
                            <div class="p-4 text-xs text-gray-500 dark:text-gray-400 italic">{{ __('relova::ui.no_active_connections') }}</div>
                        @else
                            <ul class="flex flex-col">
                                @foreach ($connections as $c)
                                    <li wire:key="conn-{{ $c->uid }}" class="block border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                        <button wire:click="selectConnection('{{ $c->uid }}')" type="button"
                                            class="block w-full text-left px-4 py-3 transition-colors duration-150 {{ $connectionUid === $c->uid ? 'bg-sky-600 dark:bg-sky-700' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                                            <div class="text-sm font-semibold {{ $connectionUid === $c->uid ? 'text-white' : 'text-zinc-900 dark:text-white' }} truncate">{{ $c->name }}</div>
                                            <div class="text-[11px] uppercase mt-0.5 {{ $connectionUid === $c->uid ? 'text-sky-200' : 'text-gray-400 dark:text-gray-500' }}">{{ $c->driver }}</div>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Tables --}}
                    <div class="w-64 shrink-0 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                {{ __('relova::ui.tables') }}
                                @if (! empty($tables))
                                    <span class="ml-1 text-sky-600 dark:text-sky-400 normal-case">({{ count($tables) }})</span>
                                @endif
                            </div>
                            @if ($connectionUid)
                                <button wire:click="flushCache" type="button" title="{{ __('relova::ui.flush_cache') }}"
                                    class="text-gray-400 dark:text-gray-500 hover:text-sky-600 dark:hover:text-sky-400 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                </button>
                            @endif
                        </div>

                        @if (! empty($tables))
                            <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700">
                                <input wire:model.live.debounce.200ms="tableSearch" type="text"
                                    placeholder="{{ __('relova::ui.filter_tables') }}"
                                    class="block w-full text-xs px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-zinc-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500" />
                            </div>
                        @endif

                        <div wire:loading wire:target="selectConnection,flushCache" class="py-8 text-center">
                            <div class="inline-block w-5 h-5 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-2">{{ __('relova::ui.loading') }}&#8230;</div>
                        </div>

                        <div wire:loading.remove wire:target="selectConnection,flushCache" style="max-height:560px;overflow-y:auto;">
                            @if (! $connectionUid)
                                <div class="py-10 px-4 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('relova::ui.pick_a_connection') }}</div>
                            @elseif ($tablesError)
                                <div class="p-4">
                                    <div class="flex items-start gap-2 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50">
                                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                        </svg>
                                        <span class="text-xs text-red-700 dark:text-red-400 break-all">{{ $tablesError }}</span>
                                    </div>
                                </div>
                            @elseif (empty($filteredTables))
                                <div class="py-8 text-center text-xs text-gray-400 dark:text-gray-500">
                                    {{ $tableSearch ? __('relova::ui.no_tables_match') : __('relova::ui.no_tables') }}
                                </div>
                            @else
                                <ul class="flex flex-col">
                                    @foreach ($filteredTables as $table)
                                        <li wire:key="tbl-{{ $table['name'] }}" class="block border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                            <button wire:click="selectTable('{{ $table['name'] }}')" type="button"
                                                class="block w-full text-left px-4 py-2.5 transition-colors duration-150 {{ $selectedTable === $table['name'] ? 'bg-sky-600 dark:bg-sky-700' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-mono text-sm truncate {{ $selectedTable === $table['name'] ? 'text-white font-semibold' : 'text-zinc-700 dark:text-gray-300' }}">{{ $table['name'] }}</span>
                                                    <span class="shrink-0 flex items-center gap-1">
                                                        @if (isset($table['type']) && $table['type'] === 'view')
                                                            <span class="{{ $selectedTable === $table['name'] ? 'bg-white/20 text-white' : 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400' }} text-[10px] px-1.5 py-0.5 rounded font-medium">view</span>
                                                        @endif
                                                        @if (isset($table['row_count']) && $table['row_count'] !== null)
                                                            <span class="text-[10px] {{ $selectedTable === $table['name'] ? 'text-sky-200' : 'text-gray-400 dark:text-gray-500' }}">~{{ number_format($table['row_count']) }}</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>

                    {{-- Detail panel --}}
                    <div class="flex-1 min-w-0 space-y-4">
                        <div wire:loading wire:target="selectTable" class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg p-10 text-center">
                            <div class="inline-block w-6 h-6 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-3">{{ __('relova::ui.loading') }}&#8230;</div>
                        </div>
                        <div wire:loading.remove wire:target="selectTable">
                            @if (! $selectedTable)
                                <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-16 text-center">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0c0 .621.504 1.125 1.125 1.125h15" />
                                    </svg>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('relova::ui.pick_a_table') }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('relova::ui.pick_a_table_hint') }}</p>
                                </div>
                            @else

                                {{-- Columns --}}
                                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
                                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-700/60">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-zinc-800 dark:text-white">{{ __('relova::ui.columns') }}</span>
                                            <code class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ $selectedTable }}</code>
                                        </div>
                                        @if (! empty($columns))
                                            <span class="text-xs bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 px-2 py-0.5 rounded-full font-medium">{{ count($columns) }} {{ __('relova::ui.cols') }}</span>
                                        @endif
                                    </div>
                                    @if ($columnsError)
                                        <div class="p-5">
                                            <div class="flex items-start gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50">
                                                <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                                </svg>
                                                <div>
                                                    <p class="text-sm font-semibold text-red-700 dark:text-red-400">{{ __('relova::ui.failed_to_load_columns') }}</p>
                                                    <p class="text-xs text-red-600 dark:text-red-500 mt-1 break-all">{{ $columnsError }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @elseif (empty($columns))
                                        <div class="px-5 py-8 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('relova::ui.no_columns') }}</div>
                                    @else
                                        <div class="overflow-x-auto" style="max-height:300px;overflow-y:auto;">
                                            <table class="w-full text-sm">
                                                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-700/80 border-b border-gray-200 dark:border-gray-600">
                                                    <tr>
                                                        <th class="text-left px-5 py-2.5 text-[11px] uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400 w-1/3">{{ __('relova::ui.name') }}</th>
                                                        <th class="text-left px-4 py-2.5 text-[11px] uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400">{{ __('relova::ui.type') }}</th>
                                                        <th class="text-left px-4 py-2.5 text-[11px] uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400">{{ __('relova::ui.nullable') }}</th>
                                                        <th class="text-left px-4 py-2.5 text-[11px] uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400">{{ __('relova::ui.default') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($columns as $col)
                                                        <tr class="border-b border-gray-100 dark:border-gray-700/60 last:border-b-0 odd:bg-white even:bg-gray-50/60 dark:odd:bg-gray-800 dark:even:bg-gray-700/30 hover:bg-sky-50 dark:hover:bg-gray-700/60 transition-colors">
                                                            <td class="px-5 py-2.5">
                                                                <div class="flex items-center gap-1.5">
                                                                    @if ($col['primary'] ?? false)
                                                                        <span title="Primary Key" class="shrink-0 inline-flex items-center justify-center w-4 h-4 rounded bg-amber-100 dark:bg-amber-900/40">
                                                                            <svg class="w-2.5 h-2.5 text-amber-500 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8 7a5 5 0 1 1 3.61 4.804l-1.903 1.903A1 1 0 0 1 9 14H8v1a1 1 0 0 1-1 1H6v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2a1 1 0 0 1 .293-.707L7.196 9.39A5.002 5.002 0 0 1 8 7Zm5-1a1 1 0 1 0 0 2 2 2 0 0 0 0-4Z" clip-rule="evenodd"/></svg>
                                                                        </span>
                                                                    @endif
                                                                    <span class="font-mono text-xs text-zinc-800 dark:text-gray-200">{{ $col['name'] ?? '' }}</span>
                                                                </div>
                                                            </td>
                                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                                <code class="text-[11px] px-1.5 py-0.5 rounded bg-sky-100/80 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 whitespace-nowrap">{{ $col['type'] ?? '' }}</code>
                                                            </td>
                                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                                @if ($col['nullable'] ?? false)
                                                                    <span class="text-[11px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 font-medium whitespace-nowrap">NULL</span>
                                                                @else
                                                                    <span class="text-[11px] px-1.5 py-0.5 rounded bg-orange-50 dark:bg-orange-900/30 text-orange-600 dark:text-orange-300 font-medium border border-orange-100 dark:border-orange-800/40 whitespace-nowrap">NOT NULL</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-2.5 font-mono text-xs text-gray-400 dark:text-gray-500 truncate max-w-[160px]">
                                                                {{ $col['default'] !== null ? (string) $col['default'] : '—' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>

                                {{-- Preview --}}
                                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
                                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-700/60">
                                        <span class="text-sm font-semibold text-zinc-800 dark:text-white">{{ __('relova::ui.preview') }}</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('relova::ui.first_n_rows', ['n' => 25]) }}</span>
                                    </div>
                                    @if ($previewError)
                                        <div class="p-5">
                                            <div class="flex items-start gap-3 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50">
                                                <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                                </svg>
                                                <div>
                                                    <p class="text-sm font-semibold text-red-700 dark:text-red-400">{{ __('relova::ui.failed_to_load_preview') }}</p>
                                                    <p class="text-xs text-red-600 dark:text-red-500 mt-1 break-all">{{ $previewError }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @elseif (empty($previewRows))
                                        <div class="px-5 py-8 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('relova::ui.no_rows') }}</div>
                                    @else
                                        <div class="overflow-x-auto" style="max-height:400px;overflow-y:auto;">
                                            <table class="w-full text-xs">
                                                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-700/80 border-b border-gray-200 dark:border-gray-600">
                                                    <tr>
                                                        @foreach (array_keys($previewRows[0]) as $h)
                                                            <th class="text-left px-4 py-2.5 text-[11px] uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $h }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($previewRows as $row)
                                                        <tr class="border-b border-gray-100 dark:border-gray-700/60 last:border-b-0 odd:bg-white even:bg-gray-50/60 dark:odd:bg-gray-800 dark:even:bg-gray-700/30 hover:bg-sky-50 dark:hover:bg-gray-700/60 transition-colors">
                                                            @foreach ($row as $val)
                                                                <td class="px-4 py-2 font-mono text-zinc-700 dark:text-gray-300 whitespace-nowrap max-w-[200px] truncate"
                                                                    title="{{ is_scalar($val) ? (string) $val : json_encode($val) }}">
                                                                    @if ($val === null)
                                                                        <span class="text-gray-300 dark:text-gray-600 italic">null</span>
                                                                    @else
                                                                        {{ is_scalar($val) ? (string) $val : json_encode($val) }}
                                                                    @endif
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>

                            @endif
                        </div>
                    </div>

                </div>
            </article>
</div>