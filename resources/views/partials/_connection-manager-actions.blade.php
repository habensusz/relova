{{-- Page-header action button(s) for the Connections index --}}
<button wire:click="openCreate" type="button"
    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white text-xs font-semibold rounded-lg shadow-md shadow-sky-500/25 transition-all duration-200">
    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
    </svg>
    {{ __('relova::ui.new_connection') }}
</button>
