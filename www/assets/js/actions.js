/* Delegated handlers replacing inline event attributes, which CSP forbids. */
(function () {
    'use strict';

    // A submit button inside a confirmed form must not trigger two dialogs:
    // the click is ignored here and the form's submit event asks once.
    document.addEventListener('submit', function (event) {
        var element = event.target.closest('[data-confirm]');
        if (element && element.tagName === 'FORM' && !window.confirm(element.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    }, true);

    document.addEventListener('click', function (event) {
        var element = event.target.closest('[data-confirm]');
        if (element && element.tagName !== 'FORM' && !window.confirm(element.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    }, true);

    document.addEventListener('change', function (event) {
        var element = event.target.closest('[data-auto-submit]');
        if (element && element.form) {
            element.form.submit();
        }
        runAction(event);
    });

    document.addEventListener('click', function (event) {
        var element = event.target.closest('[data-navigate]');
        if (element) {
            var navigateUrl = getSafeNavigateUrl(element.getAttribute('data-navigate'));
            if (navigateUrl) {
                window.location.href = navigateUrl;
            }
            return;
        }
        element = event.target.closest('[data-dispatch]');
        if (element) {
            window.dispatchEvent(new Event(element.getAttribute('data-dispatch')));
            return;
        }
        element = event.target.closest('[data-toggle-target]');
        if (element) {
            var target = document.getElementById(element.getAttribute('data-toggle-target'));
            if (target) {
                var mode = element.getAttribute('data-toggle-mode') || 'toggle';
                if (mode === 'add') {
                    target.classList.add('hidden');
                } else {
                    target.classList.toggle('hidden');
                }
            }
            return;
        }
        runAction(event);
    });

    function getSafeNavigateUrl(rawUrl) {
        if (!rawUrl) {
            return null;
        }
        try {
            var parsed = new URL(rawUrl, window.location.origin);
            if ((parsed.protocol === 'http:' || parsed.protocol === 'https:') &&
                parsed.origin === window.location.origin) {
                return parsed.href;
            }
        } catch (e) {
            return null;
        }
        return null;
    }

    function runAction(event) {
        var element = event.target.closest('[data-action]');
        if (!element) {
            return;
        }
        var action = element.getAttribute('data-action');
        if (action === 'historyBack') {
            window.history.back();
            event.preventDefault();
            return;
        }
        var handler = window[action];
        if (typeof handler === 'function') {
            handler(event);
            event.preventDefault();
        }
    }
})();
