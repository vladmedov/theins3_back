// ─── Title character counter ──────────────────────────────────────────────────
(function () {
    var pollTimer = null;

    function attachCounter() {
        var input = document.querySelector('input[data-char-counter="title"]');
        if (!input || input.dataset.counterAttached) return;
        input.dataset.counterAttached = '1';

        input.setAttribute('maxlength', '140');
        input.maxLength = 140;

        var counter = document.createElement('span');
        counter.className = 'nova-title-counter';

        function update() {
            if (input.value.length > 140) input.value = input.value.slice(0, 140);
            counter.textContent = input.value.length + '/140';
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

// ─── Save without reload for Nova forms ────────────────────────────────────────
(function () {
    const state = {
        active: false,
        hadValidationError: false,
        requestSucceeded: false,
        currentAttemptHad422: false,
        attemptSeq: 0,
        clearUiTimeoutId: null,
        scrollYBeforeSubmit: null,
        button: null,
        originalLabel: '',
        timeoutId: null,
        successFallbackId: null,
        xhrPatched: false,
        fetchPatched: false,
        domScrollPatched: false,
        historyPatched: false,
        routerPatched: false,
        locationPatched: false,
        hrefPatched: false,
        navBlockUntil: 0,
        validationScrollLockUntil: 0,
    };

    function currentPath() {
        return window.location.pathname + window.location.search;
    }

    function getScrollY() {
        return window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
    }

    function currentResourceKey() {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const resourceIndex = parts.indexOf('resources');
        return resourceIndex >= 0 ? (parts[resourceIndex + 1] || null) : null;
    }

    function currentResourceId() {
        // Nova URL: /nova/resources/{resourceKey}/{id}/edit
        const parts = window.location.pathname.split('/').filter(Boolean);
        const resourceIndex = parts.indexOf('resources');
        return resourceIndex >= 0 ? (parts[resourceIndex + 2] || null) : null;
    }

    function normalizeTarget(target) {
        if (!target) return null;

        try {
            if (typeof target === 'string') {
                const url = new URL(target, window.location.origin);
                return url.pathname + url.search;
            }

            if (typeof target === 'object') {
                if (typeof target.path === 'string') {
                    const pathUrl = new URL(target.path, window.location.origin);
                    return pathUrl.pathname + pathUrl.search;
                }

                if (typeof target.href === 'string') {
                    const hrefUrl = new URL(target.href, window.location.origin);
                    return hrefUrl.pathname + hrefUrl.search;
                }

                if (typeof target.url === 'string') {
                    const directUrl = new URL(target.url, window.location.origin);
                    return directUrl.pathname + directUrl.search;
                }
            }
        } catch (e) {}

        return null;
    }

    function isSamePathNavigation(target) {
        const normalized = normalizeTarget(target);
        if (!normalized) return false;
        // Ignore query-string differences (Nova may append/remove it after save).
        const normalizedPathname = normalized.split('?')[0];
        return normalizedPathname === window.location.pathname;
    }

    function isNovaSaveRequest(method, url) {
        // Keep this matcher intentionally broad.
        // We'll validate the exact endpoint inside markRequestResult()
        // to avoid missing real save calls (which can trigger reload).
        const normalizedMethod = String(method || '').toLowerCase();
        const resourceKey = currentResourceKey();

        if (!resourceKey) return false;

        try {
            const parsedUrl = new URL(String(url || ''), window.location.origin);
            return ['post', 'put', 'patch'].includes(normalizedMethod)
                && parsedUrl.pathname.indexOf('/nova-api/' + resourceKey) === 0;
        } catch (e) {
            return false;
        }
    }

    function saveEndpointMatchesCurrent(url) {
        const resourceKey = currentResourceKey();
        const resourceId = currentResourceId();

        if (!resourceKey) return false;

        try {
            const parsedUrl = new URL(String(url || ''), window.location.origin);
            const base = '/nova-api/' + resourceKey;

            // Update: /nova-api/{resourceKey}/{id}
            if (resourceId && resourceId !== 'new') {
                const idBase = base + '/' + resourceId;
                return parsedUrl.pathname === idBase
                    || parsedUrl.pathname === idBase + '/'
                    || parsedUrl.pathname.indexOf(idBase + '/') === 0;
            }

            // Create: /nova-api/{resourceKey}
            return parsedUrl.pathname === base
                || parsedUrl.pathname === base + '/';
        } catch (e) {
            return false;
        }
    }

    function setButtonState(label, disabled) {
        if (!state.button) return;
        state.button.textContent = label;
        state.button.disabled = !!disabled;
        state.button.style.opacity = disabled ? '0.7' : '';
        state.button.style.pointerEvents = disabled ? 'none' : '';
    }

    function resetState(success) {
        const button = state.button;
        const originalLabel = state.originalLabel;
        const statusSelect = findStatusSelect();

        state.active = false;
        state.requestSucceeded = false;
        state.navBlockUntil = 0;
        state.validationScrollLockUntil = 0;
        if (success) {
            state.hadValidationError = false;
            state.currentAttemptHad422 = false;
        }
        state.scrollYBeforeSubmit = null;
        state.button = null;
        state.originalLabel = '';

        if (state.timeoutId) {
            clearTimeout(state.timeoutId);
            state.timeoutId = null;
        }

        if (state.clearUiTimeoutId) {
            clearTimeout(state.clearUiTimeoutId);
            state.clearUiTimeoutId = null;
        }

        if (state.successFallbackId) {
            clearTimeout(state.successFallbackId);
            state.successFallbackId = null;
        }

        if (!button) return;

        if (success && statusSelect && button.dataset && button.dataset.originalStatus !== undefined) {
            button.dataset.originalStatus = statusSelect.value;
        }

        button.textContent = originalLabel;
        button.disabled = false;
        button.style.opacity = '';
        button.style.pointerEvents = '';
    }

    function scheduleSuccessFallback() {
        if (!state.active || !state.requestSucceeded) return;

        if (state.successFallbackId) {
            clearTimeout(state.successFallbackId);
        }

        // Keep state.active/requestSucceeded for longer.
        // Nova often triggers navigation/redirect shortly after 2xx,
        // and 300ms can be too short, causing a full page reload.
        state.successFallbackId = setTimeout(function () {
            if (state.requestSucceeded) {
                resetState(true);
            }
        }, 300);
    }

    function shouldPreventAutoScroll() {
        // Prevent only while the request is actively in-flight.
        // Time-based locks may hide validation feedback on subsequent 422 attempts.
        // On 422 we want Nova's native scroll/focus to run so the error UI is visible.
        return state.active && !state.hadValidationError;
    }

    function restoreScrollAfterValidationError() {
        // Nova обычно уводит viewport к первому невалидному полю (focus/scrollIntoView/scrollTo).
        // Если просто "откатывать" скролл назад, пользователь видит прыжок туда-сюда.
        // Вместо этого блокируем автоскролл на короткое время.
        // Для последовательных 422 lock нужен дольше, иначе Nova успевает выполнить автоскролл.
        state.validationScrollLockUntil = Date.now() + 8000;
    }

    function removeErrorClassesFromElement(el) {
        if (!el || !el.classList) return;

        Array.from(el.classList).forEach(function (className) {
            const lower = String(className).toLowerCase();
            // Be conservative: don't remove generic "red" styles because
            // project/UI themes may use red for the *normal* field frames.
            // We remove only typical validation/error red classes.
            // Tailwind may add variants/prefixes like `focus:border-red-500`.
            // So we match by the exact intensity token, not only by prefix position.
            const borderRedError = lower.includes('border-red-500')
                || lower.includes('border-red-600')
                || lower.includes('border-red-700');
            const ringRedError = lower.includes('ring-red-500')
                || lower.includes('ring-red-600')
                || lower.includes('ring-red-700');
            const textRedError = lower.includes('text-red-500')
                || lower.includes('text-red-600')
                || lower.includes('text-red-700');

            // Avoid removing Tailwind variant helper classes like `invalid:border-red-*`
            // unless they are clearly the validation/error red styling.
            const looksLikeErrorClass = lower.includes('danger')
                || lower.includes('error')
                || ((lower.includes('invalid') || lower.includes('invalid:')) && (borderRedError || ringRedError || textRedError))
                || borderRedError
                || ringRedError
                || textRedError;

            if (looksLikeErrorClass) el.classList.remove(className);
        });
    }

    function clearFormErrorsUi(form) {
        if (!form || !form.querySelectorAll) return;

        // 1) Не трогаем стили рамок/кольца/бордеров.
        // Максимум — удаляем класс `error`, если он реально используется.
        form.querySelectorAll('input, select, textarea, [contenteditable="true"]').forEach(function (el) {
            try {
                if (el && el.classList && el.classList.contains('error')) {
                    el.classList.remove('error');
                }
            } catch (e) {}
        });

        // 2) Чистим текстовые блоки ошибок (тег остаётся, внутри пустота).
        // В Nova `.text-red-*` может использоваться и для required-звёздочек в `label`,
        // поэтому не чистим элементы внутри `label`, а чистим остальное.
        form.querySelectorAll('[role="alert"], .help-text, .text-danger, .text-red-500, .text-red-600').forEach(function (el) {
            try {
                if (!el || !el.textContent) return;
                if (el.closest && el.closest('label')) return; // required `*` in label

                const txt = el.textContent.trim();
                if (!txt) return;

                // Доп. защита: звёздочка может быть отдельным элементом.
                if (/^\*+$/.test(txt)) return;

                el.textContent = '';
            } catch (e) {}
        });

        // 3) Доп. чистку классов у контейнеров намеренно не делаем.
        // Nova может переиспользовать классы/верстку между попытками сохранения,
        // и слишком агрессивное снятие контейнерных `.error/.invalid`
        // иногда ломает повторное отображение сообщений на следующем 422.
    }

    function findNovaComponentInstance(predicate) {
        try {
            const app = window.Nova && window.Nova.app;
            const root = app && (
                app._instance
                || (app._container && app._container._vnode && app._container._vnode.component)
            );

            if (!root) {
                console.log('[NovaCustomSave] resetErrors: Nova root instance not found', {
                    hasNova: !!window.Nova,
                    hasApp: !!app,
                    appKeys: app ? Object.keys(app) : [],
                    hasContainer: !!(app && app._container),
                    hasContainerVNode: !!(app && app._container && app._container._vnode),
                });
                return null;
            }

            const seen = new Set();
            const stack = [root];
            let scanned = 0;

            while (stack.length) {
                const node = stack.pop();
                if (!node || seen.has(node)) continue;
                seen.add(node);
                
                const proxy = node.proxy || null;
                if (proxy) {
                    scanned++;
                    try {
                        if (predicate(proxy, node)) {
                            console.log('[NovaCustomSave] resetErrors: matched component', {
                                componentName: proxy.$options && proxy.$options.name,
                                formUniqueId: proxy.formUniqueId || (proxy.$props && proxy.$props.formUniqueId) || null,
                                scanned: scanned,
                            });
                            return proxy;
                        }
                    } catch (e) {
                        console.log('[NovaCustomSave] resetErrors: predicate error', e);
                    }
                }

                // Component instance internals
                if (node.parent) stack.push(node.parent);
                if (node.component) stack.push(node.component);
                if (node.subTree) stack.push(node.subTree);
                if (node.subTree && node.subTree.component) stack.push(node.subTree.component);
                if (node.vnode) stack.push(node.vnode);

                // VNode internals
                if (node.el && node.el.__vueParentComponent) stack.push(node.el.__vueParentComponent);
                if (node.anchor && node.anchor.__vueParentComponent) stack.push(node.anchor.__vueParentComponent);
                if (node.suspense) {
                    if (node.suspense.activeBranch) stack.push(node.suspense.activeBranch);
                    if (node.suspense.pendingBranch) stack.push(node.suspense.pendingBranch);
                }
                if (node.ssContent) stack.push(node.ssContent);
                if (node.ssFallback) stack.push(node.ssFallback);
                if (Array.isArray(node.children)) {
                    node.children.forEach(function (child) { if (child) stack.push(child); });
                }
                if (Array.isArray(node.dynamicChildren)) {
                    node.dynamicChildren.forEach(function (child) { if (child) stack.push(child); });
                }
            }
            console.log('[NovaCustomSave] resetErrors: no matching component found', {
                scanned: scanned,
            });
        } catch (e) {
            console.log('[NovaCustomSave] resetErrors: component search crashed', e);
        }

        return null;
    }

    function resetNovaValidationErrorsForCurrentForm() {
        const formEl = document.querySelector('form[data-form-unique-id]');
        const formUniqueId = formEl ? formEl.getAttribute('data-form-unique-id') : null;

        console.log('[NovaCustomSave] resetErrors: start', {
            formFound: !!formEl,
            formUniqueId: formUniqueId,
            hasVueParentComponent: !!(formEl && formEl.__vueParentComponent),
        });

        if (formEl && formEl.__vueParentComponent) {
            try {
                let current = formEl.__vueParentComponent;
                let depth = 0;

                while (current && depth < 12) {
                    const proxy = current.proxy || null;
                    if (proxy && typeof proxy.resetErrors === 'function') {
                        console.log('[NovaCustomSave] resetErrors: found via form __vueParentComponent', {
                            componentName: proxy.$options && proxy.$options.name,
                            formUniqueId: proxy.formUniqueId || (proxy.$props && proxy.$props.formUniqueId) || null,
                            depth: depth,
                        });
                        proxy.resetErrors();
                        console.log('[NovaCustomSave] resetErrors: success via form __vueParentComponent');
                        return true;
                    }

                    current = current.parent;
                    depth++;
                }

                console.log('[NovaCustomSave] resetErrors: form __vueParentComponent chain had no resetErrors()', {
                    traversedDepth: depth,
                });
            } catch (e) {
                console.log('[NovaCustomSave] resetErrors: form __vueParentComponent traversal failed', e);
            }
        }

        const instance = findNovaComponentInstance(function (proxy) {
            if (!proxy || typeof proxy.resetErrors !== 'function') return false;

            if (!formUniqueId) return !!proxy.validationErrors;

            return proxy.formUniqueId === formUniqueId
                || (proxy.$props && proxy.$props.formUniqueId === formUniqueId);
        });

        if (!instance) {
            console.log('[NovaCustomSave] resetErrors: failed, instance not found');
            return false;
        }

        try {
            console.log('[NovaCustomSave] resetErrors: invoking', {
                componentName: instance.$options && instance.$options.name,
                formUniqueId: instance.formUniqueId || (instance.$props && instance.$props.formUniqueId) || null,
                hasValidationErrors: !!instance.validationErrors,
            });
            instance.resetErrors();
            console.log('[NovaCustomSave] resetErrors: success');
            return true;
        } catch (e) {
            console.log('[NovaCustomSave] resetErrors: invocation failed', e);
            return false;
        }
    }

    function markRequestResult(status, method, url) {
        // Handle validation errors first, no matter what our current state is.
        // This prevents "our success cleanup" from interfering with Nova's 422 UI.
        if (status === 422) {
            state.hadValidationError = true;
            state.currentAttemptHad422 = true;
            restoreScrollAfterValidationError();
            // Let Nova finish rendering validation UI for this 422.
            // Resetting state synchronously can interfere with Nova's
            // follow-up DOM updates/timing.
            setTimeout(function () { resetState(false); }, 0);
            return;
        }

        if (status >= 400) {
            resetState(false);
            return;
        }

        // Success (2xx)
        if (!(status >= 200 && status < 300)) return;
        if (!state.active) return;

        // If this attempt already had a 422, ignore later 2xx responses.
        if (state.currentAttemptHad422) return;

        console.log('[NovaCustomSave] markRequestResult success', {
            status: status,
            method: method,
            url: url,
        });
        resetNovaValidationErrorsForCurrentForm();
        state.hadValidationError = false;
        state.requestSucceeded = true;
        scheduleSuccessFallback();
    }

    function installXhrPatch() {
        if (state.xhrPatched || !window.XMLHttpRequest) return;

        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            this.__novaSilentSaveMethod = method;
            this.__novaSilentSaveUrl = url;
            return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function () {
            if (isNovaSaveRequest(this.__novaSilentSaveMethod, this.__novaSilentSaveUrl)) {
                this.addEventListener('loadend', function () {
                    markRequestResult(this.status, this.__novaSilentSaveMethod, this.__novaSilentSaveUrl);
                });
            }

            return originalSend.apply(this, arguments);
        };

        state.xhrPatched = true;
    }

    function installFetchPatch() {
        if (state.fetchPatched || typeof window.fetch !== 'function') return;

        const originalFetch = window.fetch;
        window.fetch = function (input, init) {
            const method = init && init.method ? init.method : (input && input.method ? input.method : 'get');
            const url = typeof input === 'string'
                ? input
                : (input && input.url ? input.url : '');

            return originalFetch.apply(this, arguments).then(function (response) {
                if (isNovaSaveRequest(method, url)) {
                    markRequestResult(response.status, method, url);
                }

                return response;
            }).catch(function (error) {
                if (isNovaSaveRequest(method, url)) {
                    resetState(false);
                }

                throw error;
            });
        };

        state.fetchPatched = true;
    }

    function installDomScrollPatches() {
        if (state.domScrollPatched) return;

        if (typeof window.scrollTo === 'function' && !window.scrollTo.__novaSilentSaveWrapped) {
            const originalScrollTo = window.scrollTo;
            const wrappedScrollTo = function () {
                if (shouldPreventAutoScroll()) return;
                return originalScrollTo.apply(this, arguments);
            };
            wrappedScrollTo.__novaSilentSaveWrapped = true;
            window.scrollTo = wrappedScrollTo;
        }

        if (typeof window.scroll === 'function' && !window.scroll.__novaSilentSaveWrapped) {
            const originalScroll = window.scroll;
            const wrappedScroll = function () {
                if (shouldPreventAutoScroll()) return;
                return originalScroll.apply(this, arguments);
            };
            wrappedScroll.__novaSilentSaveWrapped = true;
            window.scroll = wrappedScroll;
        }

        if (window.Element && typeof Element.prototype.scrollIntoView === 'function' && !Element.prototype.scrollIntoView.__novaSilentSaveWrapped) {
            const originalScrollIntoView = Element.prototype.scrollIntoView;
            const wrappedScrollIntoView = function () {
                if (shouldPreventAutoScroll()) {
                    return;
                }

                return originalScrollIntoView.apply(this, arguments);
            };

            wrappedScrollIntoView.__novaSilentSaveWrapped = true;
            Element.prototype.scrollIntoView = wrappedScrollIntoView;
        }

        if (window.HTMLElement && typeof HTMLElement.prototype.focus === 'function' && !HTMLElement.prototype.focus.__novaSilentSaveWrapped) {
            const originalFocus = HTMLElement.prototype.focus;
            const wrappedFocus = function (options) {
                if (shouldPreventAutoScroll()) {
                    try {
                        const nextOptions = options && typeof options === 'object'
                            ? Object.assign({}, options, { preventScroll: true })
                            : { preventScroll: true };
                        return originalFocus.call(this, nextOptions);
                    } catch (e) {
                        return originalFocus.call(this);
                    }
                }

                return originalFocus.apply(this, arguments);
            };

            wrappedFocus.__novaSilentSaveWrapped = true;
            HTMLElement.prototype.focus = wrappedFocus;
        }

        state.domScrollPatched = true;
    }

    function suppressCurrentNavigation(success) {
        // Suppress navigation for the whole save window,
        // even if `state.active` was already reset.
        if (Date.now() >= state.navBlockUntil) return false;

        // If we already know the save succeeded, keep the flag.
        // Otherwise we suppress anyway without forcing success.
        if (success) state.requestSucceeded = true;
        state.validationScrollLockUntil = Date.now() + 2000;
        return true;
    }

    function shouldSuppressTarget(target) {
        // Suppress navigation to the same page while we handle the save.
        // Do NOT force `requestSucceeded=true` here; the suppressCurrentNavigation()
        // call decides whether it's a success.
        return Date.now() < state.navBlockUntil && isSamePathNavigation(target);
    }

    function patchHistoryMethod(methodName) {
        if (typeof window.history[methodName] !== 'function') return;
        if (window.history[methodName].__novaSilentSaveWrapped) return;

        const original = window.history[methodName];
        const wrapped = function (historyState, unused, url) {
            if (shouldSuppressTarget(url)) {
                return suppressCurrentNavigation(!!state.requestSucceeded);
            }

            return original.apply(this, arguments);
        };

        wrapped.__novaSilentSaveWrapped = true;
        window.history[methodName] = wrapped;
    }

    function patchFunction(target, methodName, resolver) {
        if (!target || typeof target[methodName] !== 'function') return false;
        if (target[methodName].__novaSilentSaveWrapped) return true;

        const original = target[methodName];
        const wrapped = function () {
            const targetUrl = resolver ? resolver(arguments) : arguments[0];
            if (shouldSuppressTarget(targetUrl)) {
                return Promise.resolve(suppressCurrentNavigation(!!state.requestSucceeded));
            }

            return original.apply(this, arguments);
        };

        wrapped.__novaSilentSaveWrapped = true;
        target[methodName] = wrapped;
        return true;
    }

    function installNavigationPatches() {
        if (!state.historyPatched) {
            patchHistoryMethod('pushState');
            patchHistoryMethod('replaceState');
            state.historyPatched = true;
        }

        const novaPatched = window.Nova ? patchFunction(window.Nova, 'visit') : false;
        const router = window.Nova && window.Nova.app && window.Nova.app.$router;
        const routerPushPatched = router ? patchFunction(router, 'push') : false;
        const routerReplacePatched = router ? patchFunction(router, 'replace') : false;

        if (novaPatched || routerPushPatched || routerReplacePatched) {
            state.routerPatched = true;
        }
    }

    function patchLocationPrototypeMethod(methodName, resolver) {
        if (!window.Location || !Location.prototype || typeof Location.prototype[methodName] !== 'function') return false;
        if (Location.prototype[methodName].__novaSilentSaveWrapped) return true;

        const original = Location.prototype[methodName];
        const wrapped = function () {
            const targetUrl = resolver ? resolver(arguments) : arguments[0];
            // Hard guarantee: no reload/navigation while we are handling a save.
            if (Date.now() < state.navBlockUntil) {
                suppressCurrentNavigation(!!state.requestSucceeded);
                return;
            }

            if (shouldSuppressTarget(targetUrl)) {
                suppressCurrentNavigation(!!state.requestSucceeded);
                return;
            }

            return original.apply(this, arguments);
        };

        wrapped.__novaSilentSaveWrapped = true;
        Location.prototype[methodName] = wrapped;
        return true;
    }

    function installLocationPatches() {
        if (state.locationPatched) return;

        const assignPatched = patchLocationPrototypeMethod('assign');
        const replacePatched = patchLocationPrototypeMethod('replace');
        const reloadPatched = patchLocationPrototypeMethod('reload');

        if (assignPatched || replacePatched || reloadPatched) {
            state.locationPatched = true;
        }
    }

    function patchLocationHrefSetter() {
        if (state.hrefPatched) return;
        if (!window.Location || !Location.prototype) return;

        try {
            function shouldSuppress(value) {
                return Date.now() < state.navBlockUntil;
                try {
                    const nextUrl = new URL(String(value || ''), window.location.origin);
                    return nextUrl.pathname === window.location.pathname;
                } catch (e) {
                    return false;
                }
            }

            const protoDesc = Object.getOwnPropertyDescriptor(Location.prototype, 'href');
            const protoSet = protoDesc && protoDesc.set ? protoDesc.set : null;

            if (typeof protoSet === 'function') {
                // Try patching prototype first.
                try {
                    Object.defineProperty(Location.prototype, 'href', {
                        configurable: true,
                        enumerable: protoDesc.enumerable,
                        get: function () {
                            return protoDesc.get ? protoDesc.get.call(this) : window.location.href;
                        },
                        set: function (value) {
                            if (shouldSuppress(value)) {
                                suppressCurrentNavigation(!!state.requestSucceeded);
                                return;
                            }
                            return protoSet.call(this, value);
                        },
                    });
                    state.hrefPatched = true;
                    return;
                } catch (e) {}
            }

            // Fallback: patch the instance property, if possible.
            try {
                const instanceDesc = Object.getOwnPropertyDescriptor(window.location, 'href');
                const instanceSet = instanceDesc && instanceDesc.set ? instanceDesc.set : null;
                if (typeof instanceSet !== 'function') {
                    state.hrefPatched = true;
                    return;
                }

                Object.defineProperty(window.location, 'href', {
                    configurable: true,
                    enumerable: instanceDesc.enumerable,
                    get: function () {
                        return instanceDesc.get ? instanceDesc.get.call(this) : window.location.href;
                    },
                    set: function (value) {
                        if (shouldSuppress(value)) {
                            suppressCurrentNavigation(!!state.requestSucceeded);
                            return;
                        }
                        return instanceSet.call(this, value);
                    },
                });

                state.hrefPatched = true;
            } catch (e2) {
                state.hrefPatched = true;
            }
        } catch (e) {
            state.hrefPatched = true;
        }
    }

    function ensureInstalled() {
        installXhrPatch();
        installFetchPatch();
        installDomScrollPatches();
        installNavigationPatches();
        installLocationPatches();
        patchLocationHrefSetter();
    }

    function findUpdateButton() {
        return document.querySelector('button[dusk=update-button]');
    }

    function findUpdateAndContinueEditingButton() {
        return document.querySelector('button[dusk=update-and-continue-editing-button]');
    }

    function findStatusSelect() {
        return Array.from(document.querySelectorAll('select')).find(function (el) {
            const values = Array.from(el.options || []).map(function (option) {
                return option.value;
            });

            return values.includes('draft') && values.includes('published');
        }) || null;
    }

    function shouldUseRegularSave(button) {
        if (!button) return false;

        if (state.hadValidationError) {
            return true;
        }

        const originalStatus = button.dataset.originalStatus;
        if (!originalStatus) return false;

        const statusSelect = findStatusSelect();
        if (!statusSelect) return false;

        return statusSelect.value !== originalStatus;
    }

    function saveWithoutReload(button) {
        const updateButton = findUpdateButton();
        const submitButton = updateButton || findUpdateAndContinueEditingButton();

        if (!button || !submitButton || state.active) return;

        ensureInstalled();

        state.active = true;
        state.requestSucceeded = false;
        state.currentAttemptHad422 = false;
        state.hadValidationError = false;
        state.attemptSeq += 1;
        if (state.clearUiTimeoutId) {
            clearTimeout(state.clearUiTimeoutId);
            state.clearUiTimeoutId = null;
        }
        state.navBlockUntil = Date.now() + 30000;
        state.scrollYBeforeSubmit = getScrollY();
        state.button = button;
        state.originalLabel = button.textContent;

        // Сразу блокируем любые автоскроллы/фокусы на время обработки ответа.
        state.validationScrollLockUntil = Date.now() + 8000;

        setButtonState(button.dataset.savingLabel || state.originalLabel, true);

        if (state.timeoutId) {
            clearTimeout(state.timeoutId);
        }

        state.timeoutId = setTimeout(function () {
            if (state.active) {
                resetState(false);
            }
        }, 30000);

        submitButton.click();
    }

    window.NovaCustomSave = {
        saveWithoutReload: saveWithoutReload,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            ensureInstalled();
        });
    } else {
        ensureInstalled();
    }

    let installAttempts = 0;
    const installTimer = setInterval(function () {
        installAttempts++;
        ensureInstalled();
        if ((state.xhrPatched || state.fetchPatched) && state.historyPatched && state.locationPatched) {
            clearInterval(installTimer);
            return;
        }

        if (installAttempts >= 30) {
            clearInterval(installTimer);
        }
    }, 1000);
}());

// ─── Tab memory: remember last active tab per resource type ───────────────────
// Nova's TabGroup (headlessui) stores no state between navigations.
// panel.attribute is random on every request, so we key by:
//   nova_tab_{resourceType}_{panelHeading}
//   e.g. nova_tab_posts_publication → "content"
(function () {
    const PREFIX = 'nova_tab_';

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
        if (panel.dataset.tabMemory) return;

        const buttons = panel.querySelectorAll('[dusk$="-tab-trigger"]');
        if (!buttons.length) return;

        panel.dataset.tabMemory = '1';

        // Persist active tab on every click
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const attr = btn.getAttribute('dusk').replace(/-tab-trigger$/, '');
                try { localStorage.setItem(storageKey(panel), attr); } catch (e) {}
            });
        });

        // Restore last saved tab.
        // Hide tab-card immediately to avoid the visible first-tab flash,
        // then click the saved tab and reveal the content.
        let saved;
        try { saved = localStorage.getItem(storageKey(panel)); } catch (e) {}
        if (saved) {
            const target = panel.querySelector('[dusk="' + saved + '-tab-trigger"]');
            if (target && !target.disabled) {
                const tabCard = panel.querySelector('.tab-card');
                if (tabCard) tabCard.style.visibility = 'hidden';
                setTimeout(function () {
                    target.click();
                    if (tabCard) tabCard.style.visibility = '';
                }, 0);
            }
        }
    }

    // Watch for panels added by Vue/Nova SPA navigation
    new MutationObserver(function (mutations) {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.matches && node.matches('[dusk$="-tab-panel"]')) initPanel(node);
                if (node.querySelectorAll) {
                    node.querySelectorAll('[dusk$="-tab-panel"]').forEach(initPanel);
                }
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
}());

