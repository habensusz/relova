{{-- Field Group Header: section separator with title --}}
@props([
    'title' => '',
])

<div {{ $attributes->class(['flex items-center gap-2 pt-3 pb-1 border-t border-gray-100 dark:border-gray-700/50 first:border-0 first:pt-0']) }}>
    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ $title }}</span>
    <div class="flex-1 h-px bg-gradient-to-r from-gray-200 to-transparent dark:from-gray-700"></div>
</div>
