document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('main table').forEach(function (table) {
        if (table.closest('.agri-responsive-table, .table-wrap')) return;
        var wrapper = document.createElement('div');
        wrapper.className = 'agri-responsive-table';
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', 'Scrollable data table');
        wrapper.setAttribute('tabindex', '0');
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
});
