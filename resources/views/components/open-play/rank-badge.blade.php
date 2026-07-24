@props(['rank'])

@php
    $classes = match ($rank) {
        'Advanced' => 'bg-accent-100 text-accent-800 dark:bg-accent-950 dark:text-accent-300',
        'Intermediate' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300',
        'Beginner' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        default => 'bg-ink-200 text-ink-600 dark:bg-ink-800 dark:text-ink-400',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold '.$classes]) }}>
    {{ $rank }}
</span>
