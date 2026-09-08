<?php
/** Site-wide floating AI support chatbot — included once from includes/footer.php. */
function render_chatbot_widget(): void
{
    $csrf = csrf_token();
    $user = current_user();
    $prefillName = $user ? trim($user['first_name'] . ' ' . $user['last_name']) : '';
    $prefillEmail = $user['email'] ?? '';

    $consentRaw = json_decode($_COOKIE['rvz_cookie_consent'] ?? '', true);
    $aiConsentGiven = is_array($consentRaw) && !empty($consentRaw['ai']);
    ?>
    <button type="button" class="rvz-chat-fab" id="rvzChatFab" aria-label="Open support chat" title="Support chat">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 4h16v12H7l-3 3V4z" stroke="white" stroke-width="1.8" stroke-linejoin="round"/>
        </svg>
    </button>

    <div class="rvz-chat-panel" id="rvzChatPanel" role="dialog" aria-label="Support chat">
        <div class="rvz-chat-header">
            <strong>RVZ Support</strong>
            <button type="button" id="rvzChatClose" aria-label="Close chat">&times;</button>
        </div>
        <div class="rvz-chat-messages" id="rvzChatMessages">
            <div class="rvz-chat-msg system-note">Hi! Ask me anything about applying, posting jobs, billing, or your account.</div>
            <?php if (!$aiConsentGiven): ?>
                <div class="rvz-chat-msg system-note small text-muted">You're using our built-in assistant. Enable "AI Assistance" in cookie preferences (bottom of page) for smarter, AI-powered replies.</div>
            <?php endif; ?>
        </div>
        <div class="rvz-chat-escalate">
            <a href="#" id="rvzChatEscalateLink">Not what you needed? Escalate to Support &rarr;</a>
        </div>
        <div class="rvz-chat-footer">
            <form id="rvzChatForm" class="d-flex gap-2">
                <input type="text" id="rvzChatInput" class="form-control form-control-sm" placeholder="Type a message..." maxlength="1000" autocomplete="off">
                <button type="submit" class="btn btn-primary btn-sm">Send</button>
            </form>
        </div>
    </div>

    <div class="rvz-chat-panel" id="rvzEscalatePanel">
        <div class="rvz-chat-header">
            <strong>Escalate to Support</strong>
            <button type="button" id="rvzEscalateClose" aria-label="Close">&times;</button>
        </div>
        <div class="p-3" style="overflow-y:auto;">
            <p class="small text-muted">We'll send your chat so far, plus your details, to our support team.</p>
            <form id="rvzEscalateForm">
                <div class="mb-2">
                    <label class="form-label small">Your name</label>
                    <input type="text" name="name" id="rvzEscalateName" class="form-control form-control-sm" value="<?= h($prefillName) ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Your email</label>
                    <input type="email" name="email" id="rvzEscalateEmail" class="form-control form-control-sm" value="<?= h($prefillEmail) ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small">What's the issue?</label>
                    <input type="text" name="subject" id="rvzEscalateSubject" class="form-control form-control-sm" placeholder="Short summary">
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100">Send to Support</button>
            </form>
            <div id="rvzEscalateDone" class="alert alert-success small mt-2" style="display:none;"></div>
        </div>
    </div>

    <script>
    (function () {
        var csrfToken = <?= json_encode($csrf) ?>;
        var chatUrl = <?= json_encode(base_url('ajax/chatbot.php')) ?>;
        var escalateUrl = <?= json_encode(base_url('ajax/escalate_ticket.php')) ?>;
        var STORAGE_KEY = 'rvz_chat_history';

        var fab = document.getElementById('rvzChatFab');
        var panel = document.getElementById('rvzChatPanel');
        var closeBtn = document.getElementById('rvzChatClose');
        var messagesEl = document.getElementById('rvzChatMessages');
        var form = document.getElementById('rvzChatForm');
        var input = document.getElementById('rvzChatInput');
        var escalateLink = document.getElementById('rvzChatEscalateLink');
        var escalatePanel = document.getElementById('rvzEscalatePanel');
        var escalateClose = document.getElementById('rvzEscalateClose');
        var escalateForm = document.getElementById('rvzEscalateForm');
        var escalateDone = document.getElementById('rvzEscalateDone');

        function loadHistory() {
            try { return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) { return []; }
        }
        function saveHistory(h) {
            try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(h)); } catch (e) {}
        }
        var history = loadHistory();

        function renderMessage(role, content) {
            var div = document.createElement('div');
            div.className = 'rvz-chat-msg ' + (role === 'user' ? 'user' : 'assistant');
            div.textContent = content;
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
        history.forEach(function (m) { renderMessage(m.role, m.content); });

        fab.addEventListener('click', function () {
            panel.classList.toggle('is-open');
            if (panel.classList.contains('is-open')) input.focus();
        });
        closeBtn.addEventListener('click', function () { panel.classList.remove('is-open'); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = input.value.trim();
            if (!msg) return;
            renderMessage('user', msg);
            history.push({ role: 'user', content: msg });
            saveHistory(history);
            input.value = '';
            input.disabled = true;

            var body = new URLSearchParams();
            body.set('message', msg);
            body.set('history', JSON.stringify(history.slice(0, -1)));
            body.set('csrf_token', csrfToken);

            fetch(chatUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    input.disabled = false;
                    input.focus();
                    if (!data.ok) {
                        renderMessage('assistant', "Sorry, I'm having trouble right now (" + (data.error || 'unknown error') + "). Try Escalate to Support below.");
                        return;
                    }
                    renderMessage('assistant', data.reply);
                    history.push({ role: 'assistant', content: data.reply });
                    saveHistory(history);
                })
                .catch(function () {
                    input.disabled = false;
                    renderMessage('assistant', "Sorry, something went wrong reaching support chat. Try Escalate to Support below.");
                });
        });

        escalateLink.addEventListener('click', function (e) {
            e.preventDefault();
            panel.classList.remove('is-open');
            escalatePanel.classList.add('is-open');
        });
        escalateClose.addEventListener('click', function () { escalatePanel.classList.remove('is-open'); });

        escalateForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var body = new URLSearchParams();
            body.set('name', document.getElementById('rvzEscalateName').value);
            body.set('email', document.getElementById('rvzEscalateEmail').value);
            body.set('subject', document.getElementById('rvzEscalateSubject').value);
            body.set('transcript', JSON.stringify(history));
            body.set('csrf_token', csrfToken);

            fetch(escalateUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) { alert(data.error || 'Could not send — please try again.'); return; }
                    escalateForm.style.display = 'none';
                    escalateDone.style.display = 'block';
                    escalateDone.textContent = 'Thanks — ticket #' + data.ticket_id + ' has been sent to our team. We\'ll follow up by email.';
                    history = [];
                    saveHistory(history);
                });
        });
    })();
    </script>
    <?php
}
