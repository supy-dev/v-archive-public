@props(['oshis', 'routeName'])

@php
    $currentOshiId = request('oshi_id');
    $baseParams = request()->except(['oshi_id', 'page']);
@endphp

<div class="library-oshi-filter">
    <span class="library-filter-label">推し</span>
    <div class="oshi-filter-list" aria-label="推しで絞り込む">
        <a
            href="{{ route($routeName, $baseParams) }}"
            class="oshi-filter-chip oshi-filter-all {{ $currentOshiId ? '' : 'active' }}"
            @if(!$currentOshiId) aria-current="page" @endif
        >
            すべて
        </a>
        @foreach($oshis as $oshi)
            @php
                $colorClass = $oshi->color_id?->cssClass() ?? 'oshi-color-default';
                $params = array_merge($baseParams, ['oshi_id' => $oshi->id]);
                $isActive = (string) $currentOshiId === (string) $oshi->id;
            @endphp
            <a
                href="{{ route($routeName, $params) }}"
                class="oshi-filter-chip {{ $colorClass }} {{ $isActive ? 'active' : '' }}"
                @if($isActive) aria-current="page" @endif
            >
                <span class="oshi-color-dot" aria-hidden="true"></span>
                {{ $oshi->name }}
            </a>
        @endforeach
    </div>
</div>
