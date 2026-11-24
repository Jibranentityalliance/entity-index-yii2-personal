(function () {
    'use strict';
    document.querySelectorAll('select[data-ei-multiselect]').forEach(function (select) {
        new TomSelect(select, {
            plugins: {
                remove_button: { title: 'Remove' },
                clear_button: { title: 'Clear all' },
                checkbox_options: {}
            },
            placeholder: select.dataset.placeholder || '',
            hidePlaceholder: true,
            closeAfterSelect: false,
            maxOptions: null,
            dropdownParent: 'body',
            render: {
                no_results: function () { return '<div class="no-results">No matches</div>'; }
            }
        });
    });
})();
