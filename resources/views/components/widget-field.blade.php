{{-- Widget Field: renders a field label/value pair --}}
@props([
    'label' => '',
    'icon' => null,
])

<div {{ $attributes->class(['space-y-1']) }}>
    <div class="flex items-center gap-1.5">
        @if($icon)
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500">
            {!! $icon !!}
        </svg>
        @endif
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $label }}</span>
    </div>
    <div class="text-sm font-semibold text-gray-900 dark:text-white">
        {{ $slot }}
    </div>
</div>
