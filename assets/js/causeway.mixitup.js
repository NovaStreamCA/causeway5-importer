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
        var noResults = root.querySelector('.causeway-listings-no-results');

        function updateNoResults(mixState) {
            if (!noResults) return;
            noResults.hidden = !mixState || mixState.totalShow > 0;
        }

        // Initialize MixItUp
        var limitAttr = parseInt(grid.getAttribute('data-page-limit') || '0', 10);
        var hasPagination = !isNaN(limitAttr) && limitAttr > 0;
        var initialSort = grid.getAttribute('data-initial-sort') || 'event:desc next:asc title:asc';
        var mixConfig = {
            selectors: { target: '.listing-card', pageList: '.causeway-page-list' },
            controls: { scope: hasPagination ? 'global' : 'local', live: true },
            animation: { enable: false, effects: 'fade scale(0.98)', duration: 220 },
            // Use attribute names without the `data-` prefix per MixItUp docs
            load: { sort: initialSort },
            callbacks: {
                onMixStart: function () { },
                onMixEnd: updateNoResults,
                onMixFail: updateNoResults
            }
        };
        if (hasPagination) {
            mixConfig.pagination = { limit: limitAttr };
        }
        var mixer = mixitup(grid, mixConfig);
        // Explicit sort after init to ensure multi-criteria applied (some builds ignore load.sort with live controls)
        try {
            setTimeout(function () {
                mixer.sort(initialSort);
            }, 0);
        } catch (e) { console.warn('[MixItUp] sort invocation failed', e); }

        // Controls: search + selects
        var searchInput = root.querySelector('[data-role="search"]');
        var typeSelect = root.querySelector('[data-role="select-type"]');
        var catSelect = root.querySelector('[data-role="select-cat"]');
        var communitySelect = root.querySelector('[data-role="select-community"]');
        var areaSelect = root.querySelector('[data-role="select-area"]');
        var clearButtons = root.querySelectorAll('[data-role="clear-filters"]');

        function normalize(s) { return (s || '').toString().toLowerCase().trim(); }

        var state = {
            query: normalize(searchInput ? searchInput.value : ''),
            type: normalize(typeSelect ? typeSelect.value : ''),
            cat: normalize(catSelect ? catSelect.value : ''),
            community: normalize(communitySelect ? communitySelect.value : ''),
            area: normalize(areaSelect ? areaSelect.value : '')
        };
        var targets = Array.prototype.slice.call(grid.querySelectorAll('.listing-card'));
        var facets = [
            { key: 'type', prefix: 'type-', select: typeSelect },
            { key: 'cat', prefix: 'cat-', select: catSelect },
            { key: 'community', prefix: 'community-', select: communitySelect },
            { key: 'area', prefix: 'area-', select: areaSelect }
        ];

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

        /**
         * Tests a card against the current search and facet state. For the facet
         * currently being populated, use the candidate option instead of its
         * selected value so each dropdown reflects the other active filters.
         */
        function cardMatches(card, candidateFacet, candidateValue) {
            if (state.query && readTitle(card).indexOf(state.query) === -1) {
                return false;
            }

            for (var i = 0; i < facets.length; i++) {
                var facet = facets[i];
                var value = facet.key === candidateFacet ? candidateValue : state[facet.key];
                value = sanitizeClass(value);

                if (value && !card.classList.contains(facet.prefix + value)) {
                    return false;
                }
            }

            return true;
        }

        function updateAvailableOptions() {
            facets.forEach(function (facet) {
                if (!facet.select) return;

                Array.prototype.forEach.call(facet.select.options, function (option) {
                    var value = normalize(option.value);

                    // The "All" option and the active option must remain usable so
                    // the user can always see and clear their current selection.
                    if (!value || value === state[facet.key]) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }

                    var isAvailable = targets.some(function (card) {
                        return cardMatches(card, facet.key, value);
                    });

                    option.hidden = !isAvailable;
                    option.disabled = !isAvailable;
                });
            });
        }

        function applyFilter() {
            var q = state.query;
            var t = sanitizeClass(state.type);
            var c = sanitizeClass(state.cat);
            var community = sanitizeClass(state.community);
            var area = sanitizeClass(state.area);
            var parts = [];
            if (t) parts.push('.type-' + t);
            if (c) parts.push('.cat-' + c);
            if (community) parts.push('.community-' + community);
            if (area) parts.push('.area-' + area);
            if (q) parts.push('[data-title*="' + cssEscapeAttr(q) + '"]');
            var selector = parts.length ? parts.join('') : 'all';
            updateAvailableOptions();
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
        function onCommunity() { state.community = normalize(communitySelect ? communitySelect.value : ''); applyFilter(); }
        function onArea() { state.area = normalize(areaSelect ? areaSelect.value : ''); applyFilter(); }
        function clearFilters() {
            clearTimeout(tId);
            state.query = '';
            state.type = '';
            state.cat = '';
            state.community = '';
            state.area = '';

            if (searchInput) searchInput.value = '';
            if (typeSelect) typeSelect.value = '';
            if (catSelect) catSelect.value = '';
            if (communitySelect) communitySelect.value = '';
            if (areaSelect) areaSelect.value = '';

            applyFilter();
        }

        if (searchInput) searchInput.addEventListener('input', onSearch);
        if (typeSelect) typeSelect.addEventListener('change', onType);
        if (catSelect) catSelect.addEventListener('change', onCat);
        if (communitySelect) communitySelect.addEventListener('change', onCommunity);
        if (areaSelect) areaSelect.addEventListener('change', onArea);
        Array.prototype.forEach.call(clearButtons, function (button) {
            button.addEventListener('click', clearFilters);
        });

        // Remove options which cannot match anything in this particular grid.
        updateAvailableOptions();
        updateNoResults(mixer.getState());

        // Expose for debugging
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
