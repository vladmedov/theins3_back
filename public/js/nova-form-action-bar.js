/**
 * Form Action Bar: сохранение без перезагрузки, автосохранение, toggle publish, post edit lock.
 * Подключается после public/js/nova-custom.js (NovaServiceProvider).
 */

// ─── Save without reload for Nova forms ────────────────────────────────────────
(function () {
    const state = {
        active: false,
        hadValidationError: false,
        requestSucceeded: false,
        publishToggleActionPending: null,
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
        simpleSubmitLocked: false,
        simpleSubmitButton: null,
        simpleSubmitSafetyTimeoutId: null,
        simpleSubmitLockedAt: 0,
        simpleSubmitMinUnlockDelayMs: 1000,
    };
    const autosaveState = {
        timerId: null,
        countdownIntervalId: null,
        deadlineAt: 0,
        isBootstrapping: true,
        togglePublishUiInstalled: false,
        postOwnersSelfLockInstalled: false,
        /** Медленная загрузка (VPN): Vue шлёт *-change с задержкой — ждём load + паузу и мин. окно (не слишком долго, иначе быстрые правки теряются) */
        bootstrapPath: null,
        bootstrapPhaseStarted: false,
        bootstrapMinReady: false,
        bootstrapAfterLoadReady: false,
        bootstrapMinTimerId: null,
        bootstrapLoadTimerId: null,
        bootstrapLoadListener: null,
        /** Не перечитывать data-last-saved-at с DOM на каждом ensureInstalled (ломает время на бою) */
        domSavedAtSyncedForPath: null,
        suppressUntilTs: 0,
    };

    /** Синхронно с `form-action-bar.blade.php` ($secondaryActionStyles) */
    const TOGGLE_PUBLISH_VARIANT_STYLES = {
        'success-link': 'display:inline-flex;align-items:center;height:36px;padding:0 4px;font-size:14px;font-weight:500;background:none;border:none;cursor:pointer;white-space:nowrap;color:#16a34a;text-decoration:underline;',
        'danger-link': 'display:inline-flex;align-items:center;height:36px;padding:0 4px;font-size:14px;font-weight:500;background:none;border:none;cursor:pointer;white-space:nowrap;color:#dc2626;text-decoration:underline;',
        'neutral-link': 'display:inline-flex;align-items:center;height:36px;padding:0 4px;font-size:14px;font-weight:500;background:none;border:none;cursor:pointer;white-space:nowrap;color:#475569;text-decoration:underline;',
    };

    function currentPath() {
        return window.location.pathname + window.location.search;
    }

    /** /nova/resources/{type}/{id}/edit — бар статуса может появиться позже Vue */
    function isNovaResourceEditPath() {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const idx = parts.indexOf('resources');
        return idx >= 0 && parts.length >= idx + 4 && parts[idx + 3] === 'edit';
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

    function getFormActionBars() {
        return Array.from(document.querySelectorAll('[data-form-action-bar="1"]'));
    }

    function findNovaResourceSubmitButton() {
        return document.querySelector('button[dusk=create-button],button[dusk=update-button]');
    }

    function unlockSimpleSubmitButton() {
        if (!state.simpleSubmitLocked) return;

        const elapsed = Date.now() - (state.simpleSubmitLockedAt || 0);
        const remaining = state.simpleSubmitMinUnlockDelayMs - elapsed;
        if (remaining > 0) {
            setTimeout(function () {
                unlockSimpleSubmitButton();
            }, remaining);
            return;
        }

        if (state.simpleSubmitSafetyTimeoutId) {
            clearTimeout(state.simpleSubmitSafetyTimeoutId);
            state.simpleSubmitSafetyTimeoutId = null;
        }

        const button = state.simpleSubmitButton;
        state.simpleSubmitButton = null;
        state.simpleSubmitLocked = false;
        state.simpleSubmitLockedAt = 0;

        if (!button || !button.parentNode) return;
        button.disabled = false;
        button.style.opacity = '';
        button.style.pointerEvents = '';
    }

    /** fill() бросил до запроса — снять блокировку UI (иначе ждём timeout). */
    function abortSaveBeforeHttpRequest() {
        try {
            setV2InFlightHint(false);
        } catch (e) {}

        unlockSimpleSubmitButton();

        if (state.active) {
            resetState(false, true);
        }
    }

    function triggerCreateSubmit() {
        const nativeSubmit = findNovaResourceSubmitButton() || findAnyNovaSubmitButton();
        if (nativeSubmit && !nativeSubmit.disabled) {
            nativeSubmit.click();
            return true;
        }

        if (nativeSubmit) {
            const nativeForm = nativeSubmit.form || findNovaResourceForm();
            if (nativeForm && typeof nativeForm.requestSubmit === 'function') {
                try {
                    nativeForm.requestSubmit(nativeSubmit);
                    return true;
                } catch (e) {
                    try {
                        nativeForm.requestSubmit();
                        return true;
                    } catch (e2) {}
                }
            }
        }

        const fallbackEnabled = document.querySelector(
            'button[dusk=create-button]:not([disabled]), button[dusk=update-button]:not([disabled]), button[dusk=update-and-continue-editing-button]:not([disabled]), button[dusk=update-button-and-continue-editing-button]:not([disabled])'
        );
        if (fallbackEnabled) {
            fallbackEnabled.click();
            return true;
        }

        const form = findNovaResourceForm();
        if (form && typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return true;
        }

        return false;
    }

    function submitResource(button) {
        if (state.simpleSubmitLocked || state.active) return;

        if (window.NovaCustomSave && window.NovaCustomSave.cancelPendingAutosave) {
            window.NovaCustomSave.cancelPendingAutosave();
        }

        ensureInstalled();

        if (!triggerCreateSubmit()) {
            return;
        }

        state.simpleSubmitLocked = true;
        state.simpleSubmitLockedAt = Date.now();
        state.simpleSubmitButton = button || null;

        if (button) {
            button.disabled = true;
            button.style.opacity = '0.7';
            button.style.pointerEvents = 'none';
        }

        // Safety unlock if no network callback observed (e.g. client-side prevented submit).
        state.simpleSubmitSafetyTimeoutId = setTimeout(function () {
            unlockSimpleSubmitButton();
        }, 3000);
    }

    function getAutosaveStatusRoots() {
        return Array.from(document.querySelectorAll('[data-autosave-status-root="1"]'));
    }

    function getAutosaveStatusNodes() {
        return getAutosaveStatusRoots().map(function (root) {
            return root.querySelector('[data-autosave-status-text="1"]');
        }).filter(Boolean);
    }

    function getAutosaveCountdownNodes() {
        return getAutosaveStatusRoots().map(function (root) {
            return {
                root: root,
                node: root.querySelector('[data-autosave-countdown-text="1"]'),
            };
        }).filter(function (entry) {
            return !!entry.node;
        });
    }

    function formatBrowserDateTime(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }

        try {
            return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date);
        } catch (e) {
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = String(date.getFullYear());
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return day + '.' + month + '.' + year + ' ' + hours + ':' + minutes;
        }
    }

    function initializePreviewNotices() {
        document.querySelectorAll('[data-preview-notice="1"]').forEach(function (node) {
            const expiresAt = node.dataset.previewExpiresAt;
            if (!expiresAt) return;

            const parsed = new Date(expiresAt);
            if (Number.isNaN(parsed.getTime())) return;

            const prefix = node.dataset.previewPrefix || '';
            const suffix = node.dataset.previewSuffix || '';
            const formatted = formatBrowserDateTime(parsed);

            if (!formatted) return;

            node.textContent = prefix + ' ' + formatted + (suffix ? ' · ' + suffix : '');
        });
    }

    function clearAutosaveBootstrapTimers() {
        if (autosaveState.bootstrapMinTimerId) {
            clearTimeout(autosaveState.bootstrapMinTimerId);
            autosaveState.bootstrapMinTimerId = null;
        }
        if (autosaveState.bootstrapLoadTimerId) {
            clearTimeout(autosaveState.bootstrapLoadTimerId);
            autosaveState.bootstrapLoadTimerId = null;
        }
        if (autosaveState.bootstrapLoadListener) {
            window.removeEventListener('load', autosaveState.bootstrapLoadListener);
            autosaveState.bootstrapLoadListener = null;
        }
    }

    function tryFinishAutosaveBootstrap() {
        if (!autosaveState.bootstrapMinReady || !autosaveState.bootstrapAfterLoadReady) {
            return;
        }
        autosaveState.isBootstrapping = false;
        // Не вызывать notifyAutosaveChange здесь: при монтировании CKEditor и др. шлют повторный *-change
        // с нормализованным HTML — раньше это после bootstrap давало ложное автосохранение через debounceMs.
    }

    function initializeAutosaveStatus() {
        const path = currentPath();

        if (autosaveState.bootstrapPath !== path) {
            clearAutosaveBootstrapTimers();
            autosaveState.bootstrapPath = path;
            autosaveState.isBootstrapping = true;
            autosaveState.bootstrapPhaseStarted = false;
            autosaveState.bootstrapMinReady = false;
            autosaveState.bootstrapAfterLoadReady = false;
            autosaveState.domSavedAtSyncedForPath = null;
        }

        const statusRoots = getAutosaveStatusRoots();
        if (autosaveState.domSavedAtSyncedForPath !== path && statusRoots.length > 0) {
            let appliedSavedAtFromDom = false;
            statusRoots.forEach(function (root) {
                if (!root || !root.dataset) return;

                const savedAt = root.dataset.lastSavedAt;
                if (!savedAt) {
                    return;
                }

                const parsed = new Date(savedAt);
                if (Number.isNaN(parsed.getTime())) {
                    return;
                }

                updateAutosaveSavedAt(parsed);
                appliedSavedAtFromDom = true;
            });
            if (appliedSavedAtFromDom) {
                autosaveState.domSavedAtSyncedForPath = path;
            }
        }

        if (getAutosaveStatusRoots().length === 0) {
            if (!isNovaResourceEditPath()) {
                autosaveState.isBootstrapping = false;
                autosaveState.bootstrapPhaseStarted = true;
                autosaveState.bootstrapMinReady = true;
                autosaveState.bootstrapAfterLoadReady = true;
                return;
            }
            return;
        }

        if (autosaveState.bootstrapPhaseStarted) {
            return;
        }
        autosaveState.bootstrapPhaseStarted = true;

        autosaveState.bootstrapMinTimerId = setTimeout(function () {
            autosaveState.bootstrapMinTimerId = null;
            autosaveState.bootstrapMinReady = true;
            tryFinishAutosaveBootstrap();
        }, 700);

        function scheduleAfterLoadPause() {
            autosaveState.bootstrapLoadTimerId = setTimeout(function () {
                autosaveState.bootstrapLoadTimerId = null;
                autosaveState.bootstrapAfterLoadReady = true;
                tryFinishAutosaveBootstrap();
            }, 500);
        }

        if (document.readyState === 'complete') {
            scheduleAfterLoadPause();
        } else {
            const onLoad = function () {
                window.removeEventListener('load', onLoad);
                autosaveState.bootstrapLoadListener = null;
                scheduleAfterLoadPause();
            };
            autosaveState.bootstrapLoadListener = onLoad;
            window.addEventListener('load', onLoad);
        }
    }

    // Legacy autosave change hooks removed.

    /**
     * Поле «Доступ к управлению» (owners): текущий пользователь не может снять себя с тега;
     * сервер через fillUsing всё равно добавляет auth()->id() при сохранении.
     */
    function findOwnersTagFieldValuePayload(selectedTagsRoot) {
        let el = selectedTagsRoot;
        for (var depth = 0; depth < 60 && el; depth++) {
            var c = el.__vueParentComponent;
            if (c) {
                var proxy = c.proxy;
                if (proxy) {
                    var fld = proxy.field || proxy.currentField;
                    if (fld && fld.attribute === 'owners' && Array.isArray(proxy.value)) {
                        return proxy.value;
                    }
                }
            }
            el = el.parentElement;
        }
        return null;
    }

    function installPostOwnersSelfLock() {
        if (autosaveState.postOwnersSelfLockInstalled) {
            return;
        }
        autosaveState.postOwnersSelfLockInstalled = true;

        document.addEventListener(
            'click',
            function (e) {
                var root = e.target.closest('[dusk="owners-selected-tags"]');
                if (!root) {
                    return;
                }

                var nova = window.Nova;
                if (!nova || typeof nova.config !== 'function') {
                    return;
                }

                var lockUid = nova.config('userId');
                if (lockUid == null || lockUid === '') {
                    return;
                }

                var outer = e.target.closest('[dusk="owners-selected-tags"] .flex.flex-wrap > button');
                if (!outer) {
                    return;
                }

                var clickedBtn = e.target.closest('button');
                if (!clickedBtn || clickedBtn === outer) {
                    return;
                }

                var wrap = outer.parentElement;
                if (!wrap) {
                    return;
                }
                var idx = Array.prototype.indexOf.call(wrap.children, outer);
                if (idx < 0) {
                    return;
                }

                var payload = findOwnersTagFieldValuePayload(root);
                if (!payload || !payload[idx]) {
                    return;
                }

                var tagId = parseInt(payload[idx].value, 10);
                var uid = parseInt(lockUid, 10);
                if (isNaN(tagId) || isNaN(uid) || tagId !== uid) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
            },
            true
        );
    }

    function isExistingResource() {
        const resourceId = currentResourceId();
        return !!resourceId && resourceId !== 'new';
    }

    function isDraftSelected() {
        const statusSelect = findStatusSelect();
        return !!statusSelect && statusSelect.value === 'draft';
    }

    /** Режим «не ваш» лок / перехват: автосохранение не должно запускаться */
    function isPostEditLockBlockingEdit() {
        var bar = document.querySelector('[data-post-edit-lock-enabled]');
        if (!bar) {
            return false;
        }
        return bar.getAttribute('data-nova-post-edit-disabled') === '1';
    }

    function isAutosaveEnabled() {
        // Legacy autosave is intentionally disabled:
        // autosave lifecycle is handled by change-engine v2 scripts.
        return false;

        /* legacy condition (kept for quick rollback)
        return (
            isExistingResource()
            && isDraftSelected()
            && getAutosaveStatusRoots().length > 0
            && !isPostEditLockBlockingEdit()
        );
        */
    }

    function hasAutosaveUi() {
        return getAutosaveStatusRoots().length > 0;
    }

    /** Все кнопки «Сохранить» с таймером (верхняя и нижняя панели). */
    function getAllAutosaveStayButtons() {
        const buttons = [];
        getFormActionBars().forEach(function (bar) {
            const btn = bar.querySelector('button[data-saving-label]');
            if (btn) {
                buttons.push(btn);
            }
        });
        return buttons;
    }

    function findAutosaveButton() {
        const buttons = getAllAutosaveStayButtons();
        return buttons.length ? buttons[0] : null;
    }

    /** Подпись кнопки Stay «Сохранить» без таймера автосохранения */
    function getAutosaveStayButtonDefaultLabel(button) {
        if (!button || !button.dataset) {
            return '';
        }
        const fromAttr = button.dataset.autosaveSaveDefaultLabel;
        if (fromAttr !== undefined && fromAttr !== '') {
            return fromAttr;
        }
        return button.textContent || '';
    }

    function isV2AutosaveActive() {
        try {
            return !!(
                window.NovaChangeEngineV2
                && window.NovaChangeEngineV2.config
                && window.NovaChangeEngineV2.config.enabled
                && window.NovaChangeEngineV2.config.enableAutosave
            );
        } catch (e) {
            return false;
        }
    }

    function renderAutosaveSaveButtonCountdown(remainingSeconds) {
        if (isV2AutosaveActive()) {
            return;
        }
        getAllAutosaveStayButtons().forEach(function (btn) {
            if (!btn || !btn.dataset) {
                return;
            }
            const def = getAutosaveStayButtonDefaultLabel(btn);
            if (!def) {
                return;
            }
            btn.textContent = def + ' ' + String(remainingSeconds);
        });
    }

    function restoreAutosaveSaveButtonLabel() {
        if (isV2AutosaveActive()) {
            return;
        }
        getAllAutosaveStayButtons().forEach(function (btn) {
            if (!btn) {
                return;
            }
            const def = getAutosaveStayButtonDefaultLabel(btn);
            if (def) {
                btn.textContent = def;
            }
        });
    }

    function getAutosaveSavingLabel() {
        const button = findAutosaveButton();
        return button && button.dataset ? (button.dataset.savingLabel || 'Saving...') : 'Saving...';
    }

    function getAutosaveIdleLabel() {
        const root = getAutosaveStatusRoots()[0];
        return root && root.dataset ? (root.dataset.autosaveIdleLabel || root.dataset.autosaveLabel || '') : '';
    }

    function getAutosaveLastSavedLabel() {
        const root = getAutosaveStatusRoots()[0];
        return root && root.dataset ? (root.dataset.lastSavedLabel || '') : '';
    }

    function getAutosaveLastSavedDatePrefix() {
        const root = getAutosaveStatusRoots()[0];
        return root && root.dataset ? (root.dataset.lastSavedDatePrefix || '').trim() : '';
    }

    function getAutosaveLastSavedAt() {
        const root = getAutosaveStatusRoots()[0];
        return root && root.dataset ? (root.dataset.lastSavedAt || '') : '';
    }

    function getAutosaveFailureLabel() {
        const root = getAutosaveStatusRoots()[0];
        return root && root.dataset ? (root.dataset.autosaveFailureLabel || '') : '';
    }

    function clearAutosaveCaptions() {
        getAutosaveStatusRoots().forEach(function (root) {
            var cap = root.querySelector('[data-autosave-caption="1"]');
            if (cap) {
                cap.textContent = '';
                cap.style.display = 'none';
            }
        });
    }

    function setAutosaveStatusText(text) {
        clearAutosaveCaptions();
        getAutosaveStatusNodes().forEach(function (node) {
            node.textContent = text;
            node.classList.remove('nova-post-edit-lock__value', 'nova-post-edit-lock__time');
        });
    }

    function hideAutosaveCountdown() {
        getAutosaveCountdownNodes().forEach(function (entry) {
            entry.node.textContent = '';
            entry.node.style.display = 'none';
        });
        restoreAutosaveSaveButtonLabel();
    }

    function getAutosaveCountdownText(seconds) {
        const root = getAutosaveStatusRoots()[0];
        const prefix = root && root.dataset ? (root.dataset.autosaveCountdownPrefix || '') : '';
        const suffix = root && root.dataset ? (root.dataset.autosaveCountdownSuffix || '') : '';

        if (!seconds || seconds < 1) {
            return '';
        }

        return prefix + ' ' + seconds + suffix;
    }

    function renderAutosaveCountdown() {
        if (!autosaveState.deadlineAt) {
            hideAutosaveCountdown();
            return;
        }

        const remainingMs = autosaveState.deadlineAt - Date.now();
        if (remainingMs <= 0) {
            hideAutosaveCountdown();
            return;
        }

        const remainingSeconds = Math.max(1, Math.ceil(remainingMs / 1000));
        const text = getAutosaveCountdownText(remainingSeconds);

        if (text) {
            getAutosaveCountdownNodes().forEach(function (entry) {
                entry.node.textContent = text;
                entry.node.style.display = 'inline-flex';
            });
        } else {
            getAutosaveCountdownNodes().forEach(function (entry) {
                entry.node.textContent = '';
                entry.node.style.display = 'none';
            });
        }

        renderAutosaveSaveButtonCountdown(remainingSeconds);
    }

    function stopAutosaveCountdown() {
        autosaveState.deadlineAt = 0;

        if (autosaveState.countdownIntervalId) {
            clearInterval(autosaveState.countdownIntervalId);
            autosaveState.countdownIntervalId = null;
        }

        hideAutosaveCountdown();
    }

    function formatAutosaveTime(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }

        try {
            return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            }).format(date);
        } catch (e) {
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return hours + ':' + minutes + ':' + seconds;
        }
    }

    function isSameLocalCalendarDay(a, b) {
        if (!(a instanceof Date) || !(b instanceof Date)) return false;
        if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) return false;

        return a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth()
            && a.getDate() === b.getDate();
    }

    /** ДД.ММ.ГГГГ в локальной таймзоне браузера */
    function formatAutosaveDateOnly(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }

        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = String(date.getFullYear());

        return day + '.' + month + '.' + year;
    }

    /** Как в PHP: d.m.Y H:i:s (локальное время браузера) — блок «Сохранено» в Form Action Bar */
    function formatLockLastSavedDisplay(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }

        const d = String(date.getDate()).padStart(2, '0');
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const y = String(date.getFullYear());
        const h = String(date.getHours()).padStart(2, '0');
        const min = String(date.getMinutes()).padStart(2, '0');
        const s = String(date.getSeconds()).padStart(2, '0');

        return d + '.' + m + '.' + y + ' ' + h + ':' + min + ':' + s;
    }

    function syncLockLastSavedSpans(date) {
        var text = formatLockLastSavedDisplay(date);
        if (!text) {
            return;
        }

        document.querySelectorAll('[data-lock-last-saved="1"]').forEach(function (el) {
            el.textContent = text;
        });
    }

    function updateAutosaveSavedAt(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            setAutosaveStatusText(getAutosaveIdleLabel());
            return;
        }

        getAutosaveStatusRoots().forEach(function (root) {
            if (root && root.dataset) {
                root.dataset.lastSavedAt = date.toISOString();
            }
        });

        syncLockLastSavedSpans(date);

        const now = new Date();
        const prefix = getAutosaveLastSavedLabel();

        if (isSameLocalCalendarDay(date, now)) {
            const formatted = formatAutosaveTime(date);

            if (!formatted || !prefix) {
                setAutosaveStatusText(getAutosaveIdleLabel());
                return;
            }

            getAutosaveStatusRoots().forEach(function (root) {
                const p = root.dataset ? (root.dataset.lastSavedLabel || '').trim() : '';
                const caption = root.querySelector('[data-autosave-caption="1"]');
                const status = root.querySelector('[data-autosave-status-text="1"]');
                if (!status) {
                    return;
                }
                if (caption && p) {
                    caption.textContent = p;
                    caption.style.display = '';
                } else if (caption) {
                    caption.textContent = '';
                    caption.style.display = 'none';
                }
                status.textContent = formatted;
                status.classList.add('nova-post-edit-lock__value', 'nova-post-edit-lock__time');
            });
        } else {
            const dateOnly = formatAutosaveDateOnly(date);

            if (!dateOnly) {
                setAutosaveStatusText(getAutosaveIdleLabel());
                return;
            }

            const datePrefix = getAutosaveLastSavedDatePrefix();
            setAutosaveStatusText(datePrefix ? datePrefix + ' ' + dateOnly : dateOnly);
        }
    }

    function showAutosaveFailure() {
        const label = getAutosaveFailureLabel();
        setAutosaveStatusText(label || getAutosaveIdleLabel());
    }

    function showAutosaveIdleState() {
        const savedAt = getAutosaveLastSavedAt();
        if (savedAt) {
            const parsed = new Date(savedAt);
            if (!Number.isNaN(parsed.getTime())) {
                updateAutosaveSavedAt(parsed);
                return;
            }
        }

        setAutosaveStatusText(getAutosaveIdleLabel());
    }

    function clearAutosaveTimer() {
        // Legacy autosave pipeline removed (V2 handles scheduling/countdown).
        if (autosaveState.timerId) {
            clearTimeout(autosaveState.timerId);
            autosaveState.timerId = null;
        }
        stopAutosaveCountdown();
    }

    function suppressAutosaveAfterManualSave() {
        // Kept for backward compatibility with manual-save flow.
        clearAutosaveTimer();
    }

    function notifyAutosaveChange() {
        // Legacy autosave pipeline removed.
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
        const buttons = getAllAutosaveStayButtons();
        if (!buttons.length && !state.button) return;

        const effectiveLabel = label || (state.button ? getAutosaveStayButtonDefaultLabel(state.button) : '');
        buttons.forEach(function (btn) {
            if (effectiveLabel) {
                btn.textContent = effectiveLabel;
            }
            btn.disabled = !!disabled;
            btn.style.opacity = disabled ? '0.7' : '';
            btn.style.pointerEvents = disabled ? 'none' : '';
        });
    }

    function resetState(success, showFailure) {
        const button = state.button;
        const buttons = getAllAutosaveStayButtons();
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

        if (success && statusSelect) {
            syncStayButtonsOriginalStatusFromSelect();
        }

        buttons.forEach(function (btn) {
            const defaultLabel = getAutosaveStayButtonDefaultLabel(btn);
            if (defaultLabel) {
                btn.textContent = defaultLabel;
            }
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.pointerEvents = '';
        });

        if (!buttons.length && button) {
            const fallbackLabel = getAutosaveStayButtonDefaultLabel(button) || state.originalLabel;
            if (fallbackLabel) {
                button.textContent = fallbackLabel;
            }
            button.disabled = false;
            button.style.opacity = '';
            button.style.pointerEvents = '';
        }

        if (!success && showFailure && hasAutosaveUi()) {
            showAutosaveFailure();
        } else if (!success && !isAutosaveEnabled()) {
            showAutosaveIdleState();
        }
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
        if (state.simpleSubmitLocked && saveEndpointMatchesCurrent(url) && status >= 200) {
            unlockSimpleSubmitButton();
        }

        // Handle validation errors first, no matter what our current state is.
        // This prevents "our success cleanup" from interfering with Nova's 422 UI.
        if (status === 422) {
            state.hadValidationError = true;
            state.currentAttemptHad422 = true;
            restoreScrollAfterValidationError();
            // Let Nova finish rendering validation UI for this 422.
            // Resetting state synchronously can interfere with Nova's
            // follow-up DOM updates/timing.
            setTimeout(function () { resetState(false, true); }, 0);
            return;
        }

        if (status >= 400) {
            if (state.simpleSubmitLocked && saveEndpointMatchesCurrent(url)) {
                unlockSimpleSubmitButton();
            }
            resetState(false, true);
            return;
        }

        // Success (2xx)
        if (!(status >= 200 && status < 300)) return;

        const statusStripPresent = getAutosaveStatusRoots().length > 0;
        const resourceUpdate = isExistingResource() && saveEndpointMatchesCurrent(url);

        // Обычное сохранение Nova (не через saveWithoutReload): обновить «Сохранено …» у опубликованных и др.
        if (!state.active && statusStripPresent && resourceUpdate && !state.currentAttemptHad422) {
            updateAutosaveSavedAt(new Date());
            setTimeout(function () {
                syncStayButtonsOriginalStatusFromSelect();
                refreshTogglePublishButtons();
            }, 10);
            if (resourceUpdate) {
                window.dispatchEvent(new CustomEvent('nova:save-without-reload:success'));
            }
            return;
        }

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
        if (isExistingResource()) {
            updateAutosaveSavedAt(new Date());
        }
        setTimeout(function () {
            syncStayButtonsOriginalStatusFromSelect();
            refreshTogglePublishButtons();
        }, 0);
        if (resourceUpdate) {
            window.dispatchEvent(new CustomEvent('nova:save-without-reload:success'));
        }
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
            if (isNovaSaveRequest(this.__novaSilentSaveMethod, this.__novaSilentSaveUrl) && state.publishToggleActionPending) {
                try {
                    this.setRequestHeader('X-Nova-Post-Publish-Click', state.publishToggleActionPending);
                } catch (e) {}
                state.publishToggleActionPending = null;
            }

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
            const isSaveRequest = isNovaSaveRequest(method, url);
            const shouldSendPublishHeader = isSaveRequest && !!state.publishToggleActionPending;
            const publishAction = state.publishToggleActionPending;
            const nextInit = shouldSendPublishHeader
                ? Object.assign({}, init || {}, {
                    headers: Object.assign({}, (init && init.headers) || {}, {
                        'X-Nova-Post-Publish-Click': publishAction,
                    }),
                })
                : init;

            if (shouldSendPublishHeader) {
                state.publishToggleActionPending = null;
            }

            return originalFetch.call(this, input, nextInit).then(function (response) {
                if (isSaveRequest) {
                    markRequestResult(response.status, method, url);
                }

                return response;
            }).catch(function (error) {
                if (isSaveRequest) {
                    resetState(false, true);
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
        installPostOwnersSelfLock();
        installTogglePublishUi();
        initializeAutosaveStatus();
        initializePreviewNotices();
    }

    function findUpdateButton() {
        return document.querySelector('button[dusk=update-button]');
    }

    function findUpdateAndContinueEditingButton() {
        return document.querySelector('button[dusk=update-and-continue-editing-button], button[dusk=update-button-and-continue-editing-button]');
    }

    function findAnyNovaSubmitButton() {
        return document.querySelector(
            'button[dusk=update-button], button[dusk=create-button], button[dusk=update-and-continue-editing-button], button[dusk=update-button-and-continue-editing-button]'
        );
    }

    function findNovaResourceForm() {
        return document.querySelector('form[data-form-unique-id]') || document.querySelector('form');
    }

    /**
     * FormActionBar рендерится внутри Heading (v-html). При клике из поля с фокусом:
     *   pointerdown → blur → change → Vue ре-рендерит v-html → кнопка уничтожена →
     *   click приходит на отсоединённый от DOM элемент → обработчик не срабатывает.
     *
     * Решение: ловим pointerdown (до blur/re-render), захватываем режим кнопки,
     * выполняем действие через setTimeout(0) — после того как Vue закончит ре-рендер.
     */
    (function installFormActionBarClickDelegation() {
        var pendingAction = null;

        function findNovaSubmit() {
            var form = findNovaResourceForm();
            var sel = 'button[dusk=create-button],button[dusk=update-button]';
            return (form && form.querySelector(sel)) || document.querySelector(sel);
        }

        function executeFabAction(mode, originButton) {
            if (mode === 'native') {
                var b = findNovaSubmit();
                if (b) b.click();
                return;
            }
            if (mode === 'custom-save') {
                if (window.NovaCustomSave && typeof window.NovaCustomSave.saveWithoutReload === 'function') {
                    window.NovaCustomSave.saveWithoutReload(originButton);
                }
            }
        }

        document.addEventListener('pointerdown', function (e) {
            var btn = e.target && e.target.closest && e.target.closest('[data-nova-form-action-bar-click]');
            if (!btn || btn.tagName !== 'BUTTON') return;
            var mode = btn.getAttribute('data-nova-form-action-bar-click');
            if (!mode) return;
            pendingAction = { mode: mode, button: btn };
            setTimeout(function () {
                if (!pendingAction) return;
                var action = pendingAction;
                pendingAction = null;
                executeFabAction(action.mode, action.button);
            }, 0);
        }, true);

        document.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest && e.target.closest('[data-nova-form-action-bar-click]');
            if (!btn || btn.tagName !== 'BUTTON') return;
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            if (pendingAction) return;
            var mode = btn.getAttribute('data-nova-form-action-bar-click');
            if (mode) executeFabAction(mode, btn);
        }, true);
    })();

    function triggerNovaSubmit(submitButton) {
        if (submitButton) {
            if (!submitButton.disabled) {
                submitButton.click();
                return true;
            }

            const fallbackEnabled = document.querySelector(
                'button[dusk=update-button]:not([disabled]), button[dusk=create-button]:not([disabled]), button[dusk=update-and-continue-editing-button]:not([disabled]), button[dusk=update-button-and-continue-editing-button]:not([disabled])'
            );
            if (fallbackEnabled) {
                fallbackEnabled.click();
                return true;
            }

            return false;
        }

        const form = findNovaResourceForm();
        if (form && typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return true;
        }

        return false;
    }

    function setV2InFlightHint(active) {
        try {
            if (
                window.NovaChangesManagerV2
                && typeof window.NovaChangesManagerV2.setInFlightSave === 'function'
            ) {
                window.NovaChangesManagerV2.setInFlightSave(!!active);
            }
        } catch (e) {}
    }

    function notifyV2ManualSaveStart() {
        try {
            window.dispatchEvent(new CustomEvent('nova:manual-save:start'));
        } catch (e) {}
    }

    function findStatusSelect() {
        return Array.from(document.querySelectorAll('select')).find(function (el) {
            const values = Array.from(el.options || []).map(function (option) {
                return option.value;
            });

            return values.includes('draft') && values.includes('published');
        }) || null;
    }

    /** Последний статус, сохранённый на сервере (как у «Сохранить»), не черновик в селекте до save */
    function getLastSavedPublicationStatus() {
        const stay = findAutosaveButton();
        if (stay && stay.dataset && stay.dataset.originalStatus !== undefined && stay.dataset.originalStatus !== '') {
            return stay.dataset.originalStatus;
        }

        const s = findStatusSelect();
        return s ? s.value : 'draft';
    }

    function syncStayButtonsOriginalStatusFromSelect() {
        const sel = findStatusSelect();
        if (!sel) return;

        getFormActionBars().forEach(function (bar) {
            const btn = bar.querySelector('button[data-saving-label]');
            if (btn && btn.dataset && btn.dataset.originalStatus !== undefined) {
                btn.dataset.originalStatus = sel.value;
            }
        });
    }

    function refreshTogglePublishButtons() {
        const isPublished = getLastSavedPublicationStatus() === 'published';

        document.querySelectorAll('[data-toggle-publish-action="1"]').forEach(function (btn) {
            if (!btn || !btn.dataset) return;

            const whenPublished = btn.dataset.labelWhenPublished || '';
            const whenDraft = btn.dataset.labelWhenDraft || '';
            const variantPublished = btn.dataset.variantWhenPublished || 'danger-link';
            const variantDraft = btn.dataset.variantWhenDraft || 'success-link';

            btn.textContent = isPublished ? whenPublished : whenDraft;
            const variant = isPublished ? variantPublished : variantDraft;
            const style = TOGGLE_PUBLISH_VARIANT_STYLES[variant] || TOGGLE_PUBLISH_VARIANT_STYLES['neutral-link'];
            btn.setAttribute('style', style);
        });
    }

    function runTogglePublishAction() {
        const s = findStatusSelect();
        if (!s) return;

        const saved = getLastSavedPublicationStatus();
        const targetStatus = saved === 'published' ? 'draft' : 'published';
        state.publishToggleActionPending = targetStatus === 'published' ? 'publish' : 'unpublish';

        setTimeout(function () {
            const submit = document.querySelector('button[dusk=create-button],button[dusk=update-button]');
            if (submit) submit.click();
        }, 100);
    }

    function installTogglePublishUi() {
        if (autosaveState.togglePublishUiInstalled) return;
        autosaveState.togglePublishUiInstalled = true;

        refreshTogglePublishButtons();
    }

    function saveWithoutReload(button, options) {
        const updateButton = findUpdateButton();
        const submitButton = updateButton || findUpdateAndContinueEditingButton() || findAnyNovaSubmitButton();
        const fromAutosave = !!(options && options.fromAutosave);

        if (!button || state.active) return;
        if (!fromAutosave) {
            notifyV2ManualSaveStart();
            // Manual click should immediately stop V2 countdown re-scheduling.
            setV2InFlightHint(true);
        }
        if (submitButton && submitButton.disabled) {
            if (fromAutosave) {
                return;
            }
            // Manual click must not be ignored while Nova toggles disabled on inner submit buttons.
            // Continue and rely on triggerNovaSubmit() fallback (enabled submit or form.requestSubmit()).
        }

        if (!fromAutosave) {
            // Manual save: cancel active countdown and suppress immediate re-scheduling from reactive field updates.
            suppressAutosaveAfterManualSave(1000);
        } else {
            clearAutosaveTimer();
        }

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
        state.originalLabel = getAutosaveStayButtonDefaultLabel(button) || button.textContent;

        // Сразу блокируем любые автоскроллы/фокусы на время обработки ответа.
        state.validationScrollLockUntil = Date.now() + 8000;

        setButtonState(button.dataset.savingLabel || state.originalLabel, true);
        if (hasAutosaveUi()) {
            setAutosaveStatusText(getAutosaveSavingLabel());
        }

        if (state.timeoutId) {
            clearTimeout(state.timeoutId);
        }

        state.timeoutId = setTimeout(function () {
            if (state.active) {
                resetState(false, true);
            }
        }, 30000);

        if (!triggerNovaSubmit(submitButton)) {
            setV2InFlightHint(false);
            resetState(false, true);
        }
    }

    function cancelPendingAutosave() {
        suppressAutosaveAfterManualSave(1000);
        if (hasAutosaveUi()) {
            showAutosaveIdleState();
        }
    }

    window.NovaCustomSave = {
        saveWithoutReload: saveWithoutReload,
        notifyChange: notifyAutosaveChange,
        cancelPendingAutosave: cancelPendingAutosave,
    };

    window.NovaFormActionBar = {
        togglePublish: runTogglePublishAction,
        submitResource: submitResource,
        unlockSimpleSubmit: abortSaveBeforeHttpRequest,
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
// ─── Nova Post edit lock (Redis): heartbeat, takeover, UI toggle ────────────
(function () {
    var HEARTBEAT_MS = 30000;
    var heartbeatTimer = null;
    /** @type {null|function(): void} */
    var postEditLockHeartbeatRun = null;

    /** CKEditor 5: несколько read-only lock id; не конфликтует с readonly полей Nova */
    var POST_EDIT_LOCK_CK_ID = 'nova-post-edit-lock';
    var ATTR_LOCK_SAVED = 'data-nova-post-edit-lock-saved';
    /** @type {MutationObserver|null} */
    var postEditLockFieldObserver = null;
    var postEditLockFieldObserverTimer = null;

    function getPostEditLockFormScope() {
        return document.querySelector('form[data-form-unique-id]') || document.querySelector('form');
    }

    function isInsideFormActionBar(el) {
        return !!(el && el.closest && el.closest('#nova-info-bar, .nova-form-action-bar, [data-form-action-bar]'));
    }

    function unlockPostEditLockCkEditorsInScope(scope) {
        if (!scope || !scope.querySelectorAll) {
            return;
        }
        scope.querySelectorAll('textarea[' + ATTR_LOCK_SAVED + ']').forEach(function (el) {
            if (!el.ckeditorInstance) {
                return;
            }
            var prev = el.getAttribute('data-nova-post-edit-lock-prev-disabled');
            el.removeAttribute(ATTR_LOCK_SAVED);
            el.removeAttribute('data-nova-post-edit-lock-prev-disabled');
            try {
                el.ckeditorInstance.disableReadOnlyMode(POST_EDIT_LOCK_CK_ID);
            } catch (e) {}
            if (prev === '1') {
                el.disabled = true;
            } else if (prev === '0') {
                el.disabled = false;
            }
        });
        /* Старый вариант: нативный disabled — больше не выставляем, но снимаем метки если остались */
        scope.querySelectorAll('[' + ATTR_LOCK_SAVED + ']').forEach(function (el) {
            if (el.tagName === 'TEXTAREA' && el.ckeditorInstance) {
                return;
            }
            var prev = el.getAttribute(ATTR_LOCK_SAVED);
            el.removeAttribute(ATTR_LOCK_SAVED);
            el.disabled = prev === '1';
        });
    }

    /** Только CKEditor: read-only через API; остальные поля — CSS pointer-events на форме (Vue сбрасывает disabled). */
    function lockPostEditLockCkEditorsInScope(scope) {
        if (!scope || !scope.querySelectorAll) {
            return;
        }
        scope.querySelectorAll('textarea').forEach(function (el) {
            if (isInsideFormActionBar(el)) {
                return;
            }
            if (!el.ckeditorInstance) {
                return;
            }
            if (!el.hasAttribute(ATTR_LOCK_SAVED)) {
                el.setAttribute(ATTR_LOCK_SAVED, '1');
                el.setAttribute('data-nova-post-edit-lock-prev-disabled', el.disabled ? '1' : '0');
            }
            el.disabled = false;
            try {
                el.ckeditorInstance.enableReadOnlyMode(POST_EDIT_LOCK_CK_ID);
            } catch (e) {}
        });
    }

    function setFormPointerLockAttribute(disabled) {
        var form = getPostEditLockFormScope();
        if (!form) {
            return;
        }
        if (disabled) {
            form.setAttribute('data-post-edit-lock-fields-disabled', '1');
        } else {
            form.removeAttribute('data-post-edit-lock-fields-disabled');
        }
    }

    function applyPostEditLockFields(disabled) {
        var scope = getPostEditLockFormScope();
        if (!scope) {
            setFormPointerLockAttribute(false);
            return;
        }
        setFormPointerLockAttribute(!!disabled);
        if (!disabled) {
            unlockPostEditLockCkEditorsInScope(scope);
            return;
        }
        lockPostEditLockCkEditorsInScope(scope);
    }

    function syncPostEditLockFieldsFromBar() {
        var bar = document.querySelector('[data-post-edit-lock-enabled]');
        if (!bar) {
            teardownPostEditLockFieldObserver();
            applyPostEditLockFields(false);
            return;
        }
        var disabled = bar.getAttribute('data-nova-post-edit-disabled') === '1';
        applyPostEditLockFields(disabled);
        if (disabled) {
            if (window.NovaCustomSave && typeof window.NovaCustomSave.cancelPendingAutosave === 'function') {
                window.NovaCustomSave.cancelPendingAutosave();
            }
            setupPostEditLockFieldObserver();
        } else {
            teardownPostEditLockFieldObserver();
        }
    }

    function setupPostEditLockFieldObserver() {
        teardownPostEditLockFieldObserver();
        var scope = getPostEditLockFormScope();
        if (!scope) {
            return;
        }
        postEditLockFieldObserver = new MutationObserver(function () {
            if (postEditLockFieldObserverTimer) {
                clearTimeout(postEditLockFieldObserverTimer);
            }
            postEditLockFieldObserverTimer = setTimeout(function () {
                postEditLockFieldObserverTimer = null;
                var b = document.querySelector('[data-post-edit-lock-enabled]');
                if (!b || b.getAttribute('data-nova-post-edit-disabled') !== '1') {
                    return;
                }
                lockPostEditLockCkEditorsInScope(scope);
            }, 50);
        });
        postEditLockFieldObserver.observe(scope, { childList: true, subtree: true });
    }

    function teardownPostEditLockFieldObserver() {
        if (postEditLockFieldObserverTimer) {
            clearTimeout(postEditLockFieldObserverTimer);
            postEditLockFieldObserverTimer = null;
        }
        if (postEditLockFieldObserver) {
            postEditLockFieldObserver.disconnect();
            postEditLockFieldObserver = null;
        }
    }

    function clearHeartbeat() {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
        postEditLockHeartbeatRun = null;
    }

    /** Как в первом IIFE: /nova/resources/{type}/{id}/edit */
    function isNovaResourceEditPath() {
        var parts = window.location.pathname.split('/').filter(Boolean);
        var idx = parts.indexOf('resources');
        return idx >= 0 && parts.length >= idx + 4 && parts[idx + 3] === 'edit';
    }

    /** SPA: ушли со страницы редактирования — таймер heartbeat не должен продлевать лок в Redis. */
    function stopPostEditLockHeartbeatIfLeftEditPage() {
        if (heartbeatTimer === null && postEditLockHeartbeatRun === null) {
            return;
        }
        if (isNovaResourceEditPath()) {
            return;
        }
        clearHeartbeat();
        teardownPostEditLockFieldObserver();
        applyPostEditLockFields(false);
    }

    function esc(s) {
        if (s == null) {
            return '';
        }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') || '' : '';
    }

    function parseI18n(root) {
        if (!root || !root.getAttribute) {
            return {};
        }
        var editing = root.getAttribute('data-pe-msg-editing');
        if (editing !== null && editing !== '') {
            return {
                editing_locked_with_expiry: editing,
                locked_until_label: root.getAttribute('data-pe-msg-locked-until-label') || '',
                readonly_label: root.getAttribute('data-pe-msg-readonly-label') || '',
                readonly_intro: root.getAttribute('data-pe-msg-readonly-intro') || '',
                last_edited_label: root.getAttribute('data-pe-msg-last-edited') || '',
                last_seen_online_label: root.getAttribute('data-pe-msg-last-seen-online') || '',
                last_seen_unknown: root.getAttribute('data-pe-msg-last-seen-unknown') || '—',
                last_seen_just_now: root.getAttribute('data-pe-msg-last-seen-just-now') || 'только что',
                last_seen_min_sec_ago: root.getAttribute('data-pe-msg-last-seen-min-sec-ago') || ':minutes мин :seconds сек назад',
                last_seen_sec_ago: root.getAttribute('data-pe-msg-last-seen-sec-ago') || ':seconds сек назад',
                takeover: root.getAttribute('data-pe-msg-takeover') || '',
                takeover_confirm: root.getAttribute('data-pe-msg-takeover-confirm') || '',
                takeover_done_title: root.getAttribute('data-pe-msg-takeover-done-title') || '',
                takeover_done_body: root.getAttribute('data-pe-msg-takeover-done-body') || '',
                displaced_title: root.getAttribute('data-pe-msg-displaced-title') || '',
                displaced_body: root.getAttribute('data-pe-msg-displaced-body') || '',
                publication_freed_line1: root.getAttribute('data-pe-msg-publication-freed-line1') || '',
                publication_freed_as_of_label: root.getAttribute('data-pe-msg-publication-freed-as-of-label') || '',
                reload_to_edit_button: root.getAttribute('data-pe-msg-reload-to-edit') || '',
                exit_edit: root.getAttribute('data-pe-msg-exit-edit') || '',
            };
        }
        var raw = root.getAttribute('data-post-edit-lock-i18n') || '';
        if (!raw) {
            return {};
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function formatLocalTime(iso) {
        if (!iso) {
            return '';
        }
        var d = new Date(iso);
        if (isNaN(d.getTime())) {
            return '';
        }
        var h = String(d.getHours()).padStart(2, '0');
        var m = String(d.getMinutes()).padStart(2, '0');
        var s = String(d.getSeconds()).padStart(2, '0');
        return h + ':' + m + ':' + s;
    }

    /** d.m.Y H:i:s в локальной таймзоне (как на сервере для readonly «Сохранено») */
    function formatLastEditedAtLocal(iso) {
        if (!iso) {
            return '';
        }
        var d = new Date(iso);
        if (isNaN(d.getTime())) {
            return '';
        }
        var pad = function (n) {
            return String(n).padStart(2, '0');
        };
        return (
            pad(d.getDate()) +
            '.' +
            pad(d.getMonth() + 1) +
            '.' +
            d.getFullYear() +
            ' ' +
            pad(d.getHours()) +
            ':' +
            pad(d.getMinutes()) +
            ':' +
            pad(d.getSeconds())
        );
    }

    function formatMinutesAgoFromIso(iso, i18n) {
        if (!iso) {
            return (i18n && i18n.last_seen_unknown) ? i18n.last_seen_unknown : '—';
        }
        var d = new Date(iso);
        if (isNaN(d.getTime())) {
            return (i18n && i18n.last_seen_unknown) ? i18n.last_seen_unknown : '—';
        }
        var diffMs = Date.now() - d.getTime();
        var totalSeconds = Math.max(0, Math.floor(diffMs / 1000));
        if (totalSeconds <= 0) {
            return (i18n && i18n.last_seen_just_now) ? i18n.last_seen_just_now : 'только что';
        }
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;
        if (minutes <= 0) {
            var secTemplate = (i18n && i18n.last_seen_sec_ago) ? i18n.last_seen_sec_ago : ':seconds сек назад';
            return secTemplate.replace(':seconds', String(seconds));
        }
        var template = (i18n && i18n.last_seen_min_sec_ago) ? i18n.last_seen_min_sec_ago : ':minutes мин :seconds сек назад';
        return template
            .replace(':minutes', String(minutes))
            .replace(':seconds', String(seconds));
    }

    function renderCenter(lockRoot, data, i18n) {
        var els = document.querySelectorAll('[data-post-edit-lock-center]');
        if (!els.length) {
            return;
        }

        var html = '';
        if (data.can_edit) {
            /* Как у readonly / перехвата: две строки «Закреплено до» + «Сохранено» (heartbeat раньше затирал вторую). */
            var prevLastSaved = '';
            els.forEach(function (el) {
                var n = el.querySelector('[data-lock-last-saved="1"]');
                if (n && n.textContent && n.textContent.trim()) {
                    prevLastSaved = n.textContent.trim();
                }
            });

            var untilLine = '';
            var ul = (i18n.locked_until_label || '').trim();
            if (ul && data.lock_expires_at) {
                var exitLbl = (i18n.exit_edit || '').trim();
                untilLine =
                    '<div class="nova-post-edit-lock__line nova-post-edit-lock__line--locked-until">' +
                    '<span class="nova-post-edit-lock__label">' +
                    esc(ul) +
                    '</span>' +
                    '<span class="nova-post-edit-lock__value nova-post-edit-lock__time">' +
                    esc(formatLocalTime(data.lock_expires_at)) +
                    '</span>' +
                    (exitLbl
                        ? '<button type="button" class="nova-post-edit-lock__exit">' + esc(exitLbl) + '</button>'
                        : '') +
                    '</div>';
            }

            var last = '';
            var lastDisplay = '';
            if (data.last_edited_at) {
                lastDisplay = formatLastEditedAtLocal(data.last_edited_at);
            } else if (prevLastSaved) {
                lastDisplay = prevLastSaved;
            }
            if (lastDisplay) {
                last =
                    '<div class="nova-post-edit-lock__line">' +
                    '<span class="nova-post-edit-lock__label">' +
                    esc(i18n.last_edited_label || '') +
                    '</span>' +
                    '<span class="nova-post-edit-lock__value nova-post-edit-lock__time" data-lock-last-saved="1">' +
                    esc(lastDisplay) +
                    '</span></div>';
            }

            html =
                '<div class="nova-post-edit-lock nova-post-edit-lock--editor">' +
                untilLine +
                last +
                '</div>';
        } else if (data.publication_freed) {
            var l1 = (i18n.publication_freed_line1 || '').trim();
            var asOfLbl = (i18n.publication_freed_as_of_label || '').trim();
            var asOfTime = formatLastEditedAtLocal(new Date().toISOString());
            var line1Html = l1
                ? '<div class="nova-post-edit-lock__line"><span class="nova-post-edit-lock__value">' +
                  esc(l1) +
                  '</span></div>'
                : '';
            var line2Html =
                asOfLbl && asOfTime
                    ? '<div class="nova-post-edit-lock__line">' +
                      '<span class="nova-post-edit-lock__label">' +
                      esc(asOfLbl) +
                      '</span>' +
                      '<span class="nova-post-edit-lock__value nova-post-edit-lock__time">' +
                      esc(asOfTime) +
                      '</span></div>'
                    : '';
            html = '<div class="nova-post-edit-lock nova-post-edit-lock--freed">' + line1Html + line2Html + '</div>';
        } else {
            var ed = data.editor || {};
            var rl = (i18n.readonly_label || '').trim();
            var whoLine;
            if (rl) {
                whoLine =
                    '<div class="nova-post-edit-lock__line">' +
                    '<span class="nova-post-edit-lock__label">' +
                    esc(rl) +
                    '</span>' +
                    '<span class="nova-post-edit-lock__value">' +
                    esc(ed.name || '—') +
                    ' <span class="nova-post-edit-lock__muted">' +
                    esc(ed.email || '—') +
                    '</span></span></div>';
            } else {
                var introTpl = i18n.readonly_intro || '';
                var intro =
                    introTpl !== ''
                        ? introTpl
                              .replace(':name', esc(ed.name || '—'))
                              .replace(':email', esc(ed.email || '—'))
                        : esc(ed.name || '—') +
                          ' <span class="nova-post-edit-lock__muted">' +
                          esc(ed.email || '') +
                          '</span>';
                whoLine =
                    '<div class="nova-post-edit-lock__line">' +
                    '<span class="nova-post-edit-lock__value">' +
                    intro +
                    '</span></div>';
            }
            var onlineLine = '';
            var osl = (i18n.last_seen_online_label || '').trim();
            if (osl) {
                var onlineDisplay = '—';
                if (data.last_heartbeat_at) {
                    onlineDisplay = formatMinutesAgoFromIso(data.last_heartbeat_at, i18n);
                }
                onlineLine =
                    '<div class="nova-post-edit-lock__line">' +
                    '<span class="nova-post-edit-lock__label">' +
                    esc(osl) +
                    '</span>' +
                    '<span class="nova-post-edit-lock__value nova-post-edit-lock__time">' +
                    esc(onlineDisplay) +
                    '</span></div>';
            }
            html =
                '<div class="nova-post-edit-lock nova-post-edit-lock--readonly">' +
                whoLine +
                onlineLine +
                '</div>';
        }

        els.forEach(function (el) {
            el.innerHTML = html;
        });
    }

    function applyPostEditLockUiState(data) {
        var canEdit = !!data.can_edit;
        var freed = !!data.publication_freed;
        document.querySelectorAll('[data-post-edit-lock-enabled]').forEach(function (el) {
            el.setAttribute('data-nova-post-edit-disabled', canEdit ? '0' : '1');
        });
        syncPostEditLockFieldsFromBar();
        document.querySelectorAll('[data-post-edit-lock-panel="editor"]').forEach(function (el) {
            el.hidden = !canEdit;
        });
        document.querySelectorAll('[data-post-edit-lock-panel="readonly"]').forEach(function (el) {
            el.hidden = canEdit || freed;
        });
        document.querySelectorAll('[data-post-edit-lock-panel="freed"]').forEach(function (el) {
            el.hidden = !freed;
        });
    }

    function install(root) {
        if (!root) {
            return;
        }

        clearHeartbeat();

        var heartbeatUrl = root.getAttribute('data-heartbeat-url') || '';
        var postKey = root.getAttribute('data-post-key') || '';
        var i18n = parseI18n(root);
        var lockVersion = parseInt(root.getAttribute('data-lock-version') || '1', 10) || 1;

        function runHeartbeat() {
            if (!heartbeatUrl || !postKey) {
                return;
            }
            if (!isNovaResourceEditPath() || !root.isConnected) {
                clearHeartbeat();
                teardownPostEditLockFieldObserver();
                applyPostEditLockFields(false);
                return;
            }
            fetch(heartbeatUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    post_key: postKey,
                    client_version: lockVersion,
                }),
            })
                .then(function (r) {
                    if (!r.ok) {
                        return null;
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (!data || typeof data !== 'object') {
                        return;
                    }
                    lockVersion = data.lock_version != null ? parseInt(data.lock_version, 10) : lockVersion;
                    root.setAttribute('data-lock-version', String(lockVersion));

                    if (data.takeover && data.takeover.by) {
                        var by = data.takeover.by;
                        var body = (i18n.displaced_body || '')
                            .replace(':name', by.name || '')
                            .replace(':email', by.email || '');
                        window.alert((i18n.displaced_title || '') + '\n\n' + body);
                    }

                    applyPostEditLockUiState(data);
                    renderCenter(root, data, i18n);
                })
                .catch(function () {});
        }

        postEditLockHeartbeatRun = runHeartbeat;
        runHeartbeat();
        heartbeatTimer = setInterval(runHeartbeat, HEARTBEAT_MS);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible') {
            return;
        }
        if (typeof postEditLockHeartbeatRun === 'function') {
            if (!isNovaResourceEditPath()) {
                stopPostEditLockHeartbeatIfLeftEditPage();
                return;
            }
            postEditLockHeartbeatRun();
        }
    });

    document.addEventListener(
        'click',
        function (e) {
            var reloadBtn =
                e.target &&
                e.target.closest &&
                e.target.closest('.nova-post-edit-lock__reload-to-edit');
            if (reloadBtn) {
                e.preventDefault();
                window.location.reload();
                return;
            }

            var exitBtn =
                e.target && e.target.closest && e.target.closest('.nova-post-edit-lock__exit');
            if (exitBtn) {
                e.preventDefault();
                var lockRootExit = exitBtn.closest('[data-post-edit-lock]');
                if (!lockRootExit) {
                    return;
                }
                var releaseUrl = lockRootExit.getAttribute('data-release-url') || '';
                var postKeyExit = lockRootExit.getAttribute('data-post-key') || '';
                if (!releaseUrl || !postKeyExit) {
                    window.alert('Post edit lock: missing release URL or post key. Reload the page.');
                    return;
                }
                exitBtn.disabled = true;
                fetch(releaseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ post_key: postKeyExit }),
                })
                    .then(function (r) {
                        if (r.status === 419) {
                            window.alert('Session expired. Reload the page and try again.');
                            exitBtn.disabled = false;
                            return;
                        }
                        return r.json().then(function (body) {
                            if (!r.ok) {
                                window.alert(
                                    (body && body.message) ||
                                        'Could not release editing lock (HTTP ' + r.status + ').'
                                );
                                exitBtn.disabled = false;
                                return;
                            }
                            if (body && body.redirect) {
                                window.location.href = body.redirect;
                                return;
                            }
                            exitBtn.disabled = false;
                        });
                    })
                    .catch(function () {
                        exitBtn.disabled = false;
                        window.alert('Network error while releasing the edit lock.');
                    });
                return;
            }

            var btn =
                e.target &&
                e.target.closest &&
                e.target.closest('.nova-post-edit-lock__takeover, .nova-post-edit-lock-takeover');
            if (!btn) {
                return;
            }

            var lockRoot = btn.closest('[data-post-edit-lock]');
            if (!lockRoot) {
                return;
            }
            e.preventDefault();
            var takeoverUrl = lockRoot.getAttribute('data-takeover-url') || '';
            var postKey = lockRoot.getAttribute('data-post-key') || '';
            var i18n = parseI18n(lockRoot);
            if (!takeoverUrl || !postKey) {
                window.alert('Post edit lock: missing takeover URL or post key. Reload the page.');
                return;
            }
            if (!confirm(i18n.takeover_confirm || '')) {
                return;
            }
            fetch(takeoverUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ post_key: postKey }),
            })
                .then(function (r) {
                    if (r.status === 419) {
                        window.alert('Session expired. Reload the page and try again.');
                        return;
                    }
                    if (!r.ok) {
                        window.alert('Could not take over editing (HTTP ' + r.status + ').');
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    window.alert('Network error while taking over editing.');
                });
        },
        true
    );

    function scan() {
        var root = document.querySelector('[data-post-edit-lock]');
        if (!root) {
            clearHeartbeat();
            teardownPostEditLockFieldObserver();
            applyPostEditLockFields(false);
            return;
        }
        install(root);
        syncPostEditLockFieldsFromBar();
    }

    /** Nova renders Heading (FormActionBar) via Vue v-html after the page shell loads — scan() often runs too early. */
    var pollTimer = null;
    var pollUntil = 0;
    var novaResourceLoadedHooked = false;

    function scheduleScan() {
        scan();
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        pollUntil = Date.now() + 20000;
        pollTimer = setInterval(function () {
            if (Date.now() > pollUntil) {
                clearInterval(pollTimer);
                pollTimer = null;
                return;
            }
            var root = document.querySelector('[data-post-edit-lock]');
            if (!root) {
                return;
            }
            if (heartbeatTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
                return;
            }
            scan();
        }, 200);
    }

    function hookNovaResourceLoaded() {
        if (novaResourceLoadedHooked) {
            return true;
        }
        if (!window.Nova || typeof window.Nova.$on !== 'function') {
            return false;
        }
        novaResourceLoadedHooked = true;
        window.Nova.$on('resource-loaded', function (payload) {
            [0, 50, 150, 400, 1000].forEach(function (ms) {
                setTimeout(stopPostEditLockHeartbeatIfLeftEditPage, ms);
            });
            if (payload && payload.mode === 'update') {
                [0, 50, 150, 400, 1000].forEach(function (ms) {
                    setTimeout(scheduleScan, ms);
                });
            }
        });
        return true;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleScan);
    } else {
        scheduleScan();
    }
    document.addEventListener('inertia:finish', function () {
        scheduleScan();
        [0, 50, 150, 300].forEach(function (ms) {
            setTimeout(stopPostEditLockHeartbeatIfLeftEditPage, ms);
        });
    });
    window.addEventListener('popstate', function () {
        setTimeout(stopPostEditLockHeartbeatIfLeftEditPage, 0);
    });

    (function tryHookNova() {
        if (hookNovaResourceLoaded()) {
            return;
        }
        setTimeout(tryHookNova, 100);
    }());
}());