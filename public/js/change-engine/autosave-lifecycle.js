(function () {
    if (window.NovaAutosaveLifecycleV2) return;

    var config = {
        enabled: false,
        delayMs: 3000,
        draftOnly: true,
    };

    var manager = null;
    var unsubscribe = null;
    var saveTimer = null;
    var inProgress = false;
    var releaseTimer = null;
    var countdownInterval = null;
    var dueAt = 0;
    var saveStartedWithLastSavedAt = null;
    var lastSeenChangedAt = null;
    var manualSaveInProgress = false;
    var manualIntentUntil = 0;

    function getAllAutosaveButtons() {
        return Array.from(document.querySelectorAll('button[data-saving-label]'));
    }

    function getDefaultButtonLabel(button) {
        if (!button || !button.dataset) return '';
        var fromAttr = button.dataset.autosaveSaveDefaultLabel;
        if (fromAttr !== undefined && fromAttr !== '') return fromAttr;
        var labelNode = button.querySelector && button.querySelector('[data-save-button-label=\"1\"]');
        if (labelNode) return labelNode.textContent || '';
        return button.textContent || '';
    }

    function setButtonLabel(button, label) {
        if (!button) return;
        var labelNode = button.querySelector && button.querySelector('[data-save-button-label=\"1\"]');
        if (labelNode) {
            labelNode.textContent = String(label);
            return;
        }
        button.textContent = String(label);
    }

    function restoreButtonLabels() {
        getAllAutosaveButtons().forEach(function (btn) {
            var def = getDefaultButtonLabel(btn);
            if (def) setButtonLabel(btn, def);
        });
    }

    function showDirtyFallbackDigit(forceDigit) {
        var digit = Number(forceDigit);
        if (!Number.isFinite(digit) || digit <= 0) digit = 3;
        var digitText = String(Math.ceil(digit));
        getAllAutosaveButtons().forEach(function (btn) {
            var def = getDefaultButtonLabel(btn);
            if (!def) return;
            setButtonLabel(btn, def + ' ' + digitText);
        });
    }

    function clearCountdown() {
        dueAt = 0;
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    function stopCountdownKeepCurrentLabel() {
        dueAt = 0;
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    function renderCountdown() {
        if (!dueAt) return;
        var remainingMs = dueAt - Date.now();
        if (remainingMs <= 0) {
            clearCountdown();
            return;
        }
        var remainingSeconds = Math.ceil(remainingMs / 1000);
        if (!Number.isFinite(remainingSeconds) || remainingSeconds <= 0) {
            remainingSeconds = 3;
        }
        getAllAutosaveButtons().forEach(function (btn) {
            var def = getDefaultButtonLabel(btn);
            if (!def) return;
            setButtonLabel(btn, def + ' ' + String(remainingSeconds));
        });
    }

    function startCountdown(nextDueAt) {
        dueAt = nextDueAt;
        renderCountdown();
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
        countdownInterval = setInterval(renderCountdown, 250);
    }

    function findStatusSelect() {
        return Array.from(document.querySelectorAll('select')).find(function (el) {
            var values = Array.from(el.options || []).map(function (o) { return o.value; });
            return values.indexOf('draft') !== -1 && values.indexOf('published') !== -1;
        }) || null;
    }

    function isDraftSelected() {
        var s = findStatusSelect();
        return !!(s && s.value === 'draft');
    }

    function findAutosaveButton() {
        return document.querySelector('button[data-saving-label]');
    }

    function clearTimer() {
        if (saveTimer) {
            clearTimeout(saveTimer);
            saveTimer = null;
        }
        clearCountdown();
    }

    function clearSaveTimerOnly() {
        if (saveTimer) {
            clearTimeout(saveTimer);
            saveTimer = null;
        }
    }

    function clearReleaseTimer() {
        if (releaseTimer) {
            clearTimeout(releaseTimer);
            releaseTimer = null;
        }
    }

    function releaseInProgress() {
        inProgress = false;
        manualSaveInProgress = false;
        saveStartedWithLastSavedAt = null;
        lastSeenChangedAt = null;
        clearReleaseTimer();
        if (manager && typeof manager.setInFlightSave === 'function') {
            manager.setInFlightSave(false);
        }
    }

    function setManualIntentWindow(ms) {
        var ttl = Number(ms);
        if (!Number.isFinite(ttl) || ttl <= 0) ttl = 1200;
        manualIntentUntil = Date.now() + ttl;
        clearSaveTimerOnly();
        stopCountdownKeepCurrentLabel();
    }

    function hasManualIntentNow() {
        return manualIntentUntil > 0 && Date.now() < manualIntentUntil;
    }

    function isAutosaveButtonTarget(target) {
        return !!(target && target.closest && target.closest('button[data-saving-label]'));
    }

    function onManualIntentPointerDown(ev) {
        if (!config.enabled) return;
        if (isAutosaveButtonTarget(ev && ev.target)) {
            setManualIntentWindow(1500);
        }
    }

    function onManualIntentKeyDown(ev) {
        if (!config.enabled) return;
        if (!isAutosaveButtonTarget(ev && ev.target)) return;
        var key = ev && ev.key;
        if (key === 'Enter' || key === ' ' || key === 'Spacebar') {
            setManualIntentWindow(1500);
        }
    }

    function onManualSaveStart() {
        clearTimer();
        stopCountdownKeepCurrentLabel();
        manualSaveInProgress = true;
        inProgress = true;
        manualIntentUntil = 0;

        var snapshot = manager && typeof manager.getState === 'function' ? manager.getState() : null;
        saveStartedWithLastSavedAt = snapshot && snapshot.lastSavedAt ? snapshot.lastSavedAt : null;

        if (manager && typeof manager.setInFlightSave === 'function') {
            manager.setInFlightSave(true);
        }
    }

    function triggerSave() {
        if (!config.enabled || inProgress || !manager) return;
        if (hasManualIntentNow()) return;
        clearSaveTimerOnly();

        var state = manager.getState();
        if (!state || !state.isDirty) return;
        if (config.draftOnly && !isDraftSelected()) return;

        var btn = findAutosaveButton();
        if (!btn) return;

        inProgress = true;
        saveStartedWithLastSavedAt = state.lastSavedAt || null;
        if (typeof manager.setInFlightSave === 'function') {
            manager.setInFlightSave(true);
        }

        if (window.NovaCustomSave && typeof window.NovaCustomSave.saveWithoutReload === 'function') {
            window.NovaCustomSave.saveWithoutReload(btn, { fromAutosave: true });
        } else {
            btn.click();
        }

        // Safety unlock in case no network callback was observed.
        releaseTimer = setTimeout(releaseInProgress, 45000);
    }

    function scheduleByState(state) {
        if (!config.enabled || !state || !state.ready) {
            clearTimer();
            restoreButtonLabels();
            return;
        }
        if (hasManualIntentNow()) {
            clearSaveTimerOnly();
            stopCountdownKeepCurrentLabel();
            return;
        }
        if (manualSaveInProgress) {
            clearSaveTimerOnly();
            stopCountdownKeepCurrentLabel();
            return;
        }
        if (!state.isDirty) {
            clearTimer();
            lastSeenChangedAt = null;
            restoreButtonLabels();
            return;
        }
        if (state.inFlightSave) {
            clearSaveTimerOnly();
            stopCountdownKeepCurrentLabel();
            return;
        }
        if (config.draftOnly && !isDraftSelected()) {
            clearSaveTimerOnly();
            stopCountdownKeepCurrentLabel();
            restoreButtonLabels();
            return;
        }

        var changedAt = state.lastChangedAt || Date.now();
        var isNewChange = changedAt !== lastSeenChangedAt;
        if (!saveTimer || isNewChange) {
            // Keep countdown visible while user types; restart only save timer.
            clearSaveTimerOnly();
            lastSeenChangedAt = changedAt;

            // Always start from full 3-second window for a new change.
            var dueIn = config.delayMs;
            startCountdown(Date.now() + dueIn);
            saveTimer = setTimeout(function () {
                saveTimer = null;
                triggerSave();
            }, dueIn);
        }
    }

    function onState(state) {
        if (!state) return;
        if (inProgress && saveStartedWithLastSavedAt !== null && state.lastSavedAt !== null && state.lastSavedAt !== saveStartedWithLastSavedAt) {
            releaseInProgress();
        }
        if (inProgress && !state.inFlightSave) {
            releaseInProgress();
        }
        scheduleByState(state);
    }

    function init(changesManager, options) {
        manager = changesManager || manager;
        if (!manager || typeof manager.subscribe !== 'function') return;

        config = Object.assign({}, config, options || {});

        if (unsubscribe) {
            unsubscribe();
            unsubscribe = null;
        }
        unsubscribe = manager.subscribe(onState);

        window.addEventListener('nova:manual-save:start', onManualSaveStart, true);
        document.addEventListener('pointerdown', onManualIntentPointerDown, true);
        document.addEventListener('keydown', onManualIntentKeyDown, true);
    }

    function stop() {
        clearTimer();
        releaseInProgress();
        restoreButtonLabels();
        window.removeEventListener('nova:manual-save:start', onManualSaveStart, true);
        document.removeEventListener('pointerdown', onManualIntentPointerDown, true);
        document.removeEventListener('keydown', onManualIntentKeyDown, true);
        if (unsubscribe) {
            unsubscribe();
            unsubscribe = null;
        }
    }

    window.NovaAutosaveLifecycleV2 = {
        init: init,
        stop: stop,
        triggerNow: triggerSave,
    };
}());
