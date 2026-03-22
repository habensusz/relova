<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ __('relova::relova.custom_fields.title') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('relova::relova.custom_fields.subtitle', ['entity' => $entityType]) }}
            </p>
        </div>

        @if (! $showForm)
            <button wire:click="create" type="button"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('relova::relova.custom_fields.add_field') }}
            </button>
        @endif
    </div>

    {{-- Form --}}
    @if ($showForm)
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
            <div class="h-1 bg-gradient-to-r from-sky-500 via-indigo-500 to-purple-500"></div>
            <div class="p-6">
                <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4">
                    {{ $editingId ? __('relova::relova.custom_fields.edit_field') : __('relova::relova.custom_fields.new_field') }}
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Name --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('relova::relova.custom_fields.name') }}
                        </label>
                        <input wire:model="name" type="text" placeholder="e.g. serial_number"
                            @if ($editingId) disabled @endif
                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200 disabled:opacity-60">
                        @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Label --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('relova::relova.custom_fields.label') }}
                        </label>
                        <input wire:model="label" type="text" placeholder="e.g. Serial Number"
                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200">
                        @error('label') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Field Type --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('relova::relova.custom_fields.field_type') }}
                        </label>
                        <select wire:model.live="field_type"
                            @if ($editingId) disabled @endif
                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200 disabled:opacity-60">
                            @foreach (\Relova\Models\CustomFieldDefinition::fieldTypes() as $type)
                                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                        @error('field_type') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Sort Order --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('relova::relova.custom_fields.sort_order') }}
                        </label>
                        <input wire:model="sort_order" type="number" min="0"
                            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:focus:border-sky-400 transition-all duration-200">
                        @error('sort_order') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Required --}}
                    <div class="flex items-center gap-3 pt-6">
                        <input wire:model="is_required" type="checkbox" id="cf-is-required"
                            class="w-5 h-5 rounded-lg border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500 dark:bg-gray-700">
                        <label for="cf-is-required" class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ __('relova::relova.custom_fields.is_required') }}
                        </label>
                    </div>

                    {{-- Active --}}
                    <div class="flex items-center gap-3 pt-6">
                        <input wire:model="is_active" type="checkbox" id="cf-is-active"
                            class="w-5 h-5 rounded-lg border-gray-300 dark:border-gray-600 text-sky-600 focus:ring-sky-500 dark:bg-gray-700">
                        <label for="cf-is-active" class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ __('relova::relova.custom_fields.is_active') }}
                        </label>
                    </div>
                </div>

                {{-- Validation Constraints (conditional per field_type) --}}
                @if (in_array($field_type, ['text']))
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl">
                        <h5 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-3">
                            {{ __('relova::relova.custom_fields.text_constraints') }}
                        </h5>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">{{ __('relova::relova.custom_fields.min_length') }}</label>
                                <input wire:model="min_length" type="number" min="0"
                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all duration-200">
                                @error('min_length') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">{{ __('relova::relova.custom_fields.max_length') }}</label>
                                <input wire:model="max_length" type="number" min="0"
                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all duration-200">
                                @error('max_length') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">{{ __('relova::relova.custom_fields.regex_pattern') }}</label>
                                <input wire:model="regex_pattern" type="text" placeholder="/^[A-Z]{2}-\d{4}$/"
                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all duration-200">
                                @error('regex_pattern') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                @if (in_array($field_type, ['number']))
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl">
                        <h5 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-3">
                            {{ __('relova::relova.custom_fields.number_constraints') }}
                        </h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">{{ __('relova::relova.custom_fields.min_value') }}</label>
                                <input wire:model="min_value" type="number"
                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all duration-200">
                                @error('min_value') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 dark:text-gray-400 mb-1">{{ __('relova::relova.custom_fields.max_value') }}</label>
                                <input wire:model="max_value" type="number"
                                    class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all duration-200">
                                @error('max_value') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="mt-6 flex items-center gap-3">
                    <button wire:click="save" type="button"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 rounded-xl shadow-md shadow-sky-500/25 transition-all duration-200 disabled:opacity-60">
                        <svg wire:loading wire:target="save" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? __('relova::relova.custom_fields.update') : __('relova::relova.custom_fields.create') }}
                        </span>
                        <span wire:loading wire:target="save">{{ __('relova::relova.custom_fields.saving') }}</span>
                    </button>

                    <button wire:click="cancel" type="button"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200">
                        {{ __('relova::relova.custom_fields.cancel') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Definitions List --}}
    @if ($definitions->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-lg overflow-hidden">
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500"></div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($definitions as $definition)
                    <div wire:key="def-{{ $definition->id }}" class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all duration-200">
                        <div class="flex items-center gap-4">
                            {{-- Type Icon --}}
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center
                                @if ($definition->field_type === 'text') bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50
                                @elseif ($definition->field_type === 'number') bg-gradient-to-br from-amber-100 to-orange-200 dark:from-amber-900/50 dark:to-orange-800/50
                                @elseif ($definition->field_type === 'date') bg-gradient-to-br from-emerald-100 to-teal-200 dark:from-emerald-900/50 dark:to-teal-800/50
                                @else bg-gradient-to-br from-purple-100 to-pink-200 dark:from-purple-900/50 dark:to-pink-800/50
                                @endif">
                                @if ($definition->field_type === 'text')
                                    <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
                                @elseif ($definition->field_type === 'number')
                                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5"/></svg>
                                @elseif ($definition->field_type === 'date')
                                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                @else
                                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ $definition->label }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">{{ $definition->name }}</span>
                                    @if ($definition->is_required)
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                            {{ __('relova::relova.custom_fields.required') }}
                                        </span>
                                    @endif
                                    @if (! $definition->is_active)
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                            {{ __('relova::relova.custom_fields.inactive') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ ucfirst($definition->field_type) }}
                                    @if ($definition->sort_order) &middot; {{ __('relova::relova.custom_fields.order') }}: {{ $definition->sort_order }} @endif
                                </p>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-2">
                            <button wire:click="edit({{ $definition->id }})" type="button"
                                class="p-2 text-gray-400 hover:text-sky-600 dark:hover:text-sky-400 rounded-lg hover:bg-sky-50 dark:hover:bg-sky-900/20 transition-all duration-200"
                                title="{{ __('relova::relova.custom_fields.edit_field') }}">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                            </button>
                            <button wire:click="delete({{ $definition->id }})" type="button"
                                wire:confirm="{{ __('relova::relova.custom_fields.delete_confirm') }}"
                                class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-all duration-200"
                                title="{{ __('relova::relova.custom_fields.delete_field') }}">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        {{-- Empty State --}}
        @if (! $showForm)
            <div class="text-center py-12">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    {{ __('relova::relova.custom_fields.no_fields') }}
                </p>
                <button wire:click="create" type="button"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-all duration-200">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('relova::relova.custom_fields.add_first') }}
                </button>
            </div>
        @endif
    @endif
</div>
