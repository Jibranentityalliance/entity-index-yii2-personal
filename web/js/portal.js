/* Portal light/dark toggle. The layouts render the theme from the ei_theme cookie; this swaps it in place and saves it. */
(function () {
    'use strict';
    var swaps = [['dark-mode', 'ei-light'], ['sidebar-dark-primary', 'sidebar-light-primary'], ['navbar-dark', 'navbar-light']];

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-ei-theme-toggle]');
        if (!btn) return;
        var light = !document.body.classList.contains('ei-light');
        swaps.forEach(function (pair) {
            document.querySelectorAll('.' + pair[light ? 0 : 1]).forEach(function (el) {
                el.classList.remove(pair[light ? 0 : 1]);
                el.classList.add(pair[light ? 1 : 0]);
            });
        });
        document.cookie = 'ei_theme=' + (light ? 'light' : 'dark') + '; path=/; max-age=31536000; SameSite=Lax';
        var label = light ? 'Switch to dark mode' : 'Switch to light mode';
        document.querySelectorAll('[data-ei-theme-toggle]').forEach(function (b) {
            b.setAttribute('aria-label', label);
            b.title = label;
            b.querySelector('i').className = 'fas ' + (light ? 'fa-moon' : 'fa-sun');
        });
    });
})();
