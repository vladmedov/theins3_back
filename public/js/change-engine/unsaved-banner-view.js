(function () {
    if (window.NovaUnsavedBannerViewV2) return;

    var bannerEl = null;
    var unsubscribe = null;

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

    function ensureBanner() {
        if (bannerEl && bannerEl.parentNode) return;
        bannerEl = document.createElement('div');
        bannerEl.id = 'nova-unsaved-changes-banner-v2';
        bannerEl.className = 'nova-unsaved-changes-banner nova-unsaved-changes-banner--v2';
        bannerEl.setAttribute('role', 'alert');
        bannerEl.hidden = true;
        bannerEl.setAttribute('aria-hidden', 'true');

        var lang = (document.documentElement.lang || '').toLowerCase();
        bannerEl.textContent = lang.indexOf('ru') === 0
            ? 'Есть несохранённые изменения'
            : 'You have unsaved changes';

        document.body.insertBefore(bannerEl, document.body.firstChild);
    }

    function setVisible(visible) {
        ensureBanner();
        if (!bannerEl) return;
        var shouldShow = !!visible && isPostEditPage();
        bannerEl.hidden = !shouldShow;
        bannerEl.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
    }

    function init(manager) {
        if (!manager || typeof manager.subscribe !== 'function') return;
        if (unsubscribe) unsubscribe();
        ensureBanner();
        unsubscribe = manager.subscribe(function (state) {
            setVisible(!!(state && state.isDirty));
        });
    }

    function destroy() {
        if (unsubscribe) {
            unsubscribe();
            unsubscribe = null;
        }
        setVisible(false);
    }

    window.NovaUnsavedBannerViewV2 = {
        init: init,
        destroy: destroy,
    };
}());
