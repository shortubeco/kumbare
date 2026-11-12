{{--
    Gerekçe isteyen işlem düğmesi (tıklayınca küçük form açılır).
    Parametreler: action, label, title, submit, field (reason|note), required (bool), btn (sınıf), hint, hidden (dizi), align (left|right)
--}}
@php
    $field ??= 'reason';
    $required ??= true;
    $btn ??= '';
    $hidden ??= [];
@endphp
<details class="act {{ ($align ?? 'right') === 'left' ? 'left' : '' }}">
    <summary class="btn sm {{ $btn }}">{{ $label }}</summary>
    <div class="pop">
        <form method="post" action="{{ $action }}">
            @csrf
            @foreach ($hidden as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <div class="field">
                <label for="{{ $id = 'f'.md5($action.$label) }}">{{ $title ?? 'Gerekçe' }}@unless ($required) <span class="muted">(isteğe bağlı)</span>@endunless</label>
                <textarea id="{{ $id }}" name="{{ $field }}" maxlength="1000" @if ($required) required @endif></textarea>
                @isset($hint)<span class="hint">{{ $hint }}</span>@endisset
            </div>
            <div class="actions">
                <button type="submit" class="btn sm {{ str_contains($btn, 'danger') ? 'danger solid' : 'primary' }}">{{ $submit ?? $label }}</button>
            </div>
        </form>
    </div>
</details>
