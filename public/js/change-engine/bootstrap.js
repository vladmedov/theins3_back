(function () {
    if (window.NovaChangeEngineV2Bootstrap) return;
    window.NovaChangeEngineV2Bootstrap = true;

    var defaults = {
        enabled: true,
        mountBanner: true,
        enableAutosave: true,
        autosaveDelayMs: 3000,
        draftOnly: true,
        debounceMs: 150,
    };

    var cfg = Object.assign({}, defaults, window.NovaChangeEngineV2Config || {});

    function getEditResourceInfoFromPath() {
        var m = window.location.pathname.match(/\/resources\/([^/]+)\/([^/]+)\/edit\/?$/);
        if (!m) return null;
        return {
            key: m[1],
            id: m[2],
        };
    }

    function isPostEditPage() {
        var info = getEditResourceInfoFromPath();
        if (!info || !info.key || !info.id || info.id === 'new') return false;
        var key = info.key;
        return key === 'posts' || key.indexOf('post-') === 0;
    }

    if (!cfg.enabled) {
        return;
    }

    var manager = window.NovaChangesManagerV2;
    if (!manager) return;

    manager.init({ debounceMs: cfg.debounceMs });

    if (cfg.mountBanner && isPostEditPage() && window.NovaUnsavedBannerViewV2) {
        window.NovaUnsavedBannerViewV2.init(manager);
    }

    if (cfg.enableAutosave && window.NovaAutosaveLifecycleV2) {
        window.NovaAutosaveLifecycleV2.init(manager, {
            enabled: true,
            delayMs: cfg.autosaveDelayMs,
            draftOnly: cfg.draftOnly,
        });
    }

    window.NovaChangeEngineV2 = {
        manager: manager,
        banner: window.NovaUnsavedBannerViewV2 || null,
        autosave: window.NovaAutosaveLifecycleV2 || null,
        config: cfg,
    };
}());
