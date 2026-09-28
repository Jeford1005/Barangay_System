@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'optionalHint' => false,
    'value' => null,
    'placeholder' => null,
    'maxlength' => null,
    'options' => null,
    'placeholderOption' => null,
    'rows' => null,
    'checked' => false,
])

@php
$errorId = $name.'-error';
$hasError = $errors->has($name);
$fieldClass = 'min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 '.($hasError ? 'border-red-500! ' : '');
$spanClass = $type === 'select' ? $fieldClass : $fieldClass;
$numberInputMode = $type === 'number'
    ? (str_contains((string) $attributes->get('step'), '.') ? 'decimal' : 'numeric')
    : null;

// A list such as ['Male', 'Female'] stores the visible label as its
// value. A map such as $puroks = [12 => 'Purok 1'] stores the key
// (the database ID) and displays the label. Keeping this distinction
// prevents visible values such as HH-001 from being submitted to
// integer foreign-key fields.
$optionCollection = collect($options);
$optionValuesAreLabels = array_is_list($optionCollection->all());
@endphp

<div>
    @if ($type !== 'checkbox')
        <label for="{{ $name }}" class="block text-sm font-medium text-slate-700 mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @elseif ($optionalHint)
                <span class="text-slate-500">(optional)</span>
            @endif
        </label>
    @endif

    @if ($type === 'select')
        <select id="{{ $name }}" name="{{ $name }}" @required($required)
            @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
            {{ $attributes->merge(['class' => $spanClass]) }}>
            @if ($placeholderOption !== null)
                <option value="">{{ $placeholderOption }}</option>
            @endif
            @foreach ($optionCollection as $optValue => $optLabel)
                @php $realValue = $optionValuesAreLabels ? $optLabel : $optValue; @endphp
                <option value="{{ $realValue }}" @selected((string) old($name, $value) === (string) $realValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows ?? 3 }}" @required($required)
            @if($maxlength) maxlength="{{ $maxlength }}" @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
            {{ $attributes->merge(['class' => $fieldClass]) }}>{{ old($name, $value) }}</textarea>
    @elseif ($type === 'checkbox')
        <label for="{{ $name }}" class="inline-flex items-center gap-2">
            <input type="hidden" name="{{ $name }}" value="0">
            <input id="{{ $name }}" type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked))
                @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                class="h-4 w-4 rounded border-slate-300 text-sky-700 focus:ring-sky-600">
            <span class="text-sm text-slate-700">{{ $slot ?: $label }}</span>
        </label>
    @else
        <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" @required($required)
            @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
            @if($numberInputMode && ! $attributes->has('inputmode')) inputmode="{{ $numberInputMode }}" @endif
            @if($maxlength) maxlength="{{ $maxlength }}" @endif
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->merge(['class' => $fieldClass]) }}>
    @endif

    @if ($hasError)
        <p id="{{ $errorId }}" class="mt-1.5 text-sm text-red-600">{{ $errors->first($name) }}</p>
    @endif
</div>
