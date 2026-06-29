@php
    $isEditing = isset($oshi);
    $cancelUrl = $isEditing ? route('oshis.show', $oshi) : route('oshis.index');
@endphp

<form
    method="POST"
    action="{{ $isEditing ? route('oshis.update', $oshi) : route('oshis.store') }}"
    class="oshi-form"
>
    @csrf
    @if($isEditing)
        @method('PUT')
    @endif

    <label class="oshi-form-field" for="name">
        <span>推し名 <em>必須</em></span>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $oshi->name ?? '') }}"
            maxlength="100"
            required
            class="field-control @error('name') has-error @enderror"
            placeholder="例: 夢川あい"
        >
        @error('name')
            <small class="oshi-form-error">{{ $message }}</small>
        @enderror
    </label>

    <label class="oshi-form-field" for="group_name">
        <span>グループ名</span>
        <input
            type="text"
            id="group_name"
            name="group_name"
            value="{{ old('group_name', $oshi->group_name ?? '') }}"
            maxlength="100"
            class="field-control"
            placeholder="例: にじなんとか"
        >
    </label>

    <fieldset class="oshi-form-field oshi-color-field">
        <legend>推しカラー</legend>
        <x-oshi-color-picker
            :colors="$colors"
            :selected="old('color_id', isset($oshi) ? $oshi->color_id?->value : null)"
        />
        <small>カードや名前の識別に使います。アプリ全体の色は変わりません。</small>
        @error('color_id')
            <small class="oshi-form-error">{{ $message }}</small>
        @enderror
    </fieldset>

    <label class="oshi-form-field" for="memo">
        <span>メモ</span>
        <textarea
            id="memo"
            name="memo"
            rows="4"
            class="field-control"
            placeholder="好きなところや覚えておきたいことを自由にメモできます"
        >{{ old('memo', $oshi->memo ?? '') }}</textarea>
    </label>

    <div class="oshi-form-actions">
        <button type="submit" class="button-primary">
            <x-icon name="{{ $isEditing ? 'check' : 'plus' }}" />
            {{ $isEditing ? '変更を保存' : '推しを追加' }}
        </button>
        <a href="{{ $cancelUrl }}">キャンセル</a>
    </div>
</form>
