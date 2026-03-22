<div class="space-y-3">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-sky-100 to-indigo-200 dark:from-sky-900/50 dark:to-indigo-800/50 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-sky-600 dark:text-sky-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                </svg>
            </div>
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                {{ __('relova::relova.widget_config.title') }}
            </h3>
        </div>
        <div class="flex items-center gap-2">
            @if($hasCustomConfig)
            <button
                wire:click="resetToDefaults"
                wire:confirm="{{ __('relova::relova.widget_config.reset_confirm') }}"
                type="button"
                class="px-2.5 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-200"
            >
                {{ __('relova::relova.widget_config.reset') }}
            </button>
            @endif
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                type="button"
                class="px-3 py-1.5 text-xs font-medium text-white bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 rounded-lg shadow-md shadow-sky-500/25 transition-all duration-200 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="save">{{ __('relova::relova.widget_config.save') }}</span>
                <span wire:loading wire:target="save">{{ __('relova::relova.widget_config.saving') }}</span>
            </button>
        </div>
    </div>

    {{-- Field List --}}
    <div class="space-y-1">
        @foreach($items as $index => $item)
        <div wire:key="wci-{{ $item['key'] }}" class="flex items-center gap-2 p-2 rounded-lg border {{ $item['is_visible'] ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700' : 'bg-gray-100 dark:bg-gray-900/50 border-gray-200/50 dark:border-gray-700/50 opacity-60' }} transition-all duration-200">
            {{-- Reorder Buttons --}}
            <div class="flex flex-col gap-0.5">
                <button
                    wire:click="moveUp({{ $index }})"
                    type="button"
                    class="p-0.5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                    @if($index === 0) disabled @endif
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                    </svg>
                </button>
                <button
                    wire:click="moveDown({{ $index }})"
                    type="button"
                    class="p-0.5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                    @if($index === count($items) - 1) disabled @endif
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            </div>

            {{-- Field Info --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        wire:model.blur="items.{{ $index }}.label"
                        class="text-sm font-medium text-gray-900 dark:text-white bg-transparent border-0 border-b border-transparent hover:border-gray-300 dark:hover:border-gray-600 focus:border-sky-500 focus:ring-0 p-0 pb-0.5 w-full transition-colors"
                    />
                </div>
                <div class="text-xs text-gray-400 dark:text-gray-500 font-mono">{{ $item['key'] }}</div>
            </div>

            {{-- Group Header Input --}}
            <input
                type="text"
                wire:model.blur="items.{{ $index }}.group"
                placeholder="{{ __('relova::relova.widget_config.group_placeholder') }}"
                class="text-xs text-gray-500 dark:text-gray-400 bg-transparent border border-gray-200 dark:border-gray-700 rounded-md px-2 py-1 w-28 focus:border-sky-500 focus:ring-1 focus:ring-sky-500/20 transition-colors"
            />

            {{-- Visibility Toggle --}}
            <button
                wire:click="toggleVisibility({{ $index }})"
                type="button"
                class="p-1.5 rounded-lg transition-colors {{ $item['is_visible'] ? 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30' : 'text-gray-400 dark:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800' }}"
                title="{{ $item['is_visible'] ? __('relova::relova.widget_config.hide_field') : __('relova::relova.widget_config.show_field') }}"
            >
                @if($item['is_visible'])
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                @else
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
                @endif
            </button>
        </div>
        @endforeach
    </div>

    @if(empty($items))
    <div class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">
        {{ __('relova::relova.widget_config.no_fields') }}
    </div>
    @endif
</div>
