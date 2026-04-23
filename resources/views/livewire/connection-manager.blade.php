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
                            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('relova::ui.connections') }}</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ __('relova::ui.connections_subtitle') }}</p>
                        </div>
                        @if (!$showForm)
                            <button wire:click="openCreate" type="button"
                                class="px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                                {{ __('relova::ui.new_connection') }}
                            </button>
                        @endif
                    </div>

                    {{-- Inline form — expands below header when open --}}
                    @if ($showForm)
                        <div class="border-t border-gray-100 dark:border-gray-700">
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
                    @endif
                </div>

                {{-- Test results --}}
                @if ($testResult)
                    <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 text-sm">
                        {{ __('relova::ui.test_passed') }}
                    </div>
                @endif
                @if ($testError)
                    <div class="mb-4 px-4 py-3 rounded-xl bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 text-sm">
                        {{ $testError }}
                    </div>
                @endif

                {{-- Search --}}
                <div class="mb-4">
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="{{ __('relova::ui.search_placeholder') }}"
                        class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                </div>

                {{-- Connection list --}}
                <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
                    @if ($connections->isEmpty())
                        <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('relova::ui.no_connections_yet') }}
                        </div>
                    @else
                        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($connections as $connection)
                                <li wire:key="conn-{{ $connection->uid }}" class="px-6 py-4 flex items-start gap-4">
                                    <span @class([
                                        'w-2 h-2 rounded-full shrink-0 mt-2',
                                        'bg-emerald-500' => $connection->status === 'active',
                                        'bg-amber-500'   => $connection->status === 'error',
                                        'bg-red-500'     => $connection->status === 'unreachable',
                                    ])></span>
                                    <div class="flex-1 min-w-0 overflow-hidden">
                                        <div class="font-semibold text-zinc-900 dark:text-white truncate">{{ $connection->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            <span class="uppercase">{{ $connection->driver }}</span>
                                            @if ($connection->host)
                                                &middot; {{ $connection->host }}@if ($connection->port):{{ $connection->port }}@endif
                                            @endif
                                            @if ($connection->database) &middot; {{ $connection->database }} @endif
                                        </div>
                                        @if ($connection->last_error)
                                            <div class="text-xs text-red-600 dark:text-red-400 mt-1 line-clamp-2" title="{{ $connection->last_error }}">{{ $connection->last_error }}</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button wire:click="test('{{ $connection->uid }}')" type="button"
                                            class="px-3 py-1.5 text-xs font-semibold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-all duration-200">
                                            <span wire:loading.remove wire:target="test('{{ $connection->uid }}')">{{ __('relova::ui.test') }}</span>
                                            <span wire:loading wire:target="test('{{ $connection->uid }}')">&#8230;</span>
                                        </button>
                                        <a href="{{ route('relova.connections.schema', $connection->uid) }}"
                                            class="px-3 py-1.5 text-xs font-semibold bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/60 transition-all duration-200">
                                            {{ __('relova::ui.schema') }}
                                        </a>
                                        <button wire:click="openEdit('{{ $connection->uid }}')" type="button"
                                            class="px-3 py-1.5 text-xs font-semibold bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-all duration-200">
                                            {{ __('relova::ui.edit') }}
                                        </button>
                                        <button wire:click="delete('{{ $connection->uid }}')"
                                            wire:confirm="{{ __('relova::ui.confirm_delete') }}" type="button"
                                            class="px-3 py-1.5 text-xs font-semibold bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 border border-red-100 dark:border-red-800 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition-all duration-200">
                                            {{ __('relova::ui.delete') }}
                                        </button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

            </article>
        </div>
    </div>
</div>
