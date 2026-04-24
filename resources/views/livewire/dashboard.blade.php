<div>
    <div class="px-4 sm:px-6 lg:px-8 pt-4 pb-12 max-w-7xl mx-auto">

        {{-- ── Breadcrumb ───────────────────────────────────────────── --}}
        @include('relova::partials._breadcrumb', [
            'items' => [
                ['label' => __('relova::ui.breadcrumb_dashboard')],
            ],
        ])

        {{-- ── Page header ──────────────────────────────────────────── --}}
        <div class="mt-3">
            @include('relova::partials._page-header', [
                'icon'     => 'M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z',
                'title'    => __('relova::ui.dashboard_title'),
                'subtitle' => __('relova::ui.dashboard_subtitle'),
                'actions'  => 'relova::partials._dashboard-actions',
            ])
        </div>

        {{-- ── Sub-navigation tabs ──────────────────────────────────── --}}
        @include('relova::partials._sub-nav', ['active' => 'overview'])

        <article style="min-height: 100px;">

            {{-- ── Stat strip ───────────────────────────────────────── --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">

                {{-- Connections --}}
                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/30 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                        </div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.connections') }}</span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ $totalConnections }}</div>
                    <div class="text-[11px] text-emerald-700 dark:text-emerald-400 mt-1">{{ $activeConnections }} {{ __('relova::ui.active') }}</div>
                </div>

                {{-- Errors --}}
                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg {{ $erroringConnections > 0 ? 'bg-red-50 dark:bg-red-900/30' : 'bg-gray-50 dark:bg-gray-700/50' }} flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 {{ $erroringConnections > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                        </div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.errors') }}</span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ $erroringConnections }}</div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">{{ __('relova::ui.unreachable_or_failing') }}</div>
                </div>

                {{-- Mappings --}}
                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                        </div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.module_mappings') }}</span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ $totalMappings }}</div>
                </div>

                {{-- Virtual references --}}
                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm p-4">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        </div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.virtual_references') }}</span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ $totalReferences }}</div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><span class="text-emerald-700 dark:text-emerald-400 tabular-nums">{{ $freshSnapshots }}</span></span>
                        <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span><span class="text-amber-700 dark:text-amber-400 tabular-nums">{{ $staleSnapshots }}</span></span>
                        <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span><span class="text-red-700 dark:text-red-400 tabular-nums">{{ $unavailableSnapshots }}</span></span>
                    </div>
                </div>
            </div>

            {{-- ── Section heading ─────────────────────────────────── --}}
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('relova::ui.your_connections') }}</h2>
                <a href="{{ tenancy()->initialized ? tenant()->route('relova.connections.index') : route('relova.connections.index') }}"
                   wire:navigate
                   class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-colors">
                    {{ __('relova::ui.manage_connections') }} &rarr;
                </a>
            </div>

            {{-- ── Connections list (iTenance index-list-row pattern) ─ --}}
            @if (empty($connections))
                {{-- Empty state --}}
                <div class="rounded-2xl bg-white dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center">
                    <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-sky-700 dark:text-sky-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('relova::ui.no_connections_yet') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">{{ __('relova::ui.connections_subtitle') }}</p>
                    <a href="{{ tenancy()->initialized ? tenant()->route('relova.connections.index') : route('relova.connections.index') }}"
                       wire:navigate
                       class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        {{ __('relova::ui.create_first_connection') }}
                    </a>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($connections as $c)
                        @include('relova::partials._connection-row', ['c' => $c])
                    @endforeach
                </div>
            @endif

        </article>
    </div>
</div>
