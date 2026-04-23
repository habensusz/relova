{{--
    Universal "synced from remote" badge.

    Usage on any show page where the model has a relovaRef() relationship:
        <x-relova::shadow-badge :reference="$machine->relovaRef" />

    Renders nothing if $reference is null.
--}}
@props(['reference' => null])

@if ($reference)
    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-700/40 text-[11px] text-indigo-700 dark:text-indigo-300">
        {{-- Link icon --}}
        <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
        </svg>

        <span class="font-medium">{{ optional($reference->connection)->name ?? 'Remote' }}</span>

        <span class="text-indigo-300 dark:text-indigo-600">·</span>

        <span class="font-mono">{{ $reference->remote_table }}</span>

        @if ($reference->snapshot_taken_at)
            <span class="text-indigo-300 dark:text-indigo-600">·</span>
            <span class="text-indigo-400 dark:text-indigo-500">{{ $reference->snapshot_taken_at->diffForHumans() }}</span>
        @endif
    </div>
@endif
