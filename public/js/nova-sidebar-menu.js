/**
 * Nova: навигация — логотип в шапке → внешний сайт (новое окно); логотип в выезжающем меню — без действия.
 * Логотип шапки: capture + stopPropagation, чтобы Inertia Link не перехватывал клик.
 */

/* После смены языка (/set-locale → редирект с ?nova_reset_sidebar_menu=1) — состояние раскрытия
   как у свежей загрузки: «Аналитика» открыта, остальные группы свёрнуты.
   Ключи = persistCollapseKey в App\Support\Nova\SidebarMenuGroup (NovaServiceProvider). */
(function () {
    var NOVA_NAV_KEYS = [
        ['nova.navigation.analytics.collapsed', 'false'],
        ['nova.navigation.posts.collapsed', 'true'],
        ['nova.navigation.main-page.collapsed', 'true'],
        ['nova.navigation.taxonomy.collapsed', 'true'],
        ['nova.navigation.users.collapsed', 'true'],
    ];

    function resetNovaSidebarMenuCollapseState() {
        for (var i = 0; i < NOVA_NAV_KEYS.length; i++) {
            try {
                localStorage.setItem(NOVA_NAV_KEYS[i][0], NOVA_NAV_KEYS[i][1]);
            } catch (e) {}
        }
    }

    try {
        var u = new URL(window.location.href);
        if (u.searchParams.get('nova_reset_sidebar_menu') === '1') {
            resetNovaSidebarMenuCollapseState();
            u.searchParams.delete('nova_reset_sidebar_menu');
            var q = u.searchParams.toString();
            window.history.replaceState(null, '', u.pathname + (q ? '?' + q : '') + u.hash);
        }
    } catch (e) {}

    document.addEventListener(
        'click',
        function (e) {
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                return;
            }
            var a = e.target.closest && e.target.closest('a[href*="/set-locale/"]');
            if (!a || !a.getAttribute('href')) {
                return;
            }
            resetNovaSidebarMenuCollapseState();
        },
        true
    );
})();

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

    function isHeaderLogoAnchor(a) {
        if (!a || a.tagName !== 'A' || !a.getAttribute('href')) {
            return false;
        }
        if ((a.getAttribute('href') || '').indexOf('nova.laravel.com/licenses') !== -1) {
            return false;
        }
        var col = document.querySelector('#nova header > div[class*="lg:w-60"]');
        return !!(col && col.querySelector('a') === a);
    }

    /** Логотип в выезжающем меню (mobile drawer) — отдельно от шапки */
    function isDrawerLogoAnchor(a) {
        if (!a || a.tagName !== 'A' || !a.getAttribute('href')) {
            return false;
        }
        if ((a.getAttribute('href') || '').indexOf('nova.laravel.com/licenses') !== -1) {
            return false;
        }
        var drawer = a.closest('div.lg\\:hidden.w-60');
        var row = a.closest('div.border-b');
        return !!(drawer && row && row.querySelector('a') === a);
    }

    function onLogoClickCapture(e) {
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a) {
            return;
        }
        /* Выезжающее меню: клик по логотипу ничего не делает (ни Inertia, ни внешний сайт) */
        if (isDrawerLogoAnchor(a)) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return;
        }
        var u = frontendUrl();
        if (!u) {
            return;
        }
        if (!isHeaderLogoAnchor(a)) {
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
        /* Логотип в drawer не подменяем — остаётся ссылка Nova; клик глушится в onLogoClickCapture */
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
