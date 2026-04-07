// Post SEO в форме: title→seo_title; lead→seo_description (plain, ≤255). У lead-change лишние
// срабатывания Nova — храним по форме { manual, first } и сброс на inertia:finish.
(function () {
    var MAX = 255;
    var TRUNC = 252;
    var fromLead = false;
    var byForm = {};

    function convertPairedDoubleQuotesToGuillemets(text) {
        if (text == null || text === '') {
            return '';
        }
        var chars = String(text).split('');
        var pendingOpenIndex = null;
        for (var i = 0; i < chars.length; i++) {
            if (chars[i] !== '"') {
                continue;
            }
            if (pendingOpenIndex === null) {
                pendingOpenIndex = i;
            } else {
                chars[pendingOpenIndex] = '«';
                chars[i] = '»';
                pendingOpenIndex = null;
            }
        }
        return chars.join('');
    }

    function isRussianInterface() {
        var htmlLang = (document.documentElement && document.documentElement.lang) || '';
        if (/^ru(?:[-_]|$)/i.test(htmlLang)) {
            return true;
        }
        var bodyLang = (document.body && document.body.getAttribute && document.body.getAttribute('lang')) || '';
        if (/^ru(?:[-_]|$)/i.test(bodyLang)) {
            return true;
        }
        if (typeof document.cookie === 'string' && /(?:^|;\s*)locale=ru(?:;|$)/.test(document.cookie)) {
            return true;
        }
        return false;
    }

    function fid(f) {
        return (f && f.getAttribute('data-form-unique-id')) || '_default';
    }

    function row(f) {
        var id = fid(f);
        return byForm[id] || (byForm[id] = { manual: false, first: false });
    }

    function push(input, v) {
        if (!input) {
            return;
        }
        var s = v == null ? '' : String(v);
        // Без изменения значения не шлём события — иначе Nova считает форму «грязной» после save / лишних lead-change.
        if (input.value === s) {
            return;
        }
        input.value = s;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function plain(html) {
        if (html == null || html === '') {
            return '';
        }
        var s = String(html)
            .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
            .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '');
        var d = document.createElement('div');
        d.innerHTML = s;
        s = (d.textContent || d.innerText || '').replace(/\s+/g, ' ').trim();
        if (!s) {
            return '';
        }
        return s.length <= MAX ? s : s.substring(0, TRUNC) + '...';
    }

    document.addEventListener('input', function (e) {
        var t = e.target;
        if (!t.matches || !t.matches('input[data-post-seo-description="1"]') || fromLead) {
            return;
        }
        var f = t.closest('form');
        if (f) {
            row(f).manual = true;
        }
    }, true);

    var seoSyncPath = typeof location !== 'undefined' ? location.pathname : '';
    document.addEventListener('inertia:finish', function () {
        if (typeof location === 'undefined' || location.pathname === seoSyncPath) {
            return;
        }
        byForm = {};
        seoSyncPath = location.pathname;
    });

    document.addEventListener('input', function (e) {
        var t = e.target;
        if (!t.matches || !t.matches('input[data-char-counter="title"]')) {
            return;
        }
        if (isRussianInterface()) {
            var current = t.value;
            var converted = convertPairedDoubleQuotesToGuillemets(current);
            if (converted !== current) {
                var caretStart = t.selectionStart;
                var caretEnd = t.selectionEnd;
                t.value = converted;
                if (typeof caretStart === 'number' && typeof caretEnd === 'number' && t.setSelectionRange) {
                    t.setSelectionRange(caretStart, caretEnd);
                }
            }
        }
        var f = t.closest('form');
        if (f) {
            push(f.querySelector('input[data-post-seo-title="1"]'), t.value);
        }
    }, true);

    function syncDesc(html) {
        var f = document.querySelector('form[data-form-unique-id]') || document.querySelector('form');
        if (!f) {
            return;
        }
        var seo = f.querySelector('input[data-post-seo-description="1"]');
        if (!seo) {
            return;
        }
        var r = row(f);
        if (r.manual) {
            return;
        }
        var p = plain(html);
        var c = seo.value;
        if (!r.first) {
            r.first = true;
            if (c !== '' && c !== p) {
                return;
            }
        }
        fromLead = true;
        push(seo, p);
        fromLead = false;
    }

    function installLead() {
        if (window.__postLeadSeoDescriptionSyncInstalled) {
            return true;
        }
        if (!window.Nova || typeof window.Nova.$on !== 'function') {
            return false;
        }
        window.__postLeadSeoDescriptionSyncInstalled = true;
        window.Nova.$on('lead-change', function (v) {
            syncDesc(v == null ? '' : String(v));
        });
        return true;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installLead);
    } else {
        installLead();
    }
    var n = 0;
    var iv = setInterval(function () {
        if (installLead() || ++n > 80) {
            clearInterval(iv);
        }
    }, 100);
}());

