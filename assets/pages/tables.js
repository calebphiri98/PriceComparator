document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('main table').forEach(function (table) {
    if (table.closest('.agri-page-table, .table-wrap')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'agri-page-table';
    wrapper.setAttribute('role', 'region');
    wrapper.setAttribute('aria-label', 'Data table, scroll horizontally on smaller screens');
    wrapper.setAttribute('tabindex', '0');
    table.parentNode.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });
});
