{{-- Style and script of public forms, printed once per page. Themes can override the style: every rule uses :where(). --}}
<style>
    :where(.verso-form) { display: grid; gap: 1rem; font-family: var(--font-ui, system-ui, sans-serif); }
    :where(.verso-form__title) { margin: 0; }
    :where(.verso-form__intro) { margin: 0; color: var(--muted, #52525b); }
    :where(.verso-form__grid) { display: grid; gap: 1.25rem 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); }
    :where(.verso-form__field) { display: grid; gap: 0.375rem; align-content: start; grid-column: 1 / -1; min-inline-size: 0; margin: 0; padding: 0; border: 0; }
    @media (min-width: 40rem) { :where(.verso-form__field--half) { grid-column: auto; } }
    :where(.verso-form__field legend) { padding: 0; margin-block-end: 0.375rem; font-weight: 600; }
    :where(.verso-form__field > label) { font-weight: 600; }
    :where(.verso-form__field input:not([type='checkbox'], [type='radio']), .verso-form__field textarea, .verso-form__field select) {
        inline-size: 100%; min-block-size: 2.75rem; padding: 0.5rem 0.75rem; font: inherit; font-size: max(1rem, 1em);
        color: var(--text, #18181b); background: var(--bg, #fff); border: 1px solid var(--muted, #71717a); border-radius: var(--radius, 6px);
    }
    :where(.verso-form__field textarea) { resize: vertical; }
    :where(.verso-form input[type='checkbox'], .verso-form input[type='radio']) { inline-size: 1.25rem; block-size: 1.25rem; margin: 0.125rem 0 0; accent-color: var(--accent, #9a3412); flex: none; }
    :where(.verso-form__choice) { display: flex; gap: 0.625rem; align-items: flex-start; }
    :where(.verso-form__choice label) { padding-block: 0.25rem; margin-block: -0.25rem; }
    :where(.verso-form :focus-visible) { outline: 3px solid var(--focus, #1d4ed8); outline-offset: 2px; }
    :where(.verso-form__field :user-invalid, .verso-form__field [aria-invalid='true']) { border-color: var(--error, var(--accent, #b91c1c)); }
    :where(.verso-form__help) { margin: 0; color: var(--muted, #52525b); font-size: 0.875rem; }
    :where(.verso-form__error) { margin: 0; color: var(--error, var(--accent, #b91c1c)); font-size: 0.875rem; font-weight: 600; }
    :where(.verso-form__error:empty) { display: none; }
    :where(.verso-form__required) { color: var(--error, var(--accent, #b91c1c)); }
    :where(.verso-form__sr, .verso-form__trap) { position: absolute; inline-size: 1px; block-size: 1px; margin: -1px; padding: 0; overflow: hidden; clip-path: inset(50%); white-space: nowrap; border: 0; }
    :where(.verso-form__summary, .verso-form__success) { margin: 0; padding: 0.75rem 1rem; border-radius: var(--radius, 6px); background: var(--surface, #f4f4f5); }
    :where(.verso-form__summary) { border-inline-start: 4px solid var(--error, var(--accent, #b91c1c)); }
    :where(.verso-form__summary p, .verso-form__summary ul) { margin: 0; }
    :where(.verso-form__submit) { margin: 0; }
    :where(.verso-form__submit button) {
        min-block-size: 2.75rem; padding: 0.5rem 1.25rem; font: inherit; font-weight: 600; cursor: pointer;
        color: var(--bg, #fff); background: var(--text, #18181b); border: 0; border-radius: var(--radius, 6px);
    }
    :where(.verso-form__submit button:hover) { background: var(--accent, #3f3f46); }
    :where(.verso-form__choice label) { cursor: pointer; }
    :where(.verso-form__submit button[aria-disabled='true']) { opacity: 0.7; cursor: progress; }
</style>
<script>
    (function () {
        if (!('fetch' in window) || !document.querySelectorAll) { return; }

        var CONTROLS = 'input:not([type="hidden"]), select, textarea';

        function errorBox(control) {
            var field = control.closest('.verso-form__field');
            return field ? field.querySelector('.verso-form__error') : null;
        }

        function groupOf(control) {
            return control.closest('fieldset[data-required-group]');
        }

        function fileTooBig(control) {
            var max = Number(control.getAttribute('data-max-kb'));
            return control.type === 'file' && max > 0 && control.files && control.files[0] && control.files[0].size > max * 1024;
        }

        function message(control) {
            var group = groupOf(control);
            if (group && control.type === 'checkbox' && !group.querySelector('input:checked')) {
                return control.form ? control.form.getAttribute('data-choose-one') : '';
            }
            if (fileTooBig(control)) {
                return control.form ? (control.form.getAttribute('data-too-large') || '').replace(':size', control.getAttribute('data-max-kb')) : '';
            }
            return control.checkValidity() ? '' : control.validationMessage;
        }

        function check(control) {
            var text = message(control);
            var box = errorBox(control);
            var target = groupOf(control) || control;
            if (box) { box.textContent = text; }
            if (text) { target.setAttribute('aria-invalid', 'true'); } else { target.removeAttribute('aria-invalid'); }
            return text === '';
        }

        function setup(form) {
            var tokenRequest = null;
            var button = form.querySelector('button[type="submit"]');
            form.noValidate = true;

            // Cached pages hold an old CSRF token and time trap token, so fresh ones are fetched when the visitor starts.
            function freshToken() {
                if (!tokenRequest) {
                    tokenRequest = fetch(form.getAttribute('data-token-url'), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
                        .then(function (response) { return response.ok ? response.json() : null; })
                        .then(function (data) {
                            if (!data) { return; }
                            var token = form.querySelector('input[name="_token"]');
                            var timer = form.querySelector('input[name="' + form.getAttribute('data-timer-field') + '"]');
                            if (token && data.token) { token.value = data.token; }
                            if (timer && data.timer) { timer.value = data.timer; }
                        })
                        .catch(function () {});
                }
                return tokenRequest;
            }

            form.addEventListener('focusin', freshToken);

            form.addEventListener('input', function (event) {
                var control = event.target;
                control.setAttribute('data-touched', '1');
                if (fileTooBig(control)) { control.setCustomValidity('too-big'); } else if (control.type === 'file') { control.setCustomValidity(''); }
                var target = groupOf(control) || control;
                if (target.getAttribute('aria-invalid') === 'true') { check(control); }
            });

            form.addEventListener('focusout', function (event) {
                var control = event.target;
                if (control.matches && control.matches(CONTROLS) && control.getAttribute('data-touched')) { check(control); }
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (form.getAttribute('data-sending')) { return; }

                var first = null;
                Array.prototype.forEach.call(form.querySelectorAll(CONTROLS), function (control) {
                    if (control.closest('.verso-form__trap')) { return; }
                    if (!check(control) && !first) { first = control; }
                });

                if (first) {
                    first.focus();
                    return;
                }

                form.setAttribute('data-sending', '1');
                if (button) { button.setAttribute('aria-disabled', 'true'); }
                freshToken().then(function () { form.submit(); });
            });
        }

        function start() {
            Array.prototype.forEach.call(document.querySelectorAll('form.verso-form__form'), setup);

        }

        // Browsers skip autofocus when the address has a #fragment, so the first error or the thank you note
        // gets focus here, after the page has loaded and jumped to the fragment.
        function focusFirst() {
            var target = document.querySelector('.verso-form [autofocus]');
            if (target && document.activeElement !== target) { setTimeout(function () { target.focus(); }, 0); }
        }

        if (document.readyState === 'complete') { focusFirst(); } else { window.addEventListener('load', focusFirst); }

        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
    })();
</script>