// ─── Nova image cropper: allow reselecting same file after cancel ────────────
(function () {
    function clearCropperFileInputFromCancel(target) {
        if (!target || !target.closest) return;

        var cancelButton = target.closest('outlinebutton');
        if (!cancelButton) return;

        var fieldRoot = cancelButton.closest('div.space-y-4') || cancelButton.parentElement;
        if (!fieldRoot || !fieldRoot.querySelectorAll) return;

        fieldRoot.querySelectorAll('input.form-file-input[type="file"]').forEach(function (input) {
            input.value = '';
        });
    }

    document.addEventListener('click', function (e) {
        clearCropperFileInputFromCancel(e.target);
    }, true);
}());

// ─── Title character counter ──────────────────────────────────────────────────
(function () {
    var pollTimer = null;

    function attachCounter() {
        var input = document.querySelector('input[data-char-counter="title"]');
        if (!input || input.dataset.counterAttached) return;
        input.dataset.counterAttached = '1';

        var counter = document.createElement('span');
        counter.className = 'nova-title-counter';

        function update() {
            counter.textContent = input.value.length + '/140';
            counter.style.color = input.value.length > 140 ? '#dc2626' : '';
            counter.style.borderColor = input.value.length > 140 ? '#fca5a5' : '';
        }

        input.addEventListener('input', update);
        update();

        // Create a dedicated row wrapper — avoids touching the parent's Tailwind flex-col
        var row = document.createElement('div');
        row.style.cssText = 'display:flex;flex-direction:row;align-items:stretch;width:100%;gap:8px;';
        input.parentNode.insertBefore(row, input);
        row.appendChild(input);
        row.appendChild(counter);

        input.style.flex = '1';
        input.style.minWidth = '0';

        stopPoll();
    }

    function startPoll() {
        if (pollTimer) return;
        var attempts = 0;
        pollTimer = setInterval(function () {
            attempts++;
            attachCounter();
            if (attempts > 50) stopPoll();
        }, 200);
    }

    function stopPoll() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
            if (mutations[i].addedNodes.length) { startPoll(); return; }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });

    startPoll();
}());

