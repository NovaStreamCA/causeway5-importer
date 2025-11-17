(function () {
    // Guard init
    if (window.__causewayMixitupInit) return;
    window.__causewayMixitupInit = true;
    var __sectionSeq = 0;

    function initScope(root) {
        var sectionId = ++__sectionSeq;
        var grid = root.querySelector('.causeway-listings-grid');
        if (!grid || typeof mixitup === 'undefined') {
            console.warn('[MixItUp] init aborted for section #' + sectionId + ' (missing grid or mixitup)', { hasGrid: !!grid, hasMixitup: (typeof mixitup !== 'undefined') });
            return;
        }

        // Initialize MixItUp
        var limitAttr = parseInt(grid.getAttribute('data-page-limit') || '0', 10);
        var hasPagination = !isNaN(limitAttr) && limitAttr > 0;
        var mixConfig = {
            selectors: { target: '.listing-card', pageList: '.causeway-page-list' },
            controls: { scope: hasPagination ? 'global' : 'local', live: true },
            animation: { enable: false, effects: 'fade scale(0.98)', duration: 220 },
            // Use attribute names without the `data-` prefix per MixItUp docs
            load: { sort: 'event:desc next:asc title:asc' },
            callbacks: {
                onMixStart: function () { },
                onMixEnd: function () { }
            }
        };
        if (hasPagination) {
            mixConfig.pagination = { limit: limitAttr };
        }
        var mixer = mixitup(grid, mixConfig);
        // Explicit sort after init to ensure multi-criteria applied (some builds ignore load.sort with live controls)
        try {
            setTimeout(function () {
                mixer.sort('event:desc next:asc title:asc');
            }, 0);
        } catch (e) { console.warn('[MixItUp] sort invocation failed', e); }

        // Controls: search + selects
        var searchInput = root.querySelector('[data-role="search"]');
        var typeSelect = root.querySelector('[data-role="select-type"]');
        var catSelect = root.querySelector('[data-role="select-cat"]');

        var state = { query: '', type: '', cat: '' };

        function normalize(s) { return (s || '').toString().toLowerCase().trim(); }

        function readTitle(el) {
            var t = el.getAttribute('data-title');
            if (t) return t; // already lowercased in markup
            var h = el.querySelector && el.querySelector('.title');
            if (h) return normalize(h.textContent || h.innerText || '');
            return normalize(el.textContent || '');
        }

        function cssEscapeAttr(val) {
            return (val || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\]/g, '\\]');
        }
        function sanitizeClass(val) {
            return (val || '').replace(/[^a-z0-9_-]/gi, '');
        }
        function applyFilter() {
            var q = state.query;
            var t = sanitizeClass(state.type);
            var c = sanitizeClass(state.cat);
            var parts = [];
            if (t) parts.push('.type-' + t);
            if (c) parts.push('.cat-' + c);
            if (q) parts.push('[data-title*="' + cssEscapeAttr(q) + '"]');
            var selector = parts.length ? parts.join('') : 'all';
            try {
                mixer.filter(selector);
            } catch (err) {
                console.warn('[MixItUp] filter error', err);
            }
        }

        var tId;
        function onSearch() { state.query = normalize(searchInput ? searchInput.value : ''); clearTimeout(tId); tId = setTimeout(applyFilter, 160); }
        function onType() { state.type = normalize(typeSelect ? typeSelect.value : ''); applyFilter(); }
        function onCat() { state.cat = normalize(catSelect ? catSelect.value : ''); applyFilter(); }

        if (searchInput) searchInput.addEventListener('input', onSearch);
        if (typeSelect) typeSelect.addEventListener('change', onType);
        if (catSelect) catSelect.addEventListener('change', onCat);

        // Expose for debugging
        root.__mixer = mixer;
        // Minimal exposure for potential manual debugging (no console spam)
        root.__mixer = mixer;
    }

    function initAll() {
        var sections = document.querySelectorAll('.causeway-listings-section');
        sections.forEach(initScope);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
