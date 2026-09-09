<div class="px-4 sm:px-6 lg:px-8 pt-4 pb-12 max-w-7xl mx-auto">

        {{-- ── Breadcrumb ───────────────────────────────────────────── --}}
        @include('relova::partials._breadcrumb', [
            'items' => [
                ['label' => __('relova::ui.trigger_rules')],
            ],
        ])

        {{-- ── Page header ──────────────────────────────────────────── --}}
        <div class="mt-3 mb-4">
            @include('relova::partials._page-header', [
                'icon'     => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
                'title'    => __('relova::ui.trigger_rules'),
                'subtitle' => __('relova::ui.trigger_rules_subtitle'),
                'actions'  => $showForm ? null : 'relova::partials._trigger-rule-manager-actions',
            ])
        </div>

        {{-- ── Sub-navigation tabs ──────────────────────────────────── --}}
        @include('relova::partials._sub-nav', ['active' => 'triggers'])

        <article style="min-height: 100px;">

            {{-- No mappings yet — a trigger needs a mapping to watch --}}
            @if ($mappings->isEmpty() && ! $showForm)
                <div class="rounded-2xl bg-white dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center">
                    <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-sky-700 dark:text-sky-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('relova::ui.trigger_needs_mapping') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">{{ __('relova::ui.trigger_needs_mapping_hint') }}</p>
                    <a href="{{ tenancy()->initialized ? tenant()->route('relova.mappings.index') : route('relova.mappings.index') }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                        {{ __('relova::ui.new_mapping') }}
                    </a>
                </div>
            @else

            {{-- Inline create/edit form --}}
            @if ($showForm)
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-5">
                    <div class="h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500"></div>

                    <form wire:submit="save" class="px-6 py-6 space-y-5">

                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-bold text-zinc-900 dark:text-white">
                                {{ $editing ? __('relova::ui.trigger_edit_rule') : __('relova::ui.trigger_new_rule') }}
                            </h2>
                            <button type="button" wire:click="closeForm"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-xl leading-none">&times;</button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 -mt-2">{{ __('relova::ui.trigger_form_subtitle') }}</p>

                        {{-- Mapping --}}
                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.trigger_watch_mapping') }}</label>
                            <select wire:model.live="mappingUid"
                                class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                <option value="">{{ __('relova::ui.trigger_choose_mapping') }}</option>
                                @foreach ($mappings as $m)
                                    <option value="{{ $m->uid }}">{{ $m->module_key }} — {{ $m->remote_table }}</option>
                                @endforeach
                            </select>
                            @error('mappingUid') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Name --}}
                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.name') }}</label>
                            <input wire:model="name" type="text" placeholder="{{ __('relova::ui.trigger_name_placeholder') }}"
                                class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                            @error('name') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Condition: field / operator / value --}}
                        <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-4 space-y-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.trigger_condition') }}</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.trigger_when_field') }}</label>
                                    <input wire:model="targetField" type="text" list="relova-trigger-fields" placeholder="e.g. temperature"
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                    <datalist id="relova-trigger-fields">
                                        @foreach ($this->fieldSuggestions as $f)
                                            <option value="{{ $f }}"></option>
                                        @endforeach
                                    </datalist>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">{{ __('relova::ui.trigger_when_field_hint') }}</p>
                                    @error('targetField') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.trigger_operator') }}</label>
                                    <select wire:model.live="operator"
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                        @foreach (array_keys(\Relova\Livewire\TriggerRuleManager::OPERATORS) as $op)
                                            <option value="{{ $op }}">{{ __('relova::ui.trigger_operator_'.$op) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Value — scalar --}}
                            @if ($this->valueMode === 'scalar')
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.trigger_value') }}</label>
                                    <input wire:model="valueScalar" type="text"
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                    @error('valueScalar') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                            {{-- Value — list --}}
                            @elseif ($this->valueMode === 'list')
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.trigger_value_list') }}</label>
                                    <div class="space-y-2">
                                        @foreach ($valueList as $i => $lv)
                                            <div class="flex items-center gap-2" wire:key="vl-{{ $i }}">
                                                <input wire:model="valueList.{{ $i }}" type="text"
                                                    class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm font-mono text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                                <button type="button" wire:click="removeValueRow({{ $i }})"
                                                    class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400">&times;</button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button type="button" wire:click="addValueRow"
                                        class="mt-2 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300">
                                        + {{ __('relova::ui.trigger_add_value') }}
                                    </button>
                                    @error('valueList') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                            @else
                                <p class="text-xs text-gray-500 dark:text-gray-400 italic">{{ __('relova::ui.trigger_no_value_needed') }}</p>
                            @endif
                        </div>

                        {{-- Work order defaults --}}
                        <div class="space-y-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('relova::ui.trigger_wo_defaults') }}</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.trigger_priority') }}</label>
                                    <select wire:model="priority"
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400">
                                        @foreach ($priorities as $p)
                                            <option value="{{ $p }}">{{ __('relova::ui.priority_'.$p) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.trigger_cooldown') }}</label>
                                    <input wire:model="cooldownMinutes" type="number" min="0" max="43200"
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">{{ __('relova::ui.trigger_cooldown_hint') }}</p>
                                    @error('cooldownMinutes') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.trigger_wo_title') }}</label>
                                <input wire:model="woTitleTemplate" type="text" placeholder="{{ __('relova::ui.trigger_wo_title_placeholder') }}"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400" />
                                @error('woTitleTemplate') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.trigger_wo_description') }}</label>
                                <textarea wire:model="woDescriptionTemplate" rows="2" placeholder="{{ __('relova::ui.trigger_wo_description_placeholder') }}"
                                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400"></textarea>
                            </div>

                            <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('relova::ui.trigger_placeholder_hint') }}</p>
                        </div>

                        {{-- Description + active --}}
                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block">{{ __('relova::ui.description') }}</label>
                            <textarea wire:model="description" rows="2"
                                class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl text-sm text-zinc-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400"></textarea>
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="checkbox" wire:model="active"
                                class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500 dark:bg-gray-700" />
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('relova::ui.trigger_active') }}</span>
                        </label>

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

            {{-- Rule list --}}
            @if ($rules->isEmpty())
                @unless ($showForm)
                    <div class="rounded-2xl bg-white dark:bg-gray-800 border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center">
                        <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-sky-700 dark:text-sky-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                        </div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('relova::ui.trigger_none_yet') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">{{ __('relova::ui.trigger_none_yet_hint') }}</p>
                        <button wire:click="openCreate" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            {{ __('relova::ui.trigger_new_rule') }}
                        </button>
                    </div>
                @endunless
            @else
                <div class="space-y-2">
                    @foreach ($rules as $rule)
                        <div wire:key="rule-{{ $rule->uid }}">
                            @include('relova::partials._trigger-rule-row', ['rule' => $rule])
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Recent activity --}}
            @if ($recentLog->isNotEmpty())
                <div class="mt-8">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">{{ __('relova::ui.trigger_recent_activity') }}</h2>
                    <div class="rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($recentLog as $entry)
                            @php
                                $outcomeStyles = [
                                    'matched' => 'text-emerald-600 dark:text-emerald-400',
                                    'created' => 'text-emerald-600 dark:text-emerald-400',
                                    'skipped' => 'text-amber-600 dark:text-amber-400',
                                    'error' => 'text-red-600 dark:text-red-400',
                                ];
                            @endphp
                            <div class="px-4 py-2.5 flex items-center gap-3 text-xs">
                                <span class="font-semibold {{ $outcomeStyles[$entry->outcome] ?? 'text-gray-500' }}">{{ __('relova::ui.trigger_outcome_'.$entry->outcome) }}</span>
                                @if ($entry->rejection_reason)
                                    <span class="text-gray-400 dark:text-gray-500">{{ $entry->rejection_reason }}</span>
                                @endif
                                <span class="ml-auto text-gray-400 dark:text-gray-500">{{ optional($entry->triggered_at)->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @endif
        </article>
</div>
