@if ($paginator->hasPages())
<nav class="an-pagination" role="navigation" aria-label="ページナビゲーション">

    <p class="an-pagination__count an-pagination__count--desktop">
        @if ($paginator->firstItem())
            {{ $paginator->firstItem() }}〜{{ $paginator->lastItem() }}件 / 全{{ $paginator->total() }}件
        @else
            {{ $paginator->count() }}件
        @endif
    </p>

    <div class="an-pagination__nav an-pagination__nav--desktop">

        {{-- 前へ --}}
        @if ($paginator->onFirstPage())
            <span class="an-pagination__btn an-pagination__btn--disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="an-pagination__btn" aria-label="{{ __('pagination.previous') }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
        @endif

        {{-- ページ番号 --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="an-pagination__dots">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="an-pagination__btn an-pagination__btn--current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="an-pagination__btn" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- 次へ --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="an-pagination__btn" aria-label="{{ __('pagination.next') }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            </a>
        @else
            <span class="an-pagination__btn an-pagination__btn--disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            </span>
        @endif

    </div>

    <div class="an-pagination__mobile">
        @if ($paginator->onFirstPage())
            <span class="an-pagination__mobile-action is-disabled" aria-disabled="true">
                前へ
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="an-pagination__mobile-action">
                前へ
            </a>
        @endif

        <label class="an-pagination__mobile-current">
            <span class="sr-only">移動するページを選択</span>
            <span class="an-pagination__mobile-page">
                <select
                    class="an-pagination__mobile-select"
                    aria-label="移動するページを選択"
                    onchange="window.location.href = this.value"
                >
                    @for ($page = 1; $page <= $paginator->lastPage(); $page++)
                        <option value="{{ $paginator->url($page) }}" @selected($page === $paginator->currentPage())>
                            {{ $page }} / {{ $paginator->lastPage() }}ページ
                        </option>
                    @endfor
                </select>
            </span>
            <small>全{{ $paginator->total() }}件</small>
        </label>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="an-pagination__mobile-action">
                次へ
            </a>
        @else
            <span class="an-pagination__mobile-action is-disabled" aria-disabled="true">
                次へ
            </span>
        @endif
    </div>
</nav>
@endif
