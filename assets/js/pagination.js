/**
 * Client-side pagination for data tables with class "data-table-paginated"
 * Renders a bar below each table: "Showing X to Y of Z items" | Items per page | First | Prev | pages | Next | Last
 * Persists current page in URL hash so reload keeps the same page (hash is preserved by browser).
 */
(function() {
    var PER_PAGE_OPTIONS = [5, 10, 25, 50, 100];
    var DEFAULT_PER_PAGE = 10;
    var HASH_PREFIX = 'iaslogs_';

    function paramNameFromTableId(id) {
        if (!id) return null;
        return id.replace(/-/g, '_');
    }

    function parseHash() {
        var q = {};
        var h = typeof window.location.hash === 'string' ? window.location.hash : '';
        if (h.charAt(0) === '#') h = h.slice(1);
        if (!h) return q;
        h.split('&').forEach(function(pair) {
            var i = pair.indexOf('=');
            var k = i >= 0 ? decodeURIComponent(pair.slice(0, i).replace(/\+/g, ' ')) : decodeURIComponent(pair.replace(/\+/g, ' '));
            var v = i >= 0 ? decodeURIComponent(pair.slice(i + 1).replace(/\+/g, ' ')) : '';
            if (k && k.indexOf(HASH_PREFIX) === 0) q[k] = v;
        });
        return q;
    }

    function buildHash(params) {
        return Object.keys(params).filter(function(k) { return params[k] !== '' && params[k] != null; }).map(function(k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(String(params[k]));
        }).join('&');
    }

    function updateUrlPageParam(tableId, page, perPage) {
        var prefix = paramNameFromTableId(tableId);
        if (!prefix) return;
        var pageKey = HASH_PREFIX + 'page_' + prefix;
        var perpageKey = HASH_PREFIX + 'perpage_' + prefix;
        var params = parseHash();
        if (page >= 1) params[pageKey] = String(page); else delete params[pageKey];
        if (perPage && PER_PAGE_OPTIONS.indexOf(perPage) !== -1) params[perpageKey] = String(perPage); else delete params[perpageKey];
        var hash = buildHash(params);
        var url = window.location.pathname + window.location.search + (hash ? '#' + hash : '');
        try { window.history.replaceState(null, '', url); } catch (e) {}
        try { if (page >= 1) sessionStorage.setItem('iaslogs_page_' + prefix, String(page)); } catch (e) {}
    }

    function initTable(table) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        var total = rows.length;
        if (total === 0) return;

        var container = table.closest('.table-responsive') || table.parentElement;
        var tableId = table.id || table.getAttribute('data-pagination-id');
        var prefix = paramNameFromTableId(tableId);
        var hashParams = parseHash();
        var pageKey = HASH_PREFIX + 'page_' + prefix;
        var perpageKey = HASH_PREFIX + 'perpage_' + prefix;
        var perPage = DEFAULT_PER_PAGE;
        var currentPage = 1;

        if (prefix) {
            var savedPage = parseInt(hashParams[pageKey], 10);
            if (savedPage >= 1) currentPage = savedPage;
            else {
                if (typeof window.IASLOGS_PAGINATION !== 'undefined' && window.IASLOGS_PAGINATION['page_' + prefix] >= 1) {
                    currentPage = parseInt(window.IASLOGS_PAGINATION['page_' + prefix], 10);
                } else {
                    try {
                        var stored = sessionStorage.getItem('iaslogs_page_' + prefix);
                        if (stored) { var n = parseInt(stored, 10); if (n >= 1) currentPage = n; }
                    } catch (e) {}
                }
            }
            var savedPerPage = parseInt(hashParams[perpageKey], 10);
            if (PER_PAGE_OPTIONS.indexOf(savedPerPage) !== -1) perPage = savedPerPage;
            else if (typeof window.IASLOGS_PAGINATION !== 'undefined' && window.IASLOGS_PAGINATION['perpage_' + prefix] > 0 && PER_PAGE_OPTIONS.indexOf(window.IASLOGS_PAGINATION['perpage_' + prefix]) !== -1) {
                perPage = window.IASLOGS_PAGINATION['perpage_' + prefix];
            }
        }
        if (!prefix) {
            try {
                var key = 'iaslogs_perpage_' + (tableId || '');
                var saved = localStorage.getItem(key);
                if (saved) { var n = parseInt(saved, 10); if (PER_PAGE_OPTIONS.indexOf(n) !== -1) perPage = n; }
            } catch (e) {}
        }

        var totalPages = Math.max(1, Math.ceil(total / perPage));
        if (currentPage > totalPages) currentPage = totalPages;

        function getStorageKey() {
            return 'iaslogs_perpage_' + (tableId || ('table-' + Math.random().toString(36).slice(2)));
        }

        function savePerPage(val) {
            try {
                if (tableId) localStorage.setItem(getStorageKey(), String(val));
            } catch (e) {}
        }

        function showPage(page) {
            currentPage = Math.max(1, Math.min(page, totalPages));
            var start = (currentPage - 1) * perPage;
            var end = start + perPage;
            rows.forEach(function(row, i) {
                row.style.display = (i >= start && i < end) ? '' : 'none';
            });
            if (prefix) updateUrlPageParam(tableId, currentPage, perPage);
            renderBar();
        }

        function renderBar() {
            var start = (currentPage - 1) * perPage;
            var end = Math.min(start + perPage, total);
            var from = total === 0 ? 0 : start + 1;
            var to = total === 0 ? 0 : end;

            var infoText = 'Showing ' + from + ' to ' + to + ' of ' + total + ' items';

            var perPageOptionsHtml = PER_PAGE_OPTIONS.map(function(n) {
                return '<option value="' + n + '"' + (perPage === n ? ' selected' : '') + '>' + n + '</option>';
            }).join('');

            var pageButtons = [];
            pageButtons.push('<button type="button" class="page-btn first" title="First page" ' + (currentPage <= 1 ? 'disabled' : '') + '>&#171;</button>');
            pageButtons.push('<button type="button" class="page-btn prev" title="Previous page" ' + (currentPage <= 1 ? 'disabled' : '') + '>&#60;</button>');

            var maxVisible = 5;
            var half = Math.floor(maxVisible / 2);
            var pageStart = Math.max(1, currentPage - half);
            var pageEnd = Math.min(totalPages, pageStart + maxVisible - 1);
            if (pageEnd - pageStart + 1 < maxVisible) pageStart = Math.max(1, pageEnd - maxVisible + 1);

            if (pageStart > 1) {
                pageButtons.push('<button type="button" class="page-btn num" data-page="1">1</button>');
                if (pageStart > 2) pageButtons.push('<button type="button" class="page-btn ellipsis" disabled>&#8230;</button>');
            }
            for (var p = pageStart; p <= pageEnd; p++) {
                pageButtons.push('<button type="button" class="page-btn num' + (p === currentPage ? ' active' : '') + '" data-page="' + p + '">' + p + '</button>');
            }
            if (pageEnd < totalPages) {
                if (pageEnd < totalPages - 1) pageButtons.push('<button type="button" class="page-btn ellipsis" disabled>&#8230;</button>');
                pageButtons.push('<button type="button" class="page-btn num" data-page="' + totalPages + '">' + totalPages + '</button>');
            }
            pageButtons.push('<button type="button" class="page-btn next" title="Next page" ' + (currentPage >= totalPages ? 'disabled' : '') + '>&#62;</button>');
            pageButtons.push('<button type="button" class="page-btn last" title="Last page" ' + (currentPage >= totalPages ? 'disabled' : '') + '>&#187;</button>');

            var barHtml =
                '<div class="pagination-bar">' +
                '<div class="pagination-bar-left">' +
                '<div class="pagination-bar-info">' +
                '<span class="info-icon">i</span>' +
                '<span class="info-text">' + infoText + '</span>' +
                '</div>' +
                '<div class="pagination-bar-perpage">' +
                '<label for="perpage-' + (table.id || 't') + '">Items per page:</label>' +
                '<select id="perpage-' + (table.id || ('t' + Math.random().toString(36).slice(2))) + '" class="pagination-perpage-select">' +
                perPageOptionsHtml +
                '</select>' +
                '</div>' +
                '</div>' +
                '<div class="pagination-bar-right">' +
                pageButtons.join('') +
                '</div>' +
                '</div>';

            var existingBar = container.nextElementSibling;
            if (existingBar && existingBar.classList && existingBar.classList.contains('pagination-bar')) {
                existingBar.outerHTML = barHtml;
            } else {
                var wrap = document.createElement('div');
                wrap.innerHTML = barHtml;
                var barEl = wrap.firstElementChild;
                if (container.parentNode) container.parentNode.insertBefore(barEl, container.nextSibling);
            }

            var bar = container.nextElementSibling;
            if (!bar || !bar.classList || !bar.classList.contains('pagination-bar')) return;

            var select = bar.querySelector('.pagination-perpage-select');
            if (select) {
                select.onchange = function() {
                    perPage = parseInt(select.value, 10);
                    savePerPage(perPage);
                    totalPages = Math.max(1, Math.ceil(total / perPage));
                    currentPage = Math.min(currentPage, totalPages);
                    if (prefix) updateUrlPageParam(tableId, currentPage, perPage);
                    showPage(currentPage);
                };
            }

            bar.querySelectorAll('.page-btn.first').forEach(function(btn) {
                btn.onclick = function() { if (currentPage > 1) showPage(1); };
            });
            bar.querySelectorAll('.page-btn.prev').forEach(function(btn) {
                btn.onclick = function() { if (currentPage > 1) showPage(currentPage - 1); };
            });
            bar.querySelectorAll('.page-btn.next').forEach(function(btn) {
                btn.onclick = function() { if (currentPage < totalPages) showPage(currentPage + 1); };
            });
            bar.querySelectorAll('.page-btn.last').forEach(function(btn) {
                btn.onclick = function() { if (currentPage < totalPages) showPage(totalPages); };
            });
            bar.querySelectorAll('.page-btn.num').forEach(function(btn) {
                var p = parseInt(btn.getAttribute('data-page'), 10);
                btn.onclick = function() { if (!btn.classList.contains('active')) showPage(p); };
            });
        }

        showPage(currentPage);
    }

    function init() {
        document.querySelectorAll('table.data-table-paginated').forEach(initTable);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