// ─── Post tabs: remove inner content padding ─────────────────────────────────
(function () {
    function currentResourceKey() {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const resourceIndex = parts.indexOf('resources');
        return resourceIndex >= 0 ? (parts[resourceIndex + 1] || null) : null;
    }

    function isPostResource() {
        const resourceKey = currentResourceKey();
        return typeof resourceKey === 'string' && resourceKey.indexOf('post-') === 0;
    }

    function compactPostTabPanels() {
        if (!isPostResource()) return;

        document.querySelectorAll('[dusk$="-tab-panel"], [role="tabpanel"]').forEach(function (panel) {
            panel.querySelectorAll('.py-6.px-8.w-full').forEach(function (el) {
                el.classList.remove('py-6', 'px-8');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', compactPostTabPanels);
    } else {
        compactPostTabPanels();
    }

    new MutationObserver(function () {
        compactPostTabPanels();
    }).observe(document.documentElement, { childList: true, subtree: true });
}());

// ─── PanelWithoutHeader: hide panel headers with placeholder name "_pnl_*" ─────
(function () {
    const PREFIX = '_pnl_';
    function hidePanelWithoutHeader() {
        const content = document.querySelector('[dusk="content"]');
        if (!content) return;
        content.querySelectorAll('h1, h2, h3').forEach(function (el) {
            if (el.textContent.trim().startsWith(PREFIX)) {
                el.style.display = 'none';
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hidePanelWithoutHeader);
    } else {
        hidePanelWithoutHeader();
    }
    new MutationObserver(function () { hidePanelWithoutHeader(); }).observe(document.documentElement, { childList: true, subtree: true });
}());