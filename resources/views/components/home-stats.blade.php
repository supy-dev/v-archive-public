@props(['homeStats' => []])
<div class="stats">
    <a href="{{ route('archive.index') }}" class="stat-card stat-card-link">
        <x-icon name="archive" />
        <span>
            <small>未整理</small>
            <b>{{ $homeStats['unorganized'] ?? 0 }}<em>本</em></b>
        </span>
    </a>
    <a href="{{ route('watchlist.index') }}" class="stat-card stat-card-link">
        <x-icon name="list" />
        <span>
            <small>見るリスト</small>
            <b>{{ $homeStats['want_to_watch'] ?? 0 }}<em>本</em></b>
        </span>
    </a>
    <a href="{{ route('watchlist.index', ['status' => 'watching']) }}" class="stat-card stat-card-link">
        <x-icon name="play" />
        <span>
            <small>視聴中</small>
            <b>{{ $homeStats['watching'] ?? 0 }}<em>本</em></b>
        </span>
    </a>
    <a href="{{ route('watchlist.index', ['status' => 'watched']) }}" class="stat-card stat-card-link">
        <x-icon name="check" />
        <span>
            <small>視聴済み</small>
            <b>{{ $homeStats['watched'] ?? 0 }}<em>本</em></b>
        </span>
    </a>
</div>
