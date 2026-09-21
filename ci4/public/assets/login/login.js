/*
 * The little the sign-in pages do in the browser. Served from kso itself: these are the pages
 * a password and a second-factor code are typed into, so nothing on them is fetched from a
 * third party, and there is no inline script - a Content-Security-Policy of script-src 'self'
 * fits them as they are.
 *
 * Each part looks for its own elements and does nothing on a page without them.
 */
(function () {
    'use strict';

    // Show or hide the password on the sign-in form.
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        var field = document.getElementById(button.getAttribute('data-toggle-password'));
        if (!field) {
            return;
        }
        button.addEventListener('click', function () {
            var show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            button.classList.toggle('active', show);
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    // Send the second-factor form as soon as six digits are in.
    var code = document.querySelector('input[name=code]');
    if (code && code.form) {
        code.addEventListener('input', function () {
            if (code.value.length === 6) {
                code.form.requestSubmit ? code.form.requestSubmit() : code.form.submit();
            }
        });
    }

    // Tick off the rules for a new password as it is typed. The server checks the same four
    // rules; this is only so nobody has to submit to find out.
    var password = document.querySelector('input[name=password_confirm]')
        ? document.querySelector('input[name=password]')
        : null;
    if (password) {
        var confirm = document.querySelector('input[name=password_confirm]');
        var mark = function (id, ok) {
            var rule = document.getElementById(id);
            if (rule) {
                rule.classList.toggle('valid', ok);
                rule.classList.toggle('invalid', !ok);
            }
            return ok;
        };
        var validate = function () {
            var value = password.value;
            var results = [
                mark('same', value === confirm.value),
                mark('length', value.length >= 8),
                mark('letter', /[A-Za-z]/.test(value)),
                mark('capital', /[A-Z]/.test(value)),
                mark('number', /\d/.test(value)),
            ];
            return results.every(Boolean);
        };
        password.addEventListener('input', validate);
        confirm.addEventListener('input', validate);
        password.form.addEventListener('submit', function (e) {
            if (!validate()) {
                e.preventDefault();
            }
        });
    }
})();
