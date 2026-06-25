const TOKEN_KEY = 'vani_setu_api_token';

const state = {
    token: window.localStorage.getItem(TOKEN_KEY) || '',
    me: null,
    roles: [],
    queue: [],
    activeAssignment: null,
    blocks: [],
    glossary: [],
    history: null,
    error: '',
    notice: '',
    authMode: 'checking',
};

function initReviewerWorkspace(root) {
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
        if (! canReview()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        await loadQueue();
        const initialAssignment = root.dataset.initialAssignment;
        if (initialAssignment) {
            await openAssignment(initialAssignment);
        } else if (state.queue[0]) {
            await openAssignment(state.queue[0].id);
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

function canReview() {
    return state.roles.includes('supervisor') || state.roles.includes('admin');
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

        if (! canReview()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        window.history.replaceState({}, '', '/app/reviewer');
        await loadQueue();
        if (state.queue[0]) {
            await openAssignment(state.queue[0].id);
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
        // Token may already be expired.
    }

    clearSession();
    window.history.replaceState({}, '', '/app/reviewer');
    render(root);
}

function clearSession() {
    window.localStorage.removeItem(TOKEN_KEY);
    state.token = '';
    state.me = null;
    state.roles = [];
    state.queue = [];
    state.activeAssignment = null;
    state.blocks = [];
    state.glossary = [];
    state.history = null;
    state.authMode = 'login';
}

async function loadMe() {
    const payload = await api('/api/me');
    state.me = payload.user;
    state.roles = payload.roles || [];
}

async function loadQueue() {
    state.queue = await api('/api/translator/reviewer/queue');
}

async function openAssignment(assignmentId) {
    state.error = '';
    state.notice = '';
    const payload = await api(`/api/translator/reviewer/assignments/${assignmentId}`);
    state.activeAssignment = payload.assignment;
    state.blocks = (payload.blocks || []).sort((left, right) => left.sequence - right.sequence);
    state.glossary = payload.glossary || [];
    state.history = payload.history || null;
    window.history.replaceState({}, '', `/app/reviewer/assignments/${assignmentId}`);
}

async function forwardDirector(root) {
    if (! state.activeAssignment) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        const payload = await api(`/api/translator/reviewer/assignments/${state.activeAssignment.id}/forward-director`, {
            method: 'POST',
            body: {
                note: 'Reviewer cleared.',
            },
        });
        state.activeAssignment = payload.assignment;
        state.notice = 'Forwarded to Director.';
        await loadQueue();
    } catch (error) {
        state.error = error.message || 'Assignment could not be forwarded.';
    }

    render(root);
}

async function returnTranslator(root) {
    if (! state.activeAssignment) {
        return;
    }

    const reason = document.querySelector('[data-return-reason]')?.value.trim();
    state.error = '';
    state.notice = '';

    if (! reason || reason.length < 10) {
        state.error = 'Return reason must be at least 10 characters.';
        render(root);
        return;
    }

    try {
        const payload = await api(`/api/translator/reviewer/assignments/${state.activeAssignment.id}/return-translator`, {
            method: 'POST',
            body: { reason },
        });
        state.activeAssignment = payload.assignment;
        state.notice = 'Returned to translator.';
        await loadQueue();
    } catch (error) {
        state.error = error.message || 'Assignment could not be returned.';
    }

    render(root);
}

function pairLabel(pair) {
    return String(pair || '').replaceAll('_', ' ').toUpperCase();
}

function speakerName(block) {
    return block.member?.name_en || block.custom_member?.name_en || 'Unattributed';
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
                <h1>M17 Reviewer</h1>
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
                <h1>Reviewer role required</h1>
                <p class="muted">This workspace is limited to authenticated M17 supervisor reviewers. API authorization remains the source of truth.</p>
                <button data-action="logout">Use another account</button>
            </section>
        </main>
    `;
}

function workspaceHtml() {
    const queue = state.queue.map((assignment) => assignmentCardHtml(assignment)).join('');
    const assignment = state.activeAssignment;
    const blocks = state.blocks.map((block) => blockHtml(block)).join('');

    return `
        <main class="m5-shell reviewer-shell">
            <aside class="assignment-rail">
                <div class="rail-header">
                    <div>
                        <p class="eyebrow">M17 Reviewer</p>
                        <h1>${escapeHtml(state.me?.name || 'Reviewer')}</h1>
                    </div>
                    <button class="icon-button" data-action="logout" title="Sign out" aria-label="Sign out">Out</button>
                </div>
                <div class="assignment-list">${queue || '<p class="empty-state">No assignments in supervisor review.</p>'}</div>
            </aside>
            <section class="workspace-main">
                ${state.error ? `<div class="alert error">${escapeHtml(state.error)}</div>` : ''}
                ${state.notice ? `<div class="alert notice">${escapeHtml(state.notice)}</div>` : ''}
                ${assignment ? `
                    <header class="slot-header">
                        <div>
                            <p class="eyebrow">${escapeHtml(pairLabel(assignment.language_pair))}</p>
                            <h2>Slot ${escapeHtml(assignment.slot?.code || assignment.slot_id)} &middot; ${escapeHtml(assignment.slot?.topic || 'Reviewer assignment')}</h2>
                        </div>
                        <div class="slot-actions">
                            <span class="status-pill">${escapeHtml(assignment.status)}</span>
                            <button data-action="forward-director" ${assignment.status === 'supervisor_review' ? '' : 'disabled'}>Forward</button>
                        </div>
                    </header>
                    <section class="media-stage translator-brief">
                        <div class="media-topline">
                            <div>
                                <p class="eyebrow">Review packet</p>
                                <h3>${state.blocks.length} blocks &middot; ${state.glossary.length} glossary terms &middot; ${historyCount()} audit events</h3>
                            </div>
                        </div>
                        <div class="return-control">
                            <textarea data-return-reason placeholder="Return reason for translator correction" ${assignment.status === 'supervisor_review' ? '' : 'disabled'}></textarea>
                            <button data-action="return-translator" ${assignment.status === 'supervisor_review' ? '' : 'disabled'}>Return</button>
                        </div>
                    </section>
                    <section class="editor-surface">
                        <div class="section-heading">
                            <h3>Reviewer Comparison</h3>
                            <span>Read only</span>
                        </div>
                        ${blocks || '<p class="empty-state">No blocks are available for this assignment.</p>'}
                    </section>
                    ${glossaryHtml()}
                ` : '<p class="empty-state">Select an assignment to review.</p>'}
            </section>
        </main>
    `;
}

function assignmentCardHtml(assignment) {
    const active = state.activeAssignment?.id === assignment.id ? 'active' : '';

    return `
        <button class="assignment-card ${active}" data-action="open-assignment" data-assignment-id="${assignment.id}">
            <span>${escapeHtml(pairLabel(assignment.language_pair))}</span>
            <strong>Slot ${escapeHtml(assignment.slot?.code || assignment.slot_id)} ${escapeHtml(assignment.slot?.topic || '')}</strong>
            <small>${escapeHtml(assignment.status)} &middot; ${escapeHtml(assignment.translator?.name || 'Translator')}</small>
        </button>
    `;
}

function blockHtml(block) {
    return `
        <article class="block-row changed">
            <div class="block-meta">
                <span>#${block.sequence}</span>
                <strong>${escapeHtml(speakerName(block))}</strong>
                <small>v${block.version} &middot; ${escapeHtml(block.original_lang)} to ${escapeHtml(block.chief_lang)}</small>
            </div>
            <div class="parallel-text reviewer-compare">
                <div>
                    <span>Source</span>
                    <p>${escapeHtml(block.text || '')}</p>
                </div>
                <div>
                    <span>Translator draft</span>
                    <p>${escapeHtml(block.translated_text || block.ai_text || block.text || '')}</p>
                </div>
            </div>
        </article>
    `;
}

function glossaryHtml() {
    const terms = state.glossary.slice(0, 10).map((term) => `
        <tr>
            <td>${escapeHtml(term.term_source)}</td>
            <td>${escapeHtml(term.term_target)}</td>
            <td>${escapeHtml(term.domain)}</td>
        </tr>
    `).join('');

    return `
        <section class="editor-surface glossary-panel">
            <div class="section-heading">
                <h3>Glossary</h3>
                <span>${state.glossary.length} approved terms</span>
            </div>
            <table class="glossary-table">
                <thead><tr><th>Source</th><th>Target</th><th>Domain</th></tr></thead>
                <tbody>${terms || '<tr><td colspan="3">No glossary terms.</td></tr>'}</tbody>
            </table>
        </section>
    `;
}

function historyCount() {
    return Number(state.history?.audit?.length || 0);
}

function bind(root) {
    root.querySelectorAll('[data-action="login"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            login(root, form);
        });
    });
    root.querySelectorAll('[data-action="logout"]').forEach((button) => button.addEventListener('click', () => logout(root)));
    root.querySelectorAll('[data-action="open-assignment"]').forEach((button) => {
        button.addEventListener('click', async () => {
            await openAssignment(button.dataset.assignmentId);
            render(root);
        });
    });
    root.querySelectorAll('[data-action="forward-director"]').forEach((button) => button.addEventListener('click', () => forwardDirector(root)));
    root.querySelectorAll('[data-action="return-translator"]').forEach((button) => button.addEventListener('click', () => returnTranslator(root)));
}

export { initReviewerWorkspace };
