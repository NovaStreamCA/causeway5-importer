(function () {
    'use strict';

    function normalizeUrl(url) {
        try {
            return new URL(url, document.baseURI).href;
        } catch (error) {
            return url;
        }
    }

    function addLightboxAlt(image) {
        if (!(image instanceof HTMLImageElement) || !image.closest('.spl-pane')) {
            return;
        }

        var imageUrl = normalizeUrl(image.currentSrc || image.src);
        var triggers = document.querySelectorAll('a.spotlight[href]');

        for (var i = 0; i < triggers.length; i++) {
            if (normalizeUrl(triggers[i].href) !== imageUrl) {
                continue;
            }

            var thumbnail = triggers[i].querySelector('img');
            var alt = triggers[i].dataset.title || (thumbnail ? thumbnail.alt : '');
            image.alt = alt || '';
            return;
        }

        image.alt = '';
    }

    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (!(node instanceof Element)) {
                    return;
                }

                if (node.matches('img')) {
                    addLightboxAlt(node);
                }

                node.querySelectorAll('img').forEach(addLightboxAlt);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
}());
