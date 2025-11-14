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
        var mixer = mixitup(grid, {
            selectors: { target: '.listing-card' },
            controls: { scope: 'local', live: true },
            animation: { enable: true, effects: 'fade scale(0.98)', duration: 220 },
            callbacks: {
                onMixStart: function (state) {
                    console.log('[MixItUp] #' + sectionId + ' mixStart', { totalShow: state.totalShow, activeFilter: state.activeFilter });
                },
                onMixEnd: function (state) {
                    console.log('[MixItUp] #' + sectionId + ' mixEnd', { totalShow: state.totalShow, activeFilter: state.activeFilter });
                }
            }
        });
        console.log('[MixItUp] initialized for section #' + sectionId, mixer);

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
            console.log('[MixItUp] #' + sectionId + ' applyFilter -> selector', selector);
            try {
                mixer.filter(selector);
            } catch (err) {
                console.error('[MixItUp] #' + sectionId + ' filter error', err);
            }
        }

        var tId;
        function onSearch() {
            state.query = normalize(searchInput ? searchInput.value : '');
            clearTimeout(tId);
            console.log('[MixItUp] #' + sectionId + ' onSearch', state.query);
            tId = setTimeout(applyFilter, 160);
        }
        function onType() { state.type = normalize(typeSelect ? typeSelect.value : ''); console.log('[MixItUp] #' + sectionId + ' onType', state.type); applyFilter(); }
        function onCat() { state.cat = normalize(catSelect ? catSelect.value : ''); console.log('[MixItUp] #' + sectionId + ' onCat', state.cat); applyFilter(); }

        if (searchInput) searchInput.addEventListener('input', onSearch);
        if (typeSelect) typeSelect.addEventListener('change', onType);
        if (catSelect) catSelect.addEventListener('change', onCat);

        // Expose for debugging
        root.__mixer = mixer;
        try {
            var items = root.querySelectorAll('.listing-card');
            var first = items[0];
            console.log('[MixItUp] #' + sectionId + ' snapshot', { total: items.length, sampleTitle: first ? readTitle(first) : null, sampleClasses: first ? first.className : null });
        } catch (e) { }
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
