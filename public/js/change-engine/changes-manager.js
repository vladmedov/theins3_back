(function () {
    if (window.NovaChangesManagerV2) return;

    var TRACKED_ROOT_ATTRS = [
        'title',
        'lead',
        'image_description',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'related_posts',
    ];

    var state = {
        ready: false,
        isDirty: false,
        changedFields: [],
        firstChangedAt: null,
        lastChangedAt: null,
        lastSavedAt: null,
        inFlightSave: false,
        baselineHash: null,
        currentHash: null,
        _baselineEntries: null,
        _currentEntries: null,
    };

    var listeners = [];
    var initialized = false;
    var debounceMs = 150;
    var evalTimer = null;
    var novaEmitHooked = false;
    var networkHooked = false;

    function clonePublicState() {
        return {
            ready: state.ready,
            isDirty: state.isDirty,
            changedFields: state.changedFields.slice(),
            firstChangedAt: state.firstChangedAt,
            lastChangedAt: state.lastChangedAt,
            lastSavedAt: state.lastSavedAt,
            inFlightSave: state.inFlightSave,
            baselineHash: state.baselineHash,
            currentHash: state.currentHash,
        };
    }

    function notify() {
        var snapshot = clonePublicState();
        listeners.slice().forEach(function (fn) {
            try { fn(snapshot); } catch (e) {}
        });

        try {
            window.dispatchEvent(new CustomEvent('post:changes:state', { detail: snapshot }));
            window.dispatchEvent(new CustomEvent('post:changes:dirty-changed', { detail: { isDirty: snapshot.isDirty } }));
        } catch (e2) {}
    }

    function currentResourceKey() {
        var parts = window.location.pathname.split('/').filter(Boolean);
        var i = parts.indexOf('resources');
        return i >= 0 ? (parts[i + 1] || null) : null;
    }

    function isNovaResourceSaveRequest(method, url) {
        var m = String(method || '').toLowerCase();
        var key = currentResourceKey();
        if (!key || ['post', 'put', 'patch'].indexOf(m) === -1) return false;
        try {
            return new URL(String(url || ''), window.location.origin).pathname.indexOf('/nova-api/' + key) === 0;
        } catch (e) { return false; }
    }

    function isInsideFormActionBar(el) {
        return !!(el && el.closest && el.closest('[data-form-action-bar="1"], #nova-info-bar'));
    }

    function isSearchOrDialogInput(el) {
        if (!el || !el.closest) return false;
        return !!(
            (el.matches && el.matches('input[type="search"]'))
            || el.closest('[dusk$="-search-input"], [role="combobox"], [dusk$="-dropdown"], [dusk$="-results"]')
            || el.closest('.ck-balloon-panel, .ck-dialog, .ck-termin-overlay, .ck-termin-modal')
        );
    }

    function isRelatedPostsUiTarget(el) {
        if (!el || !el.closest) return false;
        return !!(
            el.closest('[data-field-attribute="related_posts"]')
            || el.closest('[data-attribute="related_posts"]')
            || el.closest('[dusk*="related_posts"]')
            || el.closest('[dusk*="related-posts"]')
            || el.closest('.outl1ne-multiselect-field')
        );
    }

    function isTrackedAttributeName(attr) {
        if (!attr || typeof attr !== 'string') return false;
        if (attr.indexOf('__') !== -1) return true;
        var normalized = attr.toLowerCase().replace(/[^a-z0-9]+/g, '');

        for (var i = 0; i < TRACKED_ROOT_ATTRS.length; i++) {
            var root = TRACKED_ROOT_ATTRS[i];
            if (attr === root || attr.endsWith('-' + root) || attr.endsWith('_' + root)) return true;
            if (normalized.indexOf(String(root).toLowerCase().replace(/[^a-z0-9]+/g, '')) !== -1) return true;
        }
        return false;
    }

    function isTrackedElement(el) {
        if (!el || !el.closest) return false;

        if (el.closest('[data-post-autosave-content="1"]')) return true;
        if (el.closest('[data-post-autosave-field="1"]')) return true;

        var dusk = String((el.getAttribute && el.getAttribute('dusk')) || '');
        if (dusk.indexOf('__') !== -1 || dusk.indexOf('related_posts') !== -1) return true;
        if (el.closest('[dusk^="content-"]')) return true;

        var name = String(el.name || '');
        var id = String(el.id || '');

        for (var i = 0; i < TRACKED_ROOT_ATTRS.length; i++) {
            var attr = TRACKED_ROOT_ATTRS[i];
            if (name.indexOf(attr) !== -1 || id.indexOf(attr) !== -1) return true;
        }

        if (name.indexOf('__') !== -1 || id.indexOf('__') !== -1) return true;
        return false;
    }

    function nearestFlexibleBlockKey(el) {
        if (!el || !el.closest) return 'root';
        var block = el.closest('[dusk^="content-"]');
        if (!block) return 'root';
        return String(block.getAttribute('dusk') || 'root');
    }

    function isRelatedPostsMultiselectField(fieldRoot, form) {
        if (!fieldRoot || !form || !fieldRoot.closest) return false;
        if (fieldRoot.closest('[dusk*="related_posts"], [dusk*="related-posts"]')) return true;

        var cursor = fieldRoot;
        for (var i = 0; i < 6 && cursor && cursor !== form; i++) {
            if (cursor.querySelector && cursor.querySelector('label[for*="related_posts"]')) return true;
            cursor = cursor.parentElement;
        }
        return false;
    }

    function getNovaForm() {
        return document.querySelector('form[data-form-unique-id]') || null;
    }

    function buildEntries(form) {
        var entries = {};
        if (!form) return entries;

        form.querySelectorAll('input, textarea, select').forEach(function (el) {
            if (!el || isInsideFormActionBar(el)) return;
            if (!isTrackedElement(el)) return;

            var type = (el.type || '').toLowerCase();
            if (type === 'button' || type === 'submit' || type === 'reset' || type === 'file') return;

            var name = el.name || el.id || '';
            if (!name) return;

            var tag = (el.tagName || '').toLowerCase();
            var blockKey = nearestFlexibleBlockKey(el);
            var key = blockKey + '::' + tag + ':' + name + ':' + type;
            if (type === 'checkbox' || type === 'radio') {
                entries[key] = el.checked ? (el.value || 'on') : '';
            } else {
                entries[key] = el.value || '';
            }
        });

        form.querySelectorAll('.outl1ne-multiselect-field').forEach(function (fieldRoot) {
            if (!isRelatedPostsMultiselectField(fieldRoot, form)) return;
            var blockKey = nearestFlexibleBlockKey(fieldRoot);
            var tags = Array.from(fieldRoot.querySelectorAll('.multiselect__tag > span'))
                .map(function (node) { return String((node.textContent || '')).trim(); })
                .filter(function (v) { return v !== ''; });

            entries['related_posts_ui:' + blockKey] = tags.join('|');
        });

        return entries;
    }

    function hashEntries(entries) {
        var parts = [];
        Object.keys(entries).sort().forEach(function (key) {
            parts.push(key + '=' + String(entries[key]));
        });
        return parts.join('\n');
    }

    function diffKeys(a, b) {
        var all = {};
        Object.keys(a || {}).forEach(function (k) { all[k] = true; });
        Object.keys(b || {}).forEach(function (k) { all[k] = true; });
        var out = [];
        Object.keys(all).forEach(function (k) {
            if (String((a || {})[k] || '') !== String((b || {})[k] || '')) out.push(k);
        });
        out.sort();
        return out;
    }

    function recalc() {
        var form = getNovaForm();
        if (!form) {
            state.ready = false;
            state.currentHash = null;
            state._currentEntries = null;
            state.changedFields = [];
            state.isDirty = false;
            state.firstChangedAt = null;
            state.lastChangedAt = null;
            notify();
            return;
        }

        state.ready = true;
        var prevCurrentHash = state.currentHash;
        var prevIsDirty = state.isDirty;
        state._currentEntries = buildEntries(form);
        state.currentHash = hashEntries(state._currentEntries);

        if (!state._baselineEntries) {
            state.changedFields = [];
            state.isDirty = false;
            state.firstChangedAt = null;
            state.lastChangedAt = null;
            notify();
            return;
        }

        state.changedFields = diffKeys(state._baselineEntries, state._currentEntries);
        state.isDirty = state.changedFields.length > 0;

        if (state.isDirty) {
            var now = Date.now();
            if (!prevIsDirty || !state.firstChangedAt) {
                state.firstChangedAt = now;
            }
            // Do not treat focus/blur/re-render as a new change.
            // Only bump lastChangedAt when serialized form values actually changed.
            if (!prevIsDirty || prevCurrentHash !== state.currentHash) {
                state.lastChangedAt = now;
            }
        } else {
            state.firstChangedAt = null;
            state.lastChangedAt = null;
        }

        notify();
    }

    function scheduleRecalc() {
        if (evalTimer) clearTimeout(evalTimer);
        evalTimer = setTimeout(function () {
            evalTimer = null;
            recalc();
        }, debounceMs);
    }

    function bumpLastChangedAtForTyping() {
        // Keep autosave countdown anchored to the latest user activity while typing.
        // Heavy diff/serialization still runs via debounced recalc().
        if (!state.ready || state.inFlightSave || !state.isDirty) return;
        state.lastChangedAt = Date.now();
        notify();
    }

    function captureBaseline() {
        var form = getNovaForm();
        state._baselineEntries = form ? buildEntries(form) : null;
        state.baselineHash = state._baselineEntries ? hashEntries(state._baselineEntries) : null;
        state.lastSavedAt = Date.now();
        recalc();
    }

    function setInFlightSave(flag) {
        state.inFlightSave = !!flag;
        notify();
    }

    function installDomListeners() {
        document.addEventListener('input', function (ev) {
            var target = ev && ev.target;
            if (!target || !state.ready) return;
            if (isInsideFormActionBar(target)) return;
            if (isSearchOrDialogInput(target) && !isRelatedPostsUiTarget(target)) return;

            var form = getNovaForm();
            if (!form || !form.contains(target)) return;
            if (!isTrackedElement(target) && !isRelatedPostsUiTarget(target)) return;

            var type = (target.type || '').toLowerCase();
            if (type === 'file') return;
            bumpLastChangedAtForTyping();
            scheduleRecalc();
        }, true);

        document.addEventListener('change', function (ev) {
            var target = ev && ev.target;
            if (!target || !state.ready) return;
            if (isInsideFormActionBar(target)) return;
            if (isSearchOrDialogInput(target) && !isRelatedPostsUiTarget(target)) return;

            var form = getNovaForm();
            if (!form || !form.contains(target)) return;
            if (!isTrackedElement(target) && !isRelatedPostsUiTarget(target)) return;

            var type = (target.type || '').toLowerCase();
            if (type === 'file') return;
            bumpLastChangedAtForTyping();
            scheduleRecalc();
        }, true);

        document.addEventListener('click', function (ev) {
            var target = ev && ev.target;
            if (!target || !state.ready) return;
            if (!isRelatedPostsUiTarget(target)) return;
            bumpLastChangedAtForTyping();
            setTimeout(scheduleRecalc, 0);
            setTimeout(scheduleRecalc, 120);
        }, true);

        document.addEventListener('nova-autosave:change', function (e) {
            var d = e && e.detail;
            if (!d || !isTrackedAttributeName(d.attribute || '')) return;
            bumpLastChangedAtForTyping();
            scheduleRecalc();
        }, true);
    }

    function hookNovaEmit() {
        if (novaEmitHooked || !window.Nova || typeof window.Nova.$emit !== 'function') return;
        novaEmitHooked = true;

        var parentEmit = window.Nova.$emit.bind(window.Nova);
        window.Nova.$emit = function () {
            var eventName = arguments[0];
            var result = parentEmit.apply(this, arguments);
            try {
                if (typeof eventName === 'string' && eventName.endsWith('-change')) {
                    var attr = eventName.slice(0, -7);
                    if (isTrackedAttributeName(attr)) {
                        setTimeout(scheduleRecalc, 0);
                    }
                }
            } catch (e) {}
            return result;
        };
    }

    function hookNetwork() {
        if (networkHooked) return;
        networkHooked = true;

        if (window.XMLHttpRequest && !XMLHttpRequest.prototype.__novaChangesV2OpenHook) {
            var parentOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.__novaChangesV2OpenHook = true;
            XMLHttpRequest.prototype.open = function (method, url) {
                this.__novaChangesV2Method = method;
                this.__novaChangesV2Url = url;
                return parentOpen.apply(this, arguments);
            };
        }

        if (window.XMLHttpRequest && !XMLHttpRequest.prototype.__novaChangesV2SendHook) {
            var parentSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.__novaChangesV2SendHook = true;
            XMLHttpRequest.prototype.send = function () {
                var xhr = this;
                xhr.addEventListener('loadend', function () {
                    var method = xhr.__novaSilentSaveMethod || xhr.__novaChangesV2Method;
                    var url = xhr.__novaSilentSaveUrl || xhr.__novaChangesV2Url;
                    if (!isNovaResourceSaveRequest(method, url)) return;
                    if (xhr.status >= 200 && xhr.status < 300) {
                        // Important order: first set clean baseline, then drop inFlight.
                        // Prevents transient dirty=true state that can flash countdown after save.
                        setTimeout(function () {
                            captureBaseline();
                            setInFlightSave(false);
                        }, 0);
                        return;
                    }
                    setInFlightSave(false);
                });
                return parentSend.apply(this, arguments);
            };
        }

        if (typeof window.fetch === 'function' && !window.fetch.__novaChangesV2FetchHook) {
            var prevFetch = window.fetch;
            function wrappedFetch() {
                var args = arguments;
                return prevFetch.apply(this, args).then(function (response) {
                    try {
                        var input = args[0];
                        var init = args[1] || {};
                        var method = (init.method || (input && input.method) || 'get');
                        var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                        if (isNovaResourceSaveRequest(method, url)) {
                            if (response && response.status >= 200 && response.status < 300) {
                                setTimeout(function () {
                                    captureBaseline();
                                    setInFlightSave(false);
                                }, 0);
                                return;
                            }
                            setInFlightSave(false);
                        }
                    } catch (e) {}
                    return response;
                });
            }
            wrappedFetch.__novaChangesV2FetchHook = true;
            window.fetch = wrappedFetch;
        }
    }

    function reset() {
        state.ready = false;
        state.isDirty = false;
        state.changedFields = [];
        state.firstChangedAt = null;
        state.lastChangedAt = null;
        state.inFlightSave = false;
        state.baselineHash = null;
        state.currentHash = null;
        state._baselineEntries = null;
        state._currentEntries = null;
        notify();
    }

    function refresh() {
        var form = getNovaForm();
        if (!form) {
            reset();
            return;
        }
        state.ready = true;
        if (!state._baselineEntries) {
            captureBaseline();
            setTimeout(function () {
                if (!state.isDirty) captureBaseline();
            }, 2500);
            return;
        }
        recalc();
    }

    function subscribe(fn) {
        if (typeof fn !== 'function') return function () {};
        listeners.push(fn);
        fn(clonePublicState());
        return function () {
            listeners = listeners.filter(function (x) { return x !== fn; });
        };
    }

    function init(options) {
        if (initialized) {
            refresh();
            return;
        }
        initialized = true;
        debounceMs = options && typeof options.debounceMs === 'number' ? options.debounceMs : 150;

        installDomListeners();
        hookNovaEmit();
        hookNetwork();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { setTimeout(refresh, 0); });
        } else {
            setTimeout(refresh, 0);
        }

        document.addEventListener('inertia:finish', function () { reset(); setTimeout(refresh, 0); });
        window.addEventListener('popstate', function () { reset(); setTimeout(refresh, 0); });

        (function tryHookNova() {
            if (!window.Nova || typeof window.Nova.$on !== 'function') {
                setTimeout(tryHookNova, 200);
                return;
            }
            window.Nova.$on('resource-loaded', function () {
                reset();
                setTimeout(refresh, 0);
            });
        }());
    }

    window.NovaChangesManagerV2 = {
        init: init,
        refresh: refresh,
        reset: reset,
        markSaved: captureBaseline,
        setInFlightSave: setInFlightSave,
        getState: clonePublicState,
        subscribe: subscribe,
    };
}());
