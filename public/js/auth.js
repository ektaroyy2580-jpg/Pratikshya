/* Pratikshya auth helpers: password toggle, strength meter, match hint, submit loading state. Vanilla JS. */
(function () {
    'use strict';
    if (window.__wwAuthInit) return;
    window.__wwAuthInit = true;

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function () {
        // ---- Show / hide password ----
        document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
            var input = document.getElementById(btn.getAttribute('data-pw-toggle'));
            if (!input) return;
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                var icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-eye', !show);
                    icon.classList.toggle('bi-eye-slash', show);
                }
            });
        });

        // ---- Strength meter ----
        var LABELS = ['—', 'Weak', 'Fair', 'Good', 'Strong'];
        document.querySelectorAll('input[data-pw-strength]').forEach(function (input) {
            var box = document.querySelector('[data-pw-meter-for="' + input.id + '"]');
            if (!box) return;
            var meter = box.querySelector('.pw-meter');
            var label = box.querySelector('[data-pw-label]');
            function rule(name, ok) {
                var li = box.querySelector('[data-rule="' + name + '"]');
                if (!li) return;
                li.classList.toggle('ok', ok);
                var i = li.querySelector('i');
                if (i) {
                    i.classList.toggle('bi-circle', !ok);
                    i.classList.toggle('bi-check-circle-fill', ok);
                }
            }
            function update() {
                var v = input.value || '';
                var r = {
                    length: v.length >= 8,
                    letter: /[A-Za-z]/.test(v),
                    number: /\d/.test(v),
                    mix: (/[a-z]/.test(v) && /[A-Z]/.test(v)) || /[^A-Za-z0-9]/.test(v)
                };
                Object.keys(r).forEach(function (k) { rule(k, r[k]); });
                var score = v.length ? Object.keys(r).filter(function (k) { return r[k]; }).length : 0;
                if (v.length && score === 0) score = 1;
                meter.setAttribute('data-score', String(score));
                if (label) {
                    label.textContent = LABELS[score];
                    label.setAttribute('data-score', String(score));
                }
            }
            input.addEventListener('input', update);
            update();
        });

        // ---- Confirmation match ----
        document.querySelectorAll('input[data-pw-match]').forEach(function (confirm) {
            var original = document.getElementById(confirm.getAttribute('data-pw-match'));
            var hint = document.getElementById(confirm.id + '-match');
            if (!original || !hint) return;
            function update() {
                if (!confirm.value) {
                    hint.textContent = '';
                    hint.className = 'match-hint';
                    confirm.setCustomValidity('');
                    return;
                }
                var ok = confirm.value === original.value;
                hint.className = 'match-hint ' + (ok ? 'ok' : 'bad');
                hint.innerHTML = ok
                    ? '<i class="bi bi-check-circle-fill" aria-hidden="true"></i> Passwords match'
                    : '<i class="bi bi-x-circle-fill" aria-hidden="true"></i> Passwords don’t match yet';
                confirm.setCustomValidity(ok ? '' : 'Passwords do not match.');
            }
            confirm.addEventListener('input', update);
            original.addEventListener('input', update);
            update();
        });

        // ---- Submit loading state ----
        document.querySelectorAll('form[data-loading-form]').forEach(function (form) {
            var btn = form.querySelector('button[type="submit"]');
            if (!btn) return;
            var original = btn.innerHTML;
            var busyText = btn.getAttribute('data-loading-text') || 'Please wait…';
            function reset() {
                btn.disabled = false;
                btn.removeAttribute('aria-busy');
                btn.innerHTML = original;
            }
            form.addEventListener('submit', function (e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    form.reportValidity();
                    reset();
                    return;
                }
                if (btn.disabled) { e.preventDefault(); return; }
                btn.style.minWidth = btn.offsetWidth + 'px'; // no layout jump while the label changes
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><span role="status">' + busyText + '</span>';
            });
            // Re-enable if the user navigates back (bfcache)
            window.addEventListener('pageshow', reset);
        });
    });
})();
