<div class="relative mx-auto container pb-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="py-3 text-gray-900 dark:text-gray-100">
            <article class="container mx-auto" style="min-height: 100px;">

                {{-- Header --}}
                <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg overflow-hidden mb-6">
                    <div class="h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500"></div>
                    <div class="p-6 flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-sky-700 dark:text-sky-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('relova::ui.dashboard_title') }}</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ __('relova::ui.dashboard_subtitle') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('relova.connections.index') }}" class="px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                                {{ __('relova::ui.manage_connections') }}
                            </a>
                            <a href="{{ route('relova.mappings.index') }}" class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-all duration-200">
                                {{ __('relova::ui.module_mappings') }}
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Stat strip --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg p-5">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.connections') }}</div>
                        <div class="text-3xl font-bold text-zinc-900 dark:text-white mt-2">{{ $totalConnections }}</div>
                        <div class="text-xs text-emerald-700 dark:text-emerald-400 mt-1">{{ $activeConnections }} {{ __('relova::ui.active') }}</div>
                    </div>
                    <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg p-5">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.errors') }}</div>
                        <div class="text-3xl font-bold text-zinc-900 dark:text-white mt-2">{{ $erroringConnections }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('relova::ui.unreachable_or_failing') }}</div>
                    </div>
                    <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg p-5">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.module_mappings') }}</div>
                        <div class="text-3xl font-bold text-zinc-900 dark:text-white mt-2">{{ $totalMappings }}</div>
                    </div>
                    <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg p-5">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.virtual_references') }}</div>
                        <div class="text-3xl font-bold text-zinc-900 dark:text-white mt-2">{{ $totalReferences }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-x-2">
                            <span class="text-emerald-700 dark:text-emerald-400">{{ $freshSnapshots }} {{ __('relova::ui.fresh') }}</span>
                            <span class="text-amber-700 dark:text-amber-400">{{ $staleSnapshots }} {{ __('relova::ui.stale') }}</span>
                            <span class="text-red-700 dark:text-red-400">{{ $unavailableSnapshots }} {{ __('relova::ui.unavailable') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Connections list --}}
                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('relova::ui.your_connections') }}</h2>
                    </div>
                    @if (empty($connections))
                        <div class="text-center py-12">
                            <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-sky-700 dark:text-sky-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('relova::ui.no_connections_yet') }}</p>
                            <a href="{{ route('relova.connections.index') }}" class="inline-flex px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                                {{ __('relova::ui.create_first_connection') }}
                            </a>
                        </div>
                    @else
                        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($connections as $c)
                                <li class="px-6 py-4 flex items-center gap-4">
                                    <span @class([
                                        'w-2 h-2 rounded-full',
                                        'bg-emerald-500' => $c['status'] === 'active',
                                        'bg-amber-500' => $c['status'] === 'error',
                                        'bg-red-500' => $c['status'] === 'unreachable',
                                    ])></span>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-zinc-900 dark:text-white truncate">{{ $c['name'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            <span class="uppercase">{{ $c['driver'] }}</span>
                                            @if ($c['host']) Â· {{ $c['host'] }} @endif
                                            @if ($c['database']) Â· {{ $c['database'] }} @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        @if ($c['last_checked_at'])
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('relova::ui.checked') }} {{ $c['last_checked_at'] }}</div>
                                        @endif
                                        @if ($c['last_error'])
                                            <div class="text-xs text-red-600 dark:text-red-400 truncate max-w-xs">{{ $c['last_error'] }}</div>
                                        @endif
                                    </div>
                                    <a href="{{ route('relova.connections.schema', $c['uid']) }}" class="text-sm font-semibold text-sky-700 dark:text-sky-400 hover:underline">{{ __('relova::ui.browse') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

            </article>
        </div>
    </div>
</div>
