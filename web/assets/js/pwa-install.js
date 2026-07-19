(function () {
    var button = document.querySelector('[data-install-app]');
    var hint = document.querySelector('[data-install-hint]');
    var appUrl = button ? button.getAttribute('data-project-url') : '';
    var deferredPrompt = null;
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

    if (!button || standalone) {
        return;
    }

    if ('serviceWorker' in navigator && appUrl) {
        navigator.serviceWorker.register(appUrl + '/sw.js').catch(function () {});
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        button.hidden = false;
    });

    button.hidden = false;

    button.addEventListener('click', function () {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.finally(function () {
                deferredPrompt = null;
            });
            return;
        }

        if (hint) {
            hint.hidden = !hint.hidden;
        }
    });
})();
