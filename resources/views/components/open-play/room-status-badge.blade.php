@props(['status'])

@php
    $meta = match ($status) {
        'waiting' => ['label' => 'Waiting for players', 'classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],
        'ready' => ['label' => 'Ready to start', 'classes' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300'],
        'in_progress' => ['label' => 'Live now', 'classes' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'],
        'finished' => ['label' => 'Finished', 'classes' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300'],
        'cancelled' => ['label' => 'Cancelled', 'classes' => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400'],
        default => ['label' => ucfirst(str_replace('_', ' ', $status)), 'classes' => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold '.$meta['classes']]) }}>
    @if ($status === 'in_progress')
        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
    @endif
    {{ $meta['label'] }}
</span>
