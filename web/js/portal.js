/* Portal theme controls: the user menu's Light / Dark / System choice and the sun/moon button on the sign-in pages. */
(function () {
    'use strict';
    document.addEventListener('click', function (ev) {
        var choice = ev.target.closest('[data-ei-theme-set]');
        if (choice) {
            ev.stopPropagation(); // keep the user menu open so the new choice shows
            window.eiTheme.set(choice.getAttribute('data-ei-theme-set'));
            return;
        }
        if (ev.target.closest('[data-ei-theme-toggle]')) {
            window.eiTheme.set(document.body.classList.contains('ei-light') ? 'dark' : 'light');
        }
    }, true);
})();
