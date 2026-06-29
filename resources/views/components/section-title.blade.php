@props(['action' => null, 'href' => '#'])
<div class="section-title">
    <h2>{{ $slot }}</h2>
    @if($action)<a href="{{ $href }}">{{ $action }}<x-icon name="chevron-right" /></a>@endif
</div>
