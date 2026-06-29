@props([
    'eyebrow',
    'title',
    'description' => null,
])

<header {{ $attributes->class(['app-page-heading', 'has-actions' => isset($actions)]) }}>
    <div class="app-page-heading-copy">
        <p class="page-eyebrow">{{ $eyebrow }}</p>
        <h1>{{ $title }}</h1>
        @if($description)
            <p>{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="app-page-heading-actions">{{ $actions }}</div>
    @endisset
</header>
