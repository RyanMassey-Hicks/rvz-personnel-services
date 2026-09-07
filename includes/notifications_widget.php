<?php
/** Notification bell — rendered in the navbar for any logged-in user. Server-rendered list, AJAX only for marking read. */
function render_notification_bell(array $user): void
{
    $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15');
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();
    $unreadCount = unread_notification_count((int) $user['id']);
    $csrf = csrf_token();
    ?>
    <div class="nav-item dropdown rvz-notif-dropdown">
        <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger rvz-notif-badge" id="rvzNotifBadge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-end rvz-notif-panel" id="rvzNotifPanel">
            <div class="d-flex justify-content-between align-items-center px-2 pb-2 border-bottom">
                <strong class="small">Notifications</strong>
                <button type="button" class="btn btn-link btn-sm p-0" id="rvzMarkAllRead">Mark all read</button>
            </div>
            <div class="px-2 py-2 border-bottom small" id="rvzEnableNotifRow" style="display:none;">
                <button type="button" class="btn btn-outline-primary btn-sm w-100" id="rvzEnableNotif">🔔 Enable browser notifications</button>
            </div>
            <div id="rvzNotifList">
            <?php if (!$notifications): ?>
                <p class="text-muted small px-2 py-3 mb-0 text-center" id="rvzNotifEmpty">No notifications yet.</p>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <a href="<?= h($n['link'] ?: '#') ?>" class="dropdown-item rvz-notif-item<?= $n['is_read'] ? '' : ' is-unread' ?>" data-notif-id="<?= (int) $n['id'] ?>">
                        <div class="fw-semibold small"><?= h($n['title']) ?></div>
                        <?php if ($n['body']): ?><div class="small text-muted"><?= h($n['body']) ?></div><?php endif; ?>
                        <div class="small text-muted"><?= h(date('M j, g:ia', strtotime($n['created_at']))) ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var csrfToken = <?= json_encode($csrf) ?>;
        var notifUrl = <?= json_encode(base_url('ajax/notifications.php')) ?>;
        var pushSubscribeUrl = <?= json_encode(base_url('ajax/push_subscribe.php')) ?>;
        var vapidPublicKey = <?= json_encode(defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '') ?>;
        var notifIcon = <?= json_encode(base_url('assets/img/icon-192.png')) ?>;
        var badge = document.getElementById('rvzNotifBadge');
        var list = document.getElementById('rvzNotifList');
        var emptyMsg = document.getElementById('rvzNotifEmpty');
        var enableRow = document.getElementById('rvzEnableNotifRow');
        var enableBtn = document.getElementById('rvzEnableNotif');
        var lastSeenId = <?= (int) ($notifications[0]['id'] ?? 0) ?>;
        var POLL_INTERVAL_MS = 25000;
        // True once this browser has a real Web Push subscription registered —
        // when it does, the service worker's own "push" handler shows the OS
        // notification (works even with every tab closed), so the poll loop
        // below skips firing its own new Notification() to avoid a duplicate.
        var hasPushSubscription = false;

        function updateBadge(count) {
            if (!badge) return;
            if (count > 0) { badge.style.display = ''; badge.textContent = count > 9 ? '9+' : count; }
            else { badge.style.display = 'none'; }
        }

        function bindItem(item) {
            item.addEventListener('click', function () {
                if (!item.classList.contains('is-unread')) return;
                item.classList.remove('is-unread');
                fetch(notifUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_read&id=' + item.dataset.notifId + '&csrf_token=' + encodeURIComponent(csrfToken),
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.ok) updateBadge(data.unread);
                });
            });
        }

        document.querySelectorAll('.rvz-notif-item').forEach(bindItem);

        var markAll = document.getElementById('rvzMarkAllRead');
        if (markAll) {
            markAll.addEventListener('click', function (e) {
                e.preventDefault();
                fetch(notifUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_all_read&csrf_token=' + encodeURIComponent(csrfToken),
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.ok) {
                        document.querySelectorAll('.rvz-notif-item.is-unread').forEach(function (el) { el.classList.remove('is-unread'); });
                        updateBadge(0);
                    }
                });
            });
        }

        // ---- Native browser notification pop-ups. If a real Web Push
        // subscription is active, the service worker's own "push" handler
        // already shows these (and works even with the tab/browser closed),
        // so this poll-driven path only runs as a fallback for a tab that's
        // open but hasn't got a push subscription yet. ----
        function showBrowserNotification(n) {
            if (hasPushSubscription) return;
            if (!('Notification' in window) || Notification.permission !== 'granted') return;
            try {
                var browserNotif = new Notification(n.title, {
                    body: n.body || '',
                    icon: notifIcon,
                    badge: notifIcon,
                    tag: 'rvz-notif-' + n.id,
                });
                browserNotif.onclick = function () {
                    window.focus();
                    if (n.link) window.location.href = n.link;
                    browserNotif.close();
                };
            } catch (e) { /* some browsers throw if the tab is backgrounded on certain OSes — badge/dropdown still updates regardless */ }
        }

        function prependToList(n) {
            if (emptyMsg) { emptyMsg.remove(); emptyMsg = null; }
            var a = document.createElement('a');
            a.href = n.link || '#';
            a.className = 'dropdown-item rvz-notif-item is-unread';
            a.dataset.notifId = n.id;
            var created = new Date(n.created_at.replace(' ', 'T'));
            a.innerHTML = '<div class="fw-semibold small"></div>' +
                (n.body ? '<div class="small text-muted"></div>' : '') +
                '<div class="small text-muted">' + created.toLocaleString() + '</div>';
            a.querySelector('.fw-semibold').textContent = n.title;
            if (n.body) a.querySelector('.small.text-muted').textContent = n.body;
            list.insertBefore(a, list.firstChild);
            bindItem(a);
        }

        function poll() {
            // Deliberately keeps polling on a hidden/backgrounded tab too —
            // that's exactly when a native browser notification pop-up is
            // most useful (alerting a user who isn't currently looking).
            fetch(notifUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=poll&since_id=' + lastSeenId + '&csrf_token=' + encodeURIComponent(csrfToken),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.ok) return;
                updateBadge(data.unread);
                (data.new || []).forEach(function (n) {
                    prependToList(n);
                    showBrowserNotification(n);
                });
                if (data.latest_id) lastSeenId = data.latest_id;
            }).catch(function () { /* a missed poll just tries again next tick */ });
        }

        setInterval(poll, POLL_INTERVAL_MS);

        // ---- Web Push: converts the VAPID public key (base64url, as stored
        // in config.php) into the Uint8Array pushManager.subscribe() needs. ----
        function urlBase64ToUint8Array(base64String) {
            var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            var rawData = window.atob(base64);
            var outputArray = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
            return outputArray;
        }

        function sendSubscriptionToServer(subscription) {
            return fetch(pushSubscribeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'subscription=' + encodeURIComponent(JSON.stringify(subscription)) + '&csrf_token=' + encodeURIComponent(csrfToken),
            }).then(function (r) { return r.json(); });
        }

        function subscribeToPush() {
            if (!vapidPublicKey || !('serviceWorker' in navigator) || !('PushManager' in window)) return;
            navigator.serviceWorker.ready.then(function (registration) {
                return registration.pushManager.getSubscription().then(function (existing) {
                    if (existing) return existing;
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
                    });
                });
            }).then(function (subscription) {
                if (!subscription) return;
                hasPushSubscription = true;
                sendSubscriptionToServer(subscription.toJSON());
            }).catch(function (e) {
                // Push isn't available on every platform (e.g. iOS Safari
                // before 16.4, or a browser with push blocked at the OS
                // level) — the poll-driven fallback above still covers an
                // open tab either way, so this is a quiet no-op.
            });
        }

        // Already subscribed from an earlier visit? Pick that up silently so
        // the poll loop knows not to double-fire its own notifications.
        if ('Notification' in window && Notification.permission === 'granted' && 'serviceWorker' in navigator && 'PushManager' in window) {
            navigator.serviceWorker.ready.then(function (registration) {
                return registration.pushManager.getSubscription();
            }).then(function (existing) {
                if (existing) hasPushSubscription = true;
            }).catch(function () {});
        }

        // ---- Opt-in prompt for browser notification permission + push
        // subscription (only ever triggered by a real click, never
        // auto-requested on page load). ----
        if ('Notification' in window) {
            if (Notification.permission === 'default' && enableRow) {
                enableRow.style.display = '';
            }
            if (enableBtn) {
                enableBtn.addEventListener('click', function () {
                    Notification.requestPermission().then(function (perm) {
                        if (perm !== 'default') enableRow.style.display = 'none';
                        if (perm === 'granted') subscribeToPush();
                    });
                });
            }
        }
    })();
    </script>
    <?php
}
