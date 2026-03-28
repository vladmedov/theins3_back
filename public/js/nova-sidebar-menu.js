/**
 * Nova: навигация — аккордеон сайдбара; ссылка «Перейти на сайт» в шапке.
 */
(function () {
    function topLevelRoot(el, menu) {
        if (!el || !menu.contains(el)) {
            return null;
        }
        var n = el;
        while (n && n.parentElement) {
            if (n.parentElement === menu) {
                return n;
            }
            n = n.parentElement;
        }
        return null;
    }

    function isCollapsibleSectionRoot(root) {
        if (!root || root.children.length < 1) {
            return false;
        }
        var h = root.firstElementChild;
        if (h.tagName === 'H4') {
            return true;
        }
        if (h.tagName === 'BUTTON') {
            return true;
        }
        if (h.tagName === 'A' && !h.classList.contains('min-h-8')) {
            return true;
        }
        return false;
    }

    function isSectionExpanded(root) {
        return root.children.length >= 2;
    }

    function onSidebarClickCapture(e) {
        var menu = e.target.closest('[dusk="sidebar-menu"]');
        if (!menu) {
            return;
        }

        var root = topLevelRoot(e.target, menu);
        if (!root || !isCollapsibleSectionRoot(root)) {
            return;
        }

        var header = root.firstElementChild;
        if (!header || !header.contains(e.target)) {
            return;
        }

        if (isSectionExpanded(root)) {
            return;
        }

        var siblings = menu.children;
        for (var i = 0; i < siblings.length; i++) {
            var sib = siblings[i];
            if (sib === root || !isCollapsibleSectionRoot(sib) || !isSectionExpanded(sib)) {
                continue;
            }
            var sh = sib.firstElementChild;
            if (sh) {
                sh.click();
            }
        }
    }

    document.addEventListener('click', onSidebarClickCapture, true);
}());

/**
 * Ссылка «Перейти на сайт» в колонке логотипа (Nova.config.frontendPublicUrl).
 * Предупреждение о лицензии скрыто в nova-sidebar-menu.css.
 */
(function () {
    var DATA = 'data-ins-public-site-link';

    function frontendUrl() {
        try {
            if (window.Nova && typeof window.Nova.config === 'function') {
                var u = window.Nova.config('frontendPublicUrl');
                if (u != null && String(u).trim() !== '') {
                    return String(u).trim();
                }
            }
        } catch (e) {}
        return null;
    }

    function logoColumn() {
        return document.querySelector('#nova header > div[class*="lg:w-60"]');
    }

    function ensurePublicSiteLink() {
        var url = frontendUrl();
        if (!url) {
            return;
        }
        var col = logoColumn();
        if (!col) {
            return;
        }
        var existing = col.querySelector('a[' + DATA + '="1"]');
        if (existing) {
            if (existing.getAttribute('href') !== url) {
                existing.setAttribute('href', url);
            }
            return;
        }
        var a = document.createElement('a');
        a.setAttribute(DATA, '1');
        a.href = url;
        a.textContent = 'Перейти на сайт';
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.className =
            'nova-ins-site-link text-gray-400 dark:text-gray-400 hover:text-white dark:hover:text-gray-200 text-xs font-semibold text-center';
        col.appendChild(a);
    }

    document.addEventListener('DOMContentLoaded', ensurePublicSiteLink);
    document.addEventListener('inertia:finish', ensurePublicSiteLink);
    setTimeout(ensurePublicSiteLink, 0);
    setTimeout(ensurePublicSiteLink, 400);
}());
