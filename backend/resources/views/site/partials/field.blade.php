@php $type ??= 'text'; @endphp
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @unless ($noOld ?? false) value="{{ old($name) }}" @endunless
           @isset($autocomplete) autocomplete="{{ $autocomplete }}" @endisset
           @isset($inputmode) inputmode="{{ $inputmode }}" @endisset
           @error($name) aria-invalid="true" aria-describedby="{{ $name }}-hata" @enderror>
    @error($name)<span class="field-error" id="{{ $name }}-hata">{{ $message }}</span>@enderror
</div>
