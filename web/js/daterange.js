/* Single-input filing date range (flatpickr). The visible input has no name; the picked days go to the hidden from/to inputs. */
(function () {
    'use strict';
    document.querySelectorAll('[data-ei-daterange]').forEach(function (input) {
        var wrap = input.parentNode;
        var from = wrap.querySelector('input[name="from"]');
        var to = wrap.querySelector('input[name="to"]');
        var fmt = function (d) { return flatpickr.formatDate(d, 'Y-m-d'); };
        flatpickr(input, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'M j, Y',
            altInputClass: input.className,
            maxDate: 'today',
            defaultDate: [from.value, to.value].filter(Boolean),
            locale: { rangeSeparator: ' – ' },
            onChange: function (days) {
                from.value = days[0] ? fmt(days[0]) : '';
                to.value = days[1] ? fmt(days[1]) : (days[0] ? fmt(days[0]) : '');
            },
            onReady: function (d, s, fp) { fp.altInput.placeholder = input.placeholder; }
        });
    });
})();
