// Platform module: browser notifications helper.
//   window.pkNotify(title, body[, options])  → shows a Notification only if permission is already granted
//                                             (and the user hasn't switched browser alerts off). Otherwise a no-op.
//   window.pkNotify.requestPermission()      → asks the browser; call ONLY from a user click/change handler.
//   window.pkNotify.enabled()                → true when a notification would be shown.
// Duplicate protection: pass options.tag (e.g. 'ticket-12-called'); the same tag is shown once per page session.
(() => {
    const supported = 'Notification' in window;
    const shown = new Set();

    function enabled() {
        return supported && Notification.permission === 'granted';
    }

    function pkNotify(title, body, options = {}) {
        if (!enabled()) return null;
        const tag = options.tag || null;
        if (tag && shown.has(tag)) return null;
        try {
            const n = new Notification(String(title || 'प्रतीक्षा'), {
                body: String(body || ''),
                tag: tag || undefined,
                icon: options.icon || undefined,
                silent: !!options.silent,
            });
            if (tag) shown.add(tag);
            n.onclick = () => { window.focus(); if (options.url && options.url.startsWith('/')) window.location.href = options.url; n.close(); };
            return n;
        } catch (e) {
            return null; // e.g. mobile browsers that require a service worker
        }
    }

    pkNotify.enabled = enabled;
    pkNotify.requestPermission = async () => {
        if (!supported) return 'unsupported';
        if (Notification.permission !== 'default') return Notification.permission;
        try { return await Notification.requestPermission(); } catch (e) { return 'denied'; }
    };

    window.pkNotify = pkNotify;
})();
