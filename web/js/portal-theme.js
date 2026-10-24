/*
 * Portal theme, loaded in <head>. The layouts render the theme from the ei_theme cookie (light / dark / system) and put
 * the setting on <body data-ei-theme>. For "system" they render dark and call eiTheme.sync() right after the top bar and
 * sidebar, so a device that prefers light switches before the page paints.
 */
window.eiTheme = (function () {
    'use strict';
    var swaps = [['dark-mode', 'ei-light'], ['sidebar-dark-primary', 'sidebar-light-primary'], ['navbar-dark', 'navbar-light']];
    var prefersLight = window.matchMedia ? window.matchMedia('(prefers-color-scheme: light)') : null;

    function apply(light) {
        swaps.forEach(function (pair) {
            document.querySelectorAll('.' + pair[light ? 0 : 1]).forEach(function (el) {
                el.classList.remove(pair[light ? 0 : 1]);
                el.classList.add(pair[light ? 1 : 0]);
            });
        });
        var label = light ? 'Switch to dark mode' : 'Switch to light mode';
        document.querySelectorAll('[data-ei-theme-toggle]').forEach(function (b) {
            b.setAttribute('aria-label', label);
            b.title = label;
            b.querySelector('i').className = 'fas ' + (light ? 'fa-moon' : 'fa-sun');
        });
    }

    function sync() {
        var setting = document.body.getAttribute('data-ei-theme');
        apply(setting === 'light' || (setting === 'system' && !!prefersLight && prefersLight.matches));
    }

    /** Saves and applies a setting: 'light', 'dark' or 'system'. */
    function set(setting) {
        document.cookie = 'ei_theme=' + setting + '; path=/; max-age=31536000; SameSite=Lax';
        document.body.setAttribute('data-ei-theme', setting);
        document.querySelectorAll('[data-ei-theme-set]').forEach(function (b) {
            var on = b.getAttribute('data-ei-theme-set') === setting;
            b.classList.toggle('active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        sync();
    }

    if (prefersLight && prefersLight.addEventListener) {
        prefersLight.addEventListener('change', function () {
            if (document.body.getAttribute('data-ei-theme') === 'system') sync();
        });
    }
    return { apply: apply, sync: sync, set: set };
})();
