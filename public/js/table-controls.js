/**
 * Moves DataTables' generated "Show N entries" and "Search" controls out of the
 * table wrapper and into a controls card rendered above the table, and moves its
 * result count + pagination into a .table-footer-bar so DataTables pages
 * (customers, vendors) get the same card footer as the server-paginated pages.
 *
 * The page markup supplies empty #dt-length / #dt-filter targets; whichever are
 * present get filled.
 *
 * Idempotent by design: several pages destroy and re-initialise the table on
 * retry timers, so the targets are cleared before appending to avoid stacking
 * duplicate control sets.
 */

/**
 * Align DataTables' own wording and pager with the <x-pagination> Blade
 * component, so a DataTables-driven list (customers, vendors) is
 * indistinguishable from a server-paginated one (products, supplies):
 * the same "Showing 1-20 of 45 results" phrasing with bold figures, and
 * chevron icons in place of the stock "Previous" / "Next" wording.
 *
 * infoFiltered is blanked because the Blade component has no equivalent
 * "(filtered from N total entries)" suffix.
 */
(function () {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable) return;

    // DataTables reports internal warnings through a blocking alert() by
    // default, which turns a cosmetic markup mismatch into a modal error popup
    // in front of the user. Route them to the console instead, where they stay
    // visible to developers without interrupting the page.
    jQuery.fn.dataTable.ext.errMode = 'throw';

    jQuery.extend(true, jQuery.fn.dataTable.defaults, {
        language: {
            info: 'Showing <strong>_START_</strong>&ndash;<strong>_END_</strong> of <strong>_TOTAL_</strong> results',
            infoEmpty: 'Showing <strong>0</strong>&ndash;<strong>0</strong> of <strong>0</strong> results',
            infoFiltered: '',
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            }
        }
    });
})();

window.relocateTableControls = function (tableId) {
    var wrapper = document.getElementById(tableId + '_wrapper');
    if (!wrapper) return;

    [['.dataTables_length', 'dt-length'], ['.dataTables_filter', 'dt-filter']]
        .forEach(function (pair) {
            var el = wrapper.querySelector(pair[0]);
            var target = document.getElementById(pair[1]);
            if (el && target) {
                target.innerHTML = '';
                target.appendChild(el);
            }
        });

    relocateTableFooter(wrapper);

    // DataTables rebuilds the pager on every draw, so restyle on each one
    // rather than only at init. Namespaced + rebound so repeat inits on the
    // pages with retry timers don't stack handlers.
    jQuery('#' + tableId)
        .off('draw.dt.footerbar')
        .on('draw.dt.footerbar', function () { styleFooterPager(tableId); });
    styleFooterPager(tableId);

    // Drop the now-empty grid rows DataTables left behind, but never the row
    // holding the table itself.
    wrapper.querySelectorAll(':scope > .row').forEach(function (row) {
        if (!row.querySelector('table') && !row.textContent.trim()) {
            row.remove();
        }
    });
};

/**
 * Matches the pager to <x-pagination>: small size, and hidden entirely while
 * there is only one page — the Blade component renders the count alone in that
 * case, rather than a lone disabled "1".
 */
function styleFooterPager(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;

    var host = table.closest('.card-body') || document.body;
    var pager = host.querySelector('.table-footer-bar .dataTables_paginate');
    if (!pager) return;

    var list = pager.querySelector('ul.pagination');
    if (list) list.classList.add('pagination-sm');

    pager.style.display = jQuery(table).DataTable().page.info().pages > 1 ? '' : 'none';
}

/**
 * Lifts DataTables' info + pagination out of its Bootstrap grid row and out of
 * .table-responsive, into a .table-footer-bar appended to the card body. Being
 * inside .table-responsive is why they used to scroll sideways with the table.
 *
 * DataTables keeps element references in its settings and rewrites these nodes
 * in place on every draw, so relocating the nodes (rather than copying their
 * markup) keeps paging and the count live.
 */
function relocateTableFooter(wrapper) {
    var info = wrapper.querySelector('.dataTables_info');
    var paginate = wrapper.querySelector('.dataTables_paginate');
    if (!info && !paginate) return;

    // Prefer the card body, so the footer picks up the full-bleed card-footer
    // styling; fall back to just outside the scroll container.
    var scroller = wrapper.closest('.table-responsive');
    var host = wrapper.closest('.card-body') || (scroller && scroller.parentNode);
    if (!host) return;

    // Only ever reuse a bar this function created. A page can also carry a
    // Blade-rendered <x-pagination> bar (orders is both server-paginated and
    // DataTables-enhanced); writing into that one would replace the server's
    // true total with DataTables' count of the current page alone.
    var bar = host.querySelector(':scope > .table-footer-bar[data-dt-footer]');
    if (!bar) {
        bar = document.createElement('div');
        bar.className = 'table-footer-bar';
        bar.setAttribute('data-dt-footer', '');
        host.appendChild(bar);
    }

    // Pages that re-initialise on retry timers build a fresh wrapper each time,
    // while the nodes already moved here survive DataTables' teardown. Clear
    // before appending or the count and pager stack up copy after copy.
    if (info) {
        var summary = bar.querySelector(':scope > .table-footer-bar__summary');
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'table-footer-bar__summary';
            bar.appendChild(summary);
        }
        summary.innerHTML = '';
        summary.appendChild(info);
    }

    if (paginate) {
        bar.querySelectorAll(':scope > .dataTables_paginate').forEach(function (old) {
            old.remove();
        });
        bar.appendChild(paginate);
    }
}
