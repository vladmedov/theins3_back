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

document.addEventListener('DOMContentLoaded', function() {
    // Ищем блок с кнопками
    const buttonContainer = document.querySelector('.flex.flex-col.md\\:flex-row.md\\:items-center.justify-center.md\\:justify-end.space-y-2.md\\:space-y-0.md\\:space-x-3');
    
    if (buttonContainer) {
        // Клонируем кнопки
        const fixedButtons = buttonContainer.cloneNode(true);
        fixedButtons.classList.add('fixed-action-buttons');
        
        // Добавляем их в конец body
        document.body.appendChild(fixedButtons);
    }
});

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