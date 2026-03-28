/**
 * Nova: навигация — логотип → внешний сайт (новое окно), аккордеон сайдбара.
 * Логотип: document capture + stopPropagation, чтобы Inertia Link не перехватывал клик.
 */

(function () {
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

    function isLogoAnchor(a) {
        if (!a || a.tagName !== 'A' || !a.getAttribute('href')) {
            return false;
        }
        if ((a.getAttribute('href') || '').indexOf('nova.laravel.com/licenses') !== -1) {
            return false;
        }
        var col = document.querySelector('#nova header > div[class*="lg:w-60"]');
        if (col && col.querySelector('a') === a) {
            return true;
        }
        var drawer = a.closest('div.lg\\:hidden.w-60');
        var row = a.closest('div.border-b');
        if (drawer && row && row.querySelector('a') === a) {
            return true;
        }
        return false;
    }

    function onLogoClickCapture(e) {
        var u = frontendUrl();
        if (!u) {
            return;
        }
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        var a = e.target.closest && e.target.closest('a[href]');
        if (!isLogoAnchor(a)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        window.open(u, '_blank', 'noopener,noreferrer');
    }

    document.addEventListener('click', onLogoClickCapture, true);

    function syncLogoHref() {
        var url = frontendUrl();
        if (!url) {
            return;
        }
        var col = document.querySelector('#nova header > div[class*="lg:w-60"]');
        if (col) {
            var la = col.querySelector('a');
            if (la && (la.getAttribute('href') || '').indexOf('nova.laravel.com/licenses') === -1) {
                la.setAttribute('href', url);
                la.target = '_blank';
                la.rel = 'noopener noreferrer';
            }
        }
        document.querySelectorAll('body > div.lg\\:hidden.w-60 div.border-b > a[href]').forEach(function (la) {
            if ((la.getAttribute('href') || '').indexOf('nova.laravel.com/licenses') === -1) {
                la.setAttribute('href', url);
                la.target = '_blank';
                la.rel = 'noopener noreferrer';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', syncLogoHref);
    document.addEventListener('inertia:finish', syncLogoHref);
    setTimeout(syncLogoHref, 0);
    setTimeout(syncLogoHref, 400);
}());

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
