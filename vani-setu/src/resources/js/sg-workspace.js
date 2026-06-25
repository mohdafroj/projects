const TOKEN_KEY = 'vani_setu_api_token';

const state = {
    token: window.localStorage.getItem(TOKEN_KEY) || '',
    me: null,
    roles: [],
    tray: [],
    activeWindow: null,
    history: null,
    error: '',
    notice: '',
    authMode: 'checking',
};

function initSgWorkspace(root) {
    render(root);
    boot(root);
}

async function boot(root) {
    if (! state.token) {
        state.authMode = 'login';
        render(root);
        return;
    }

    try {
        await loadMe();
        if (! isSg()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        await loadTray();
        const initialWindow = root.dataset.initialWindow;
        if (initialWindow) {
            await openWindow(initialWindow);
        } else if (state.tray[0]) {
            await openWindow(state.tray[0].id);
        }
    } catch (error) {
        if (error.status === 401) {
            clearSession();
        } else {
            state.error = error.message || 'Workspace could not be loaded.';
        }
    }

    render(root);
}

function isSg() {
    return state.roles.includes('sg');
}

async function api(path, options = {}) {
    const headers = {
        Accept: 'application/json',
        ...(options.headers || {}),
    };

    if (state.token) {
        headers.Authorization = `Bearer ${state.token}`;
    }

    if (options.body) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(path, {
        ...options,
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
    });

    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : null;

    if (! response.ok) {
        const error = new Error(payload?.message || response.statusText || 'Request failed.');
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

async function login(root, form) {
    state.error = '';
    state.notice = '';

    try {
        const payload = await api('/api/auth/login', {
            method: 'POST',
            body: {
                employee_id: form.querySelector('[name="employee_id"]').value.trim(),
                password: form.querySelector('[name="password"]').value,
            },
        });

        state.token = payload.token;
        state.me = payload.user;
        state.roles = payload.roles || [];
        window.localStorage.setItem(TOKEN_KEY, state.token);

        if (! isSg()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        window.history.replaceState({}, '', '/app/sg');
        await loadTray();
        if (state.tray[0]) {
            await openWindow(state.tray[0].id);
        }
    } catch (error) {
        state.error = error.message || 'Login failed.';
    }

    render(root);
}

async function logout(root) {
    try {
        if (state.token) {
            await api('/api/auth/logout', { method: 'POST' });
        }
    } catch (error) {
        // Local logout still applies.
    }

    clearSession();
    window.history.replaceState({}, '', '/app/sg');
    render(root);
}

function clearSession() {
    window.localStorage.removeItem(TOKEN_KEY);
    state.token = '';
    state.me = null;
    state.roles = [];
    state.tray = [];
    state.activeWindow = null;
    state.history = null;
    state.authMode = 'login';
}

async function loadMe() {
    const payload = await api('/api/me');
    state.me = payload.user;
    state.roles = payload.roles || [];
}

async function loadTray() {
    state.tray = await api('/api/sg/tray');
}

async function openWindow(windowId) {
    state.error = '';
    state.notice = '';
    const payload = await api(`/api/sg/windows/${windowId}`);
    state.activeWindow = payload.window;
    state.history = null;
    window.history.replaceState({}, '', `/app/sg/windows/${windowId}`);
}

async function openReview(root) {
    await mutateWindow(root, `/api/sg/windows/${state.activeWindow.id}/open`, {}, 'Review opened.');
}

async function decideExpunge(root, candidateId, action) {
    const body = action === 'override' ? { reason: 'Context reviewed and override approved.' } : {};
    await mutateWindow(root, `/api/sg/windows/${state.activeWindow.id}/expunges/${candidateId}/${action}`, body, `Expunge ${action} recorded.`);
}

async function addManualExpunge(root, form) {
    await mutateWindow(root, `/api/sg/windows/${state.activeWindow.id}/manual-expunges`, {
        block_id: Number(form.querySelector('[name="block_id"]').value),
        word: form.querySelector('[name="word"]').value,
        grounds: form.querySelector('[name="grounds"]').value,
    }, 'Manual expunge added.');
}

async function signWindow(root) {
    await mutateWindow(root, `/api/sg/windows/${state.activeWindow.id}/sign`, {}, 'Window signed and returned.');
    await loadTray();
}

async function mutateWindow(root, path, body, notice) {
    if (! state.activeWindow) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        await api(path, {
            method: 'POST',
            body,
        });
        const payload = await api(`/api/sg/windows/${state.activeWindow.id}`);
        state.activeWindow = payload.window;
        state.notice = notice;
    } catch (error) {
        state.error = error.message || 'Action failed.';
    }

    render(root);
}

async function loadHistory(root) {
    if (! state.activeWindow) {
        return;
    }

    state.error = '';

    try {
        state.history = await api(`/api/sg/windows/${state.activeWindow.id}/history`);
    } catch (error) {
        state.error = error.message || 'History could not be loaded.';
    }

    render(root);
}

function blockText(blockId) {
    const block = (state.activeWindow?.blocks || []).find((item) => item.id === blockId);
    return block?.text || '';
}

const HTML_ESCAPE_MAP = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => HTML_ESCAPE_MAP[ch]);
}

function render(root) {
    root.innerHTML = html();
    bind(root);
}

function html() {
    if (state.authMode === 'checking') {
        return '<main class="workspace-loading">Loading workspace...</main>';
    }

    if (state.authMode === 'login') {
        return loginHtml();
    }

    if (state.authMode === 'forbidden') {
        return forbiddenHtml();
    }

    return workspaceHtml();
}

function loginHtml() {
    return `
        <main class="login-shell">
            <section class="login-panel">
                <p class="eyebrow">Role workspace</p>
                <h1>M16 SG</h1>
                <form data-action="login" class="login-form">
                    <label>Employee ID<input name="employee_id" autocomplete="username" required></label>
                    <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
                    ${state.error ? `<p class="form-error">${escapeHtml(state.error)}</p>` : ''}
                    <button type="submit">Sign in</button>
                </form>
            </section>
        </main>
    `;
}

function forbiddenHtml() {
    return `
        <main class="login-shell">
            <section class="login-panel">
                <p class="eyebrow">Access blocked</p>
                <h1>SG role required</h1>
                <p class="muted">This workspace is limited to authenticated Secretary General users. API authorization remains the source of truth.</p>
                <button data-action="logout">Use another account</button>
            </section>
        </main>
    `;
}

function workspaceHtml() {
    const tray = state.tray.map((windowItem) => windowCardHtml(windowItem)).join('');
    const win = state.activeWindow;

    return `
        <main class="m5-shell sg-shell">
            <aside class="assignment-rail">
                <div class="rail-header">
                    <div>
                        <p class="eyebrow">M16 SG</p>
                        <h1>${escapeHtml(state.me?.name || 'SG')}</h1>
                    </div>
                    <button class="icon-button" data-action="logout" title="Sign out" aria-label="Sign out">Out</button>
                </div>
                <div class="assignment-list">${tray || '<p class="empty-state">No windows in SG tray.</p>'}</div>
            </aside>
            <section class="workspace-main">
                ${state.error ? `<div class="alert error">${escapeHtml(state.error)}</div>` : ''}
                ${state.notice ? `<div class="alert notice">${escapeHtml(state.notice)}</div>` : ''}
                ${win ? `
                    <header class="slot-header">
                        <div>
                            <p class="eyebrow">Window ${escapeHtml(win.window_code)}</p>
                            <h2>SG Decision Packet &middot; ${escapeHtml(win.status)}</h2>
                        </div>
                        <div class="slot-actions">
                            <span class="status-pill">${escapeHtml(win.status)}</span>
                            <button data-action="open-review" ${win.status === 'sent_to_sg' ? '' : 'disabled'}>Open</button>
                            <button data-action="history">History</button>
                            <button data-action="sign" ${win.status === 'sent_to_sg' ? '' : 'disabled'}>Sign</button>
                        </div>
                    </header>
                    <section class="media-stage translator-brief">
                        <div class="media-topline">
                            <div>
                                <p class="eyebrow">Counts</p>
                                <h3>${win.pending_expunge_candidates_count} pending &middot; ${win.confirmed_expunges_count} confirmed &middot; ${win.manual_expunges_count} manual</h3>
                            </div>
                        </div>
                        ${historyHtml()}
                    </section>
                    <section class="editor-surface">
                        <div class="section-heading">
                            <h3>Expunge Decisions</h3>
                            <span>${(win.expunge_candidates || []).length} candidates</span>
                        </div>
                        ${candidateListHtml()}
                    </section>
                    ${manualFormHtml()}
                    ${blocksHtml()}
                ` : '<p class="empty-state">Select a window to review.</p>'}
            </section>
        </main>
    `;
}

function windowCardHtml(windowItem) {
    const active = state.activeWindow?.id === windowItem.id ? 'active' : '';

    return `
        <button class="assignment-card ${active}" data-action="open-window" data-window-id="${windowItem.id}">
            <span>Window ${escapeHtml(windowItem.window_code)}</span>
            <strong>${escapeHtml(windowItem.status)}</strong>
            <small>${windowItem.block_count} blocks &middot; ${windowItem.pending_expunge_candidates_count} pending expunges</small>
        </button>
    `;
}

function candidateListHtml() {
    const candidates = state.activeWindow?.expunge_candidates || [];
    return candidates.map((candidate) => `
        <article class="block-row ${candidate.state === 'pending' ? '' : 'changed'}">
            <div class="block-meta">
                <span>#${candidate.id}</span>
                <strong>${escapeHtml(candidate.word)}</strong>
                <small>${escapeHtml(candidate.state)} &middot; ${escapeHtml(candidate.master_db_ref)}</small>
            </div>
            <div class="change-strip"><span>Grounds</span>${escapeHtml(candidate.grounds)}</div>
            <p class="candidate-context">${escapeHtml(blockText(candidate.block_id))}</p>
            <div class="block-tools">
                <button data-action="confirm-expunge" data-candidate-id="${candidate.id}" ${candidate.state === 'pending' ? '' : 'disabled'}>Confirm</button>
                <button data-action="override-expunge" data-candidate-id="${candidate.id}" ${candidate.state === 'pending' ? '' : 'disabled'}>Override</button>
            </div>
        </article>
    `).join('') || '<p class="empty-state">No expunge candidates.</p>';
}

function manualFormHtml() {
    const blocks = state.activeWindow?.blocks || [];
    return `
        <section class="editor-surface">
            <div class="section-heading">
                <h3>Manual Expunge</h3>
                <span>${(state.activeWindow?.manual_expunges || []).length} added</span>
            </div>
            <form class="glossary-form" data-action="manual-expunge">
                <select name="block_id" required ${state.activeWindow?.status === 'sent_to_sg' ? '' : 'disabled'}>
                    ${blocks.map((block) => `<option value="${block.id}">Block ${block.sequence}</option>`).join('')}
                </select>
                <input name="word" placeholder="Word or phrase" required ${state.activeWindow?.status === 'sent_to_sg' ? '' : 'disabled'}>
                <input name="grounds" placeholder="Grounds" required ${state.activeWindow?.status === 'sent_to_sg' ? '' : 'disabled'}>
                <button ${state.activeWindow?.status === 'sent_to_sg' ? '' : 'disabled'}>Add</button>
            </form>
        </section>
    `;
}

function blocksHtml() {
    const rows = (state.activeWindow?.blocks || []).slice(0, 12).map((block) => `
        <article class="block-row">
            <div class="block-meta">
                <span>#${block.sequence}</span>
                <strong>${escapeHtml(block.member?.name_en || block.custom_member?.name_en || 'Unattributed')}</strong>
                <small>v${block.version}</small>
            </div>
            <p class="candidate-context">${escapeHtml(block.text)}</p>
        </article>
    `).join('');

    return `
        <section class="editor-surface">
            <div class="section-heading">
                <h3>Transcript Context</h3>
                <span>${(state.activeWindow?.blocks || []).length} blocks</span>
            </div>
            ${rows || '<p class="empty-state">No blocks available.</p>'}
        </section>
    `;
}

function historyHtml() {
    if (! state.history) {
        return '';
    }

    const audit = (state.history.audit || []).slice(0, 6).map((item) => `<li>${escapeHtml(item.action)} at ${escapeHtml(item.created_at || '')}</li>`).join('');

    return `<ul class="history-list">${audit || '<li>No SG audit events recorded.</li>'}</ul>`;
}

function bind(root) {
    root.querySelectorAll('[data-action="login"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            login(root, form);
        });
    });
    root.querySelectorAll('[data-action="logout"]').forEach((button) => button.addEventListener('click', () => logout(root)));
    root.querySelectorAll('[data-action="open-window"]').forEach((button) => {
        button.addEventListener('click', async () => {
            await openWindow(button.dataset.windowId);
            render(root);
        });
    });
    root.querySelectorAll('[data-action="open-review"]').forEach((button) => button.addEventListener('click', () => openReview(root)));
    root.querySelectorAll('[data-action="confirm-expunge"]').forEach((button) => button.addEventListener('click', () => decideExpunge(root, button.dataset.candidateId, 'confirm')));
    root.querySelectorAll('[data-action="override-expunge"]').forEach((button) => button.addEventListener('click', () => decideExpunge(root, button.dataset.candidateId, 'override')));
    root.querySelectorAll('[data-action="manual-expunge"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            addManualExpunge(root, form);
        });
    });
    root.querySelectorAll('[data-action="history"]').forEach((button) => button.addEventListener('click', () => loadHistory(root)));
    root.querySelectorAll('[data-action="sign"]').forEach((button) => button.addEventListener('click', () => signWindow(root)));
}

export { initSgWorkspace };