// ─── Copy post URL button ────────────────────────────────────────────────────
(function () {
    function initCopyButtons() {
        document.querySelectorAll('.js-copy-post-url').forEach(function (btn) {
            if (btn.dataset.copyListener) return;
            btn.dataset.copyListener = '1';
            btn.addEventListener('click', function () {
                var url = btn.dataset.copyUrl;
                if (!url) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function () {
                        var oldTitle = btn.getAttribute('title');
                        btn.setAttribute('title', 'Copied!');
                        setTimeout(function () { btn.setAttribute('title', oldTitle || ''); }, 1500);
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = url;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        document.execCommand('copy');
                        var oldTitle = btn.getAttribute('title');
                        btn.setAttribute('title', 'Copied!');
                        setTimeout(function () { btn.setAttribute('title', oldTitle || ''); }, 1500);
                    } catch (e) {}
                    document.body.removeChild(ta);
                }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCopyButtons);
    } else {
        initCopyButtons();
    }
    new MutationObserver(function () { initCopyButtons(); }).observe(document.documentElement, { childList: true, subtree: true });
}());

// ─── Tab memory: remember last active tab per resource type ───────────────────
// Nova's TabGroup (headlessui) stores no state between navigations.
// panel.attribute is random on every request, so we key by:
//   nova_tab_{resourceType}_{panelHeading}
//   e.g. nova_tab_posts_publication → "content"
(function () {
    const PREFIX = 'nova_tab_';

    function resourcesPathMeta() {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const resIdx = parts.indexOf('resources');
        return {
            resource: resIdx >= 0 ? (parts[resIdx + 1] || '') : '',
            id: resIdx >= 0 ? (parts[resIdx + 2] || '') : '',
        };
    }

    function createIntentKey(resource) {
        return resource ? PREFIX + resource + '_after_create' : '';
    }

    function rememberOpenContentTabAfterCreate() {
        const { resource, id } = resourcesPathMeta();
        if (id !== 'new' || !resource) return;
        try {
            localStorage.setItem(createIntentKey(resource), 'content');
        } catch (err) {}
    }

    document.addEventListener(
        'click',
        function (e) {
            const btn = e.target && e.target.closest && e.target.closest('[dusk="create-button"], [dusk="create-and-add-another-button"]');
            if (!btn || btn.disabled) return;
            rememberOpenContentTabAfterCreate();
        },
        true
    );

    document.addEventListener('submit', rememberOpenContentTabAfterCreate, true);

    /** Редирект после create (?nova_tab=…) — читаем из URL каждый раз (после strip параметра пропадает). */
    function slugFromNovaTabQuery() {
        try {
            const raw = new URLSearchParams(window.location.search).get('nova_tab');
            if (raw && /^[a-z0-9_-]+$/i.test(raw)) {
                return raw;
            }
        } catch (e) {}
        return null;
    }

    function stripNovaTabQueryFromUrl() {
        try {
            const u = new URL(window.location.href);
            if (!u.searchParams.has('nova_tab')) return;
            u.searchParams.delete('nova_tab');
            const qs = u.searchParams.toString();
            window.history.replaceState({}, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
        } catch (e) {}
    }

    function storageKey(panel) {
        // Nova URL: /nova/resources/{type}/{id}/edit
        const parts = window.location.pathname.split('/').filter(Boolean);
        const resIdx = parts.indexOf('resources');
        const resource = resIdx >= 0 ? (parts[resIdx + 1] || 'unknown') : 'unknown';
        const id       = resIdx >= 0 ? (parts[resIdx + 2] || 'new')     : 'new';
        const heading  = panel.querySelector(':scope > div > h1, :scope > div > h2');
        const name     = heading
            ? heading.textContent.trim().toLowerCase().replace(/\s+/g, '_')
            : 'panel';
        return PREFIX + resource + '_' + id + '_' + name;
    }

    function initPanel(panel) {
        const buttons = panel.querySelectorAll('[dusk$="-tab-trigger"]');
        if (!buttons.length) return;

        if (panel.dataset.novaTabListeners !== '1') {
            panel.dataset.novaTabListeners = '1';
            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const attr = btn.getAttribute('dusk').replace(/-tab-trigger$/, '');
                    try {
                        localStorage.setItem(storageKey(panel), attr);
                    } catch (e) {}
                });
            });
        }

        if (panel.dataset.novaTabRestored === '1') return;

        let saved;
        try {
            saved = localStorage.getItem(storageKey(panel));
        } catch (e) {}

        const tabFromUrl = slugFromNovaTabQuery();
        if (tabFromUrl) {
            saved = tabFromUrl;
        } else {
            const pathMeta = resourcesPathMeta();
            if (!saved && pathMeta.resource && /^\d+$/.test(pathMeta.id)) {
                try {
                    if (localStorage.getItem(createIntentKey(pathMeta.resource)) === 'content') {
                        saved = 'content';
                    }
                } catch (e) {}
            }
        }

        if (!saved) {
            panel.dataset.novaTabRestored = '1';
            return;
        }

        let target = panel.querySelector('[dusk="' + saved + '-tab-trigger"]');
        if (!target && saved === 'content') {
            target = panel.querySelector('[dusk="content-tab-trigger"]');
        }
        if (!target || target.disabled) {
            return;
        }

        panel.dataset.novaTabRestored = '1';

        const pathMeta = resourcesPathMeta();
        const intentKey = createIntentKey(pathMeta.resource);
        const tabCard = panel.querySelector('.tab-card');
        if (tabCard) tabCard.style.visibility = 'hidden';
        setTimeout(function () {
            try {
                if (saved === 'content' && intentKey && localStorage.getItem(intentKey) === 'content') {
                    localStorage.removeItem(intentKey);
                }
            } catch (e) {}
            if (tabFromUrl) {
                stripNovaTabQueryFromUrl();
                try {
                    localStorage.setItem(storageKey(panel), saved);
                } catch (e) {}
            }
            target.click();
            if (tabCard) tabCard.style.visibility = '';
        }, 0);
    }

    function scanTabPanels() {
        document.querySelectorAll('[dusk$="-tab-panel"]').forEach(initPanel);
    }

    scanTabPanels();
    document.addEventListener('DOMContentLoaded', scanTabPanels);
    requestAnimationFrame(function () {
        requestAnimationFrame(scanTabPanels);
    });
    setTimeout(scanTabPanels, 0);
    setTimeout(scanTabPanels, 120);
    setTimeout(scanTabPanels, 400);

    let tabScanTimer;
    new MutationObserver(function () {
        clearTimeout(tabScanTimer);
        tabScanTimer = setTimeout(scanTabPanels, 50);
    }).observe(document.documentElement, { childList: true, subtree: true });

    document.addEventListener('inertia:finish', scanTabPanels);
    window.addEventListener('popstate', scanTabPanels);
}());

