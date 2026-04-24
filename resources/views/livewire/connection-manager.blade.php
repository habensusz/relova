<div>
    <div class="px-4 sm:px-6 lg:px-8 pt-4 pb-12 max-w-7xl mx-auto">

        {{-- ── Breadcrumb ───────────────────────────────────────────── --}}
        @include('relova::partials._breadcrumb', [
            'items' => [
                ['label' => __('relova::ui.breadcrumb_connections')],
            ],
        ])

        {{-- ── Page header ──────────────────────────────────────────── --}}
        <div class="mt-3">
            @include('relova::partials._page-header', [
                'icon'     => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244',
                'title'    => __('relova::ui.connections'),
                'subtitle' => __('relova::ui.connections_subtitle'),
                'metaText' => trans_choice('relova::ui.connections_count', $connections->count(), ['count' => $connections->count()]),
                'actions'  => $showForm ? null : 'relova::partials._connection-manager-actions',
            ])
        </div>

        {{-- ── Sub-navigation tabs ──────────────────────────────────── --}}
        @include('relova::partials._sub-nav', ['active' => 'connections'])

        <article style="min-height: 100px;">

            {{-- Inline create/edit form — appears as a single card above the list --}}
            @if ($showForm)
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-5">
                    <div class="h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500"></div>
                    <div>
                            <form wire:submit="save" class="px-6 py-6 space-y-5">

                                {{-- Form title --}}
                                <div class="flex items-center justify-between">
                                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">
                                        {{ $editing ? __('relova::ui.edit_connection') : __('relova::ui.new_connection') }}
                                    </h2>
                                    <button type="button" wire:click="closeForm"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl leading-none">&times;</button>
                                </div>

                                <p class="text-xs text-gray-500 dark:text-gray-400 -mt-2">{{ __('relova::ui.connection_form_subtitle') }}</p>

                                {{-- Name --}}
                                <div>
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.name') }}</label>
                                    <input wire:model="name" type="text"
                                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                    @error('name') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>

                                {{-- Description --}}
                                <div>
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.description') }}</label>
                                    <textarea wire:model="description" rows="2"
                                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400"></textarea>
                                </div>

                                {{-- Driver + Cache TTL --}}
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.driver') }}</label>
                                        <select wire:model="driver"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                            @foreach ($availableDrivers as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.cache_ttl_seconds') }}</label>
                                        <input wire:model="cacheTtl" type="number" min="0"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                    </div>
                                </div>

                                {{-- Host + Port --}}
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="col-span-2">
                                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.host') }}</label>
                                        <input wire:model="host" type="text"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.port') }}</label>
                                        <input wire:model="port" type="number" min="1" max="65535"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                    </div>
                                </div>

                                {{-- Database --}}
                                <div>
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.database') }}</label>
                                    <input wire:model="database" type="text"
                                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                </div>

                                {{-- Schema — only for drivers that support named schemas --}}
                                @if(in_array($driver, ['pgsql', 'oracle', 'sap_hana', 'sqlsrv']))
                                <div>
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.schema') }}</label>
                                    <input wire:model="schema" type="text"
                                        placeholder="{{ $driver === 'pgsql' ? 'public' : '' }}"
                                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('relova::ui.schema_hint') }}</p>
                                    @error('schema') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                                @endif

                                {{-- Username + Password --}}
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.username') }}</label>
                                        <input wire:model="username" type="text" autocomplete="off"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                        @if ($editing)
                                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">{{ __('relova::ui.leave_blank_to_keep') }}</p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.password') }}</label>
                                        <input wire:model="password" type="password" autocomplete="new-password"
                                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                    </div>
                                </div>

                                {{-- Driver options JSON --}}
                                <div>
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.driver_options_json') }}</label>
                                    <textarea wire:model="optionsJson" rows="3" spellcheck="false"
                                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-xs font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400"></textarea>
                                    @error('optionsJson') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>

                                {{-- SSH tunnel toggle --}}
                                <div class="border-t border-gray-100 dark:border-gray-700 pt-5">
                                    <label class="flex items-center gap-3 cursor-pointer select-none">
                                        <input type="checkbox" wire:model.live="sshEnabled"
                                            class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500 dark:bg-gray-700" />
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('relova::ui.use_ssh_tunnel') }}</span>
                                    </label>
                                </div>

                                {{-- SSH fields — visible when sshEnabled is true --}}
                                @if ($sshEnabled)
                                    <div class="rounded-xl border border-sky-100 dark:border-sky-900/40 bg-sky-50 dark:bg-sky-900/20 p-4 space-y-4">

                                        {{-- SSH Host + Port --}}
                                        <div class="grid grid-cols-3 gap-4">
                                            <div class="col-span-2">
                                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.ssh_host') }}</label>
                                                <input wire:model="sshHost" type="text"
                                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.ssh_port') }}</label>
                                                <input wire:model="sshPort" type="number" min="1" max="65535"
                                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                            </div>
                                        </div>

                                        {{-- SSH User --}}
                                        <div>
                                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.ssh_user') }}</label>
                                            <input wire:model="sshUser" type="text" autocomplete="off"
                                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                        </div>

                                        {{-- SSH Private Key --}}
                                        <div>
                                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.ssh_private_key') }}</label>
                                            <textarea wire:model="sshPrivateKey" rows="5" spellcheck="false"
                                                placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"
                                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-xs font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400"></textarea>
                                            @if ($editing)
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">{{ __('relova::ui.leave_blank_to_keep') }}</p>
                                            @endif
                                        </div>

                                        {{-- Passphrase --}}
                                        <div>
                                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.ssh_passphrase') }}</label>
                                            <input wire:model="sshPassphrase" type="password" autocomplete="new-password"
                                                class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                        </div>

                                    </div>
                                @endif

                                {{-- Actions --}}
                                <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100 dark:border-gray-700">
                                    <button type="button" wire:click="closeForm"
                                        class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-all duration-200">
                                        {{ __('relova::ui.cancel') }}
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                                        <span wire:loading.remove wire:target="save">{{ __('relova::ui.save') }}</span>
                                        <span wire:loading wire:target="save">&#8230;</span>
                                    </button>
                                </div>

                            </form>
                    </div>
                </div>
            @endif

            {{-- Test results --}}
            @if ($testResult)
                <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800/50 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    {{ __('relova::ui.test_passed') }}
                </div>
            @endif
            @if ($testError)
                <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800/50 text-red-800 dark:text-red-300 text-sm flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                    <span class="break-all">{{ $testError }}</span>
                </div>
            @endif

            {{-- Search bar --}}
            <div class="mb-4 relative">
                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="{{ __('relova::ui.search_placeholder') }}"
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-xl text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-colors" />
            </div>

            {{-- Connection list (iTenance index-list-row pattern) --}}
            @if ($connections->isEmpty())
                <div class="rounded-2xl bg-white dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center">
                    <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-sky-700 dark:text-sky-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('relova::ui.no_connections_yet') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">{{ __('relova::ui.connections_subtitle') }}</p>
                    @unless ($showForm)
                        <button wire:click="openCreate" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            {{ __('relova::ui.create_first_connection') }}
                        </button>
                    @endunless
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($connections as $connection)
                        @include('relova::partials._connection-row', [
                            'c' => $connection,
                            'showActions' => true,
                        ])
                    @endforeach
                </div>
            @endif

        </article>
    </div>
</div>
