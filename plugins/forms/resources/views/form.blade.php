{{-- A public form. It works without JavaScript; the script only adds a fresh token for cached pages and checks fields early. --}}
@php
    $headingId = $form->anchor().'-title';
    $firstError = null;
    foreach ($fields as $candidate) {
        if ($errors->has('fields.'.$candidate->name) || $errors->has('fields.'.$candidate->name.'.*')) {
            $firstError = $candidate->name;
            break;
        }
    }
    $formErrors = array_merge($errors->get('form'), $errors->get('captcha'));
    $summary = \App\Verso\I18n\Translate::icu('forms::{count, plural, one {Please check the form: one answer needs your attention.} other {Please check the form: # answers need your attention.}}', ['count' => $errors->count()]);
@endphp
@if ($withAssets)
    @include('forms::assets')
@endif
<div class="verso-form" id="{{ $form->anchor() }}">
    @if ($heading !== '')
        <h2 class="verso-form__title" id="{{ $headingId }}">{{ $heading }}</h2>
    @endif
    @if ($intro !== '')
        <p class="verso-form__intro">{{ $intro }}</p>
    @endif

    @if ($sent)
        <p class="verso-form__success" role="status" tabindex="-1" autofocus>{{ $form->visitorText('success_message') }}</p>
    @else
        @if ($errors->isNotEmpty())
            <div class="verso-form__summary" role="alert">
                <p>{{ $summary }}</p>
                @if ($formErrors !== [])
                    <ul>
                        @foreach ($formErrors as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <form class="verso-form__form" method="post" action="{{ route('forms.submit', $form) }}" @if ($form->hasFileFields()) enctype="multipart/form-data" @endif data-token-url="{{ $tokenUrl }}" data-timer-field="{{ $timerField }}" data-choose-one="{{ __('forms::Please choose at least one option.') }}" data-too-large="{{ __('forms::This file is too large. The limit is :size KB.') }}" @if ($heading !== '') aria-labelledby="{{ $headingId }}" @else aria-label="{{ $form->title }}" @endif>
            @csrf
            <input type="hidden" name="{{ $timerField }}" value="{{ $timerToken }}">
            <input type="hidden" name="_page" value="{{ $pageUrl }}">

            <div class="verso-form__trap" aria-hidden="true">
                <label for="{{ $form->anchor() }}-{{ $honeypotField }}">{{ __('forms::Leave this field empty') }}</label>
                <input type="text" id="{{ $form->anchor() }}-{{ $honeypotField }}" name="{{ $honeypotField }}" value="" tabindex="-1" autocomplete="off">
            </div>

            <div class="verso-form__grid">
                @foreach ($fields as $field)
                    @php
                        $id = $form->anchor().'-'.$field->name;
                        $inputName = 'fields['.$field->name.']';
                        $error = $errors->first('fields.'.$field->name) ?: $errors->first('fields.'.$field->name.'.*');
                        $value = $old[$field->name] ?? null;
                        $describedBy = trim(($field->help !== '' ? $id.'-help ' : '').$id.'-error');
                        $focus = $firstError === $field->name;
                        $type = $field->type->value;
                    @endphp

                    @if ($type === 'hidden')
                        @continue
                    @endif

                    @if ($field->type->isGroup())
                        <fieldset class="verso-form__field verso-form__field--{{ $field->width }}" aria-describedby="{{ $describedBy }}" @if ($field->required) data-required-group @endif @if ($error) aria-invalid="true" @endif>
                            <legend>{{ $field->label }}@if ($field->required)<span class="verso-form__required" aria-hidden="true"> *</span><span class="verso-form__sr"> {{ __('forms::(required)') }}</span>@endif</legend>
                            @foreach ($field->options as $index => $option)
                                @php
                                    $checked = is_array($value) ? in_array($option, $value, true) : $value === $option;
                                @endphp
                                <div class="verso-form__choice">
                                    <input
                                        type="{{ $type === 'radio' ? 'radio' : 'checkbox' }}"
                                        id="{{ $id }}-{{ $index }}"
                                        name="{{ $type === 'radio' ? $inputName : $inputName.'[]' }}"
                                        value="{{ $option }}"
                                        @checked($checked)
                                        @if ($type === 'radio' && $field->required) required @endif
                                        @if ($focus && $index === 0) autofocus @endif
                                    >
                                    <label for="{{ $id }}-{{ $index }}">{{ $option }}</label>
                                </div>
                            @endforeach
                            @if ($field->help !== '')
                                <p class="verso-form__help" id="{{ $id }}-help">{{ $field->help }}</p>
                            @endif
                            <p class="verso-form__error" id="{{ $id }}-error" aria-live="polite">{{ $error }}</p>
                        </fieldset>
                    @elseif ($type === 'consent')
                        <div class="verso-form__field verso-form__field--full verso-form__consent">
                            <div class="verso-form__choice">
                                <input type="checkbox" id="{{ $id }}" name="{{ $inputName }}" value="1" @checked((bool) $value) @if ($field->required) required @endif aria-describedby="{{ $describedBy }}" @if ($error) aria-invalid="true" @endif @if ($focus) autofocus @endif>
                                <label for="{{ $id }}">{{ $field->label }}@if ($field->required)<span class="verso-form__required" aria-hidden="true"> *</span>@endif</label>
                            </div>
                            @if ($field->help !== '')
                                <p class="verso-form__help" id="{{ $id }}-help">{{ $field->help }}</p>
                            @endif
                            <p class="verso-form__error" id="{{ $id }}-error" aria-live="polite">{{ $error }}</p>
                        </div>
                    @else
                        <div class="verso-form__field verso-form__field--{{ $type === 'textarea' ? 'full' : $field->width }}">
                            <label for="{{ $id }}">{{ $field->label }}@if ($field->required)<span class="verso-form__required" aria-hidden="true"> *</span><span class="verso-form__sr"> {{ __('forms::(required)') }}</span>@endif</label>
                            @if ($type === 'textarea')
                                <textarea id="{{ $id }}" name="{{ $inputName }}" rows="6" @if ($field->placeholder !== '') placeholder="{{ $field->placeholder }}" @endif @if ($field->required) required @endif @if ($field->minLength) minlength="{{ $field->minLength }}" @endif maxlength="{{ $field->textMax() }}" aria-describedby="{{ $describedBy }}" @if ($error) aria-invalid="true" @endif @if ($focus) autofocus @endif>{{ is_string($value) ? $value : '' }}</textarea>
                            @elseif ($type === 'select')
                                <select id="{{ $id }}" name="{{ $inputName }}" @if ($field->required) required @endif aria-describedby="{{ $describedBy }}" @if ($error) aria-invalid="true" @endif @if ($focus) autofocus @endif>
                                    <option value="">{{ $field->placeholder !== '' ? $field->placeholder : __('forms::Choose an option') }}</option>
                                    @foreach ($field->options as $option)
                                        <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif ($type === 'file')
                                @php
                                    $uploadTypes = strtoupper(implode(', ', $field->uploadTypes()));
                                    $uploadSize = $field->uploadKb() >= 1024
                                        ? \Illuminate\Support\Number::format($field->uploadKb() / 1024, maxPrecision: 1, locale: app()->getLocale()).' MB'
                                        : \Illuminate\Support\Number::format($field->uploadKb(), locale: app()->getLocale()).' KB';
                                @endphp
                                <input type="file" id="{{ $id }}" name="{{ $inputName }}" accept="{{ $field->acceptAttribute() }}" data-max-kb="{{ $field->uploadKb() }}" @if ($field->required) required @endif aria-describedby="{{ $id }}-limits {{ $describedBy }}" @if ($error) aria-invalid="true" @endif @if ($focus) autofocus @endif>
                                <p class="verso-form__help" id="{{ $id }}-limits">{{ __('forms:::types, up to :size.', ['types' => $uploadTypes, 'size' => $uploadSize]) }}</p>
                            @else
                                <input
                                    type="{{ match ($type) { 'email' => 'email', 'phone' => 'tel', 'date' => 'date', default => 'text' } }}"
                                    id="{{ $id }}"
                                    name="{{ $inputName }}"
                                    value="{{ is_string($value) ? $value : '' }}"
                                    @if ($field->placeholder !== '') placeholder="{{ $field->placeholder }}" @endif
                                    @if ($field->autocomplete()) autocomplete="{{ $field->autocomplete() }}" @endif
                                    @if (in_array($type, ['email', 'phone'], true)) autocorrect="off" autocapitalize="none" spellcheck="false" @endif
                                    @if ($field->required) required @endif
                                    @if ($type === 'text' && $field->minLength) minlength="{{ $field->minLength }}" @endif
                                    @if ($type === 'text') maxlength="{{ $field->textMax() }}" @elseif ($type === 'email') maxlength="190" @elseif ($type === 'phone') maxlength="40" @endif
                                    aria-describedby="{{ $describedBy }}"
                                    @if ($error) aria-invalid="true" @endif
                                    @if ($focus) autofocus @endif
                                >
                            @endif
                            @if ($field->help !== '')
                                <p class="verso-form__help" id="{{ $id }}-help">{{ $field->help }}</p>
                            @endif
                            <p class="verso-form__error" id="{{ $id }}-error" aria-live="polite">{{ $error }}</p>
                        </div>
                    @endif
                @endforeach
            </div>

            @if ($captcha)
                <div class="{{ $captcha['widget_class'] }} verso-form__captcha" data-sitekey="{{ $captcha['site_key'] }}"></div>
                <script src="{{ $captcha['script'] }}" async defer></script>
            @endif

            <p class="verso-form__submit">
                <button type="submit">{{ $form->visitorText('submit_label') }}</button>
            </p>
        </form>
    @endif
</div>
