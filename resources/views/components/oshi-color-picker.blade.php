{{-- 推し識別色パレット選択コンポーネント --}}
@props(['colors', 'name' => 'color_id', 'selected' => null])

<div x-data="{ selected: '{{ $selected ?? '' }}' }">
    <div class="oshi-color-options">
        {{-- 未選択オプション --}}
        <label class="oshi-color-option oshi-color-none" title="なし">
            <input
                type="radio"
                name="{{ $name }}"
                value=""
                x-model="selected"
                class="sr-only"
            >
            <span
                class="oshi-color-swatch"
                :class="selected === '' ? 'border-gray-600 ring-2 ring-gray-400 ring-offset-1' : 'border-gray-300'"
            >×</span>
        </label>

        {{-- パレット各色 --}}
        @foreach($colors as $color)
            <label class="oshi-color-option {{ $color->cssClass() }}" title="{{ $color->label() }}">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $color->value }}"
                    x-model="selected"
                    class="sr-only"
                >
                <span class="oshi-color-swatch" :class="selected === '{{ $color->value }}' ? 'selected' : ''"></span>
            </label>
        @endforeach
    </div>
</div>
