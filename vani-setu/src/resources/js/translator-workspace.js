const TOKEN_KEY = 'vani_setu_api_token';
const SEALED_STATUSES = ['hv_draft_finalised', 'ev_draft_finalised', 'supervisor_review', 'director_review', 'translator_committed', 'forwarded'];

const state = {
    token: window.localStorage.getItem(TOKEN_KEY) || '',
    me: null,
    roles: [],
    queue: [],
    activeAssignment: null,
    blocks: [],
    glossary: [],
    history: null,
    loading: false,
    error: '',
    notice: '',
    authMode: 'checking',
};

function initTranslatorWorkspace(root) {
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
        if (! isTranslator()) {
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

function isTranslator() {
    return state.roles.includes('translator');
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

        if (! isTranslator()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        window.history.replaceState({}, '', '/app/translator');
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
        // Local logout is still valid when the token has already expired.
    }

    clearSession();
    window.history.replaceState({}, '', '/app/translator');
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
    state.authMode = 'login';
}

async function loadMe() {
    const payload = await api('/api/me');
    state.me = payload.user;
    state.roles = payload.roles || [];
}

async function loadQueue() {
    state.queue = await api('/api/translator/queue');
}

async function openAssignment(assignmentId) {
    state.error = '';
    state.notice = '';
    const payload = await api(`/api/translator/assignments/${assignmentId}`);
    state.activeAssignment = payload.assignment;
    state.blocks = (payload.blocks || []).sort((left, right) => left.sequence - right.sequence);
    state.glossary = payload.glossary || [];
    state.history = null;
    window.history.replaceState({}, '', `/app/translator/assignments/${assignmentId}`);
}

async function requestAi(root) {
    if (! state.activeAssignment || isReadOnly()) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        const payload = await api(`/api/translator/assignments/${state.activeAssignment.id}/request-ai`, { method: 'POST' });
        state.activeAssignment = payload.assignment;
        state.blocks = (payload.blocks || state.blocks).sort((left, right) => left.sequence - right.sequence);
        state.glossary = payload.glossary || state.glossary;
        state.notice = 'AI assist completed.';
    } catch (error) {
        state.error = error.message || 'AI assist could not be requested.';
    }

    render(root);
}

async function saveBlock(root, blockId) {
    const block = findBlock(blockId);
    const textarea = document.querySelector(`[data-translation-editor="${blockId}"]`);
    if (! block || ! textarea) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        const updated = await api(`/api/translator/assignments/${state.activeAssignment.id}/blocks/${blockId}`, {
            method: 'PUT',
            body: {
                text: textarea.value,
                version: block.version,
                kind: 'text',
            },
        });
        replaceBlock(updated);
        state.notice = 'Translation saved.';
    } catch (error) {
        if (error.status === 409) {
            block.conflict = error.payload.current;
            state.error = `Version conflict. Current saved version is ${error.payload.current?.version ?? 'unknown'}. Reload before saving this block.`;
        } else {
            state.error = error.message || 'Translation could not be saved.';
        }
    }

    render(root);
}

async function acceptAi(root, blockId) {
    state.error = '';
    state.notice = '';

    try {
        const updated = await api(`/api/translator/assignments/${state.activeAssignment.id}/blocks/${blockId}/accept-ai`, { method: 'POST' });
        replaceBlock(updated);
        state.notice = 'AI suggestion accepted.';
    } catch (error) {
        state.error = error.message || 'AI suggestion could not be accepted.';
    }

    render(root);
}

async function finaliseDraft(root, draftType) {
    if (! state.activeAssignment || isReadOnly()) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        const payload = await api(`/api/translator/slot/${state.activeAssignment.slot_id}/finalise`, {
            method: 'POST',
            body: {
                slot_version: slotVersion(),
                draft_type: draftType,
            },
        });
        state.activeAssignment = payload.assignment;
        state.notice = `${draftType.toUpperCase()} draft finalised.`;
        await loadQueue();
    } catch (error) {
        if (error.status === 409) {
            state.error = `Version conflict. Current slot version is ${error.payload.current_slot_version}. Reload the assignment before finalising.`;
        } else {
            state.error = error.message || 'Draft could not be finalised.';
        }
    }

    render(root);
}

async function forwardSupervisor(root) {
    if (! state.activeAssignment || ! ['hv_draft_finalised', 'ev_draft_finalised'].includes(state.activeAssignment.status)) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        const payload = await api(`/api/translator/assignments/${state.activeAssignment.id}/forward-supervisor`, {
            method: 'POST',
            body: {
                note: 'Ready for supervisor review.',
            },
        });
        state.activeAssignment = payload.assignment;
        state.notice = 'Forwarded to supervisor.';
        await loadQueue();
    } catch (error) {
        state.error = error.message || 'Assignment could not be forwarded.';
    }

    render(root);
}

async function commitAssignment(root) {
    if (! state.activeAssignment || isReadOnly()) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        const payload = await api(`/api/translator/assignments/${state.activeAssignment.id}/commit`, { method: 'POST' });
        state.activeAssignment = payload.assignment;
        state.notice = 'Assignment committed.';
        await loadQueue();
    } catch (error) {
        state.error = error.message || 'Assignment could not be committed.';
    }

    render(root);
}

async function saveGlossary(root, form) {
    state.error = '';
    state.notice = '';

    try {
        const term = await api('/api/translator/glossary', {
            method: 'POST',
            body: {
                term_source: form.querySelector('[name="term_source"]').value,
                term_target: form.querySelector('[name="term_target"]').value,
                language_pair: state.activeAssignment?.language_pair || form.querySelector('[name="language_pair"]').value,
                domain: form.querySelector('[name="domain"]').value,
                notes: form.querySelector('[name="notes"]').value || null,
            },
        });
        state.glossary = [...state.glossary, term].sort((left, right) => left.term_source.localeCompare(right.term_source));
        state.notice = 'Glossary term saved.';
    } catch (error) {
        state.error = error.message || 'Glossary term could not be saved.';
    }

    render(root);
}

async function loadHistory(root) {
    if (! state.activeAssignment) {
        return;
    }

    state.error = '';

    try {
        state.history = await api(`/api/translator/assignments/${state.activeAssignment.id}/history`);
    } catch (error) {
        state.error = error.message || 'History could not be loaded.';
    }

    render(root);
}

function isReadOnly() {
    return SEALED_STATUSES.includes(state.activeAssignment?.status);
}

function slotVersion() {
    if (! state.blocks.length) {
        return 0;
    }

    const versions = state.blocks.map((block) => Number(block.version || 0));
    const min = Math.min(...versions);
    const sum = versions.reduce((total, version) => total + version, 0);

    return min <= 0 ? sum : sum - versions.length + 1;
}

function findBlock(blockId) {
    return state.blocks.find((block) => block.id === Number(blockId));
}

function replaceBlock(updated) {
    state.blocks = state.blocks.map((block) => block.id === updated.id ? updated : block);
}

function sourceText(block) {
    return block.text || block.ai_text || '';
}

function draftText(block) {
    return block.translated_text || block.ai_text || block.text || '';
}

function speakerName(block) {
    return block.member?.name_en || block.custom_member?.name_en || 'Unattributed';
}

function pairLabel(pair) {
    return String(pair || '').replaceAll('_', ' ').toUpperCase();
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
                <h1>M10 Translator</h1>
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
                <h1>Translator role required</h1>
                <p class="muted">This workspace is limited to authenticated M10 translators. API authorization remains the source of truth for every action.</p>
                <button data-action="logout">Use another account</button>
            </section>
        </main>
    `;
}

function workspaceHtml() {
    const queue = state.queue.map((assignment) => assignmentCardHtml(assignment)).join('');
    const assignment = state.activeAssignment;
    const blocks = state.blocks.map((block) => blockHtml(block)).join('');
    const returnReason = assignment?.ai_translation_meta?.return_reason;

    return `
        <main class="m5-shell translator-shell">
            <aside class="assignment-rail">
                <div class="rail-header">
                    <div>
                        <p class="eyebrow">M10 Translator</p>
                        <h1>${escapeHtml(state.me?.name || 'Translator')}</h1>
                    </div>
                    <button class="icon-button" data-action="logout" title="Sign out" aria-label="Sign out">Out</button>
                </div>
                <div class="assignment-list">${queue || '<p class="empty-state">No translator assignments.</p>'}</div>
            </aside>
            <section class="workspace-main">
                ${state.error ? `<div class="alert error">${escapeHtml(state.error)}</div>` : ''}
                ${state.notice ? `<div class="alert notice">${escapeHtml(state.notice)}</div>` : ''}
                ${assignment ? `
                    <header class="slot-header">
                        <div>
                            <p class="eyebrow">${escapeHtml(pairLabel(assignment.language_pair))}</p>
                            <h2>Slot ${escapeHtml(assignment.slot?.code || assignment.slot_id)} &middot; ${escapeHtml(assignment.slot?.topic || 'Translation assignment')}</h2>
                        </div>
                        <div class="slot-actions">
                            <span class="status-pill">${escapeHtml(assignment.status)}</span>
                            <button data-action="request-ai" ${isReadOnly() ? 'disabled' : ''}>AI draft</button>
                            <button data-action="finalise-hv" ${isReadOnly() ? 'disabled' : ''}>Finalise HV</button>
                            <button data-action="forward-supervisor" ${['hv_draft_finalised', 'ev_draft_finalised'].includes(assignment.status) ? '' : 'disabled'}>Forward</button>
                            <button data-action="commit" ${isReadOnly() ? 'disabled' : ''}>Commit</button>
                        </div>
                    </header>
                    ${returnReason ? `<div class="return-note"><strong>Returned:</strong> ${escapeHtml(returnReason)}</div>` : ''}
                    <section class="media-stage translator-brief">
                        <div class="media-topline">
                            <div>
                                <p class="eyebrow">Draft state</p>
                                <h3>Slot version ${slotVersion()} &middot; ${state.blocks.length} blocks &middot; ${state.glossary.length} glossary terms</h3>
                            </div>
                            <button data-action="history">History</button>
                        </div>
                        ${historyHtml()}
                    </section>
                    <section class="editor-surface">
                        <div class="section-heading">
                            <h3>Translation Draft</h3>
                            <span>${escapeHtml(pairLabel(assignment.language_pair))}</span>
                        </div>
                        ${blocks || '<p class="empty-state">No blocks are available for this assignment.</p>'}
                    </section>
                    ${glossaryHtml()}
                ` : '<p class="empty-state">Select a translator assignment to begin.</p>'}
            </section>
        </main>
    `;
}

function assignmentCardHtml(assignment) {
    const active = state.activeAssignment?.id === assignment.id ? 'active' : '';
    const sealed = SEALED_STATUSES.includes(assignment.status) ? '<span class="mini-pill">Read only</span>' : '';

    return `
        <button class="assignment-card ${active}" data-action="open-assignment" data-assignment-id="${assignment.id}">
            <span>${escapeHtml(pairLabel(assignment.language_pair))}</span>
            <strong>Slot ${escapeHtml(assignment.slot?.code || assignment.slot_id)} ${escapeHtml(assignment.slot?.topic || '')}</strong>
            <small>${escapeHtml(assignment.status)}</small>
            <span class="pill-row">${sealed}</span>
        </button>
    `;
}

function blockHtml(block) {
    const changed = Boolean(block.translated_text);
    const conflict = block.conflict ? `<div class="conflict-box">Server draft: ${escapeHtml(block.conflict.translated_text || block.conflict.ai_text || block.conflict.text)}</div>` : '';

    return `
        <article class="block-row ${changed ? 'changed' : ''}">
            <div class="block-meta">
                <span>#${block.sequence}</span>
                <strong>${escapeHtml(speakerName(block))}</strong>
                <small>v${block.version} &middot; ${escapeHtml(block.original_lang)} to ${escapeHtml(block.chief_lang)}</small>
            </div>
            <div class="parallel-text">
                <div>
                    <span>Source</span>
                    <p>${escapeHtml(sourceText(block))}</p>
                </div>
                <div>
                    <span>AI suggestion</span>
                    <p>${escapeHtml(block.ai_text || 'No AI suggestion requested.')}</p>
                </div>
            </div>
            <textarea data-translation-editor="${block.id}" ${isReadOnly() ? 'readonly' : ''}>${escapeHtml(draftText(block))}</textarea>
            ${conflict}
            <div class="block-tools">
                <button data-action="save-block" data-block-id="${block.id}" ${isReadOnly() ? 'disabled' : ''}>Save draft</button>
                <button data-action="accept-ai" data-block-id="${block.id}" ${isReadOnly() || ! block.ai_text ? 'disabled' : ''}>Accept AI</button>
                <span class="status-pill">${changed ? 'edited' : 'unreviewed'}</span>
            </div>
        </article>
    `;
}

function glossaryHtml() {
    const terms = state.glossary.slice(0, 12).map((term) => `
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
            <form class="glossary-form" data-action="glossary">
                <input name="term_source" placeholder="Source term" required ${isReadOnly() ? 'disabled' : ''}>
                <input name="term_target" placeholder="Target term" required ${isReadOnly() ? 'disabled' : ''}>
                <input name="language_pair" value="${escapeHtml(state.activeAssignment?.language_pair || '')}" hidden>
                <select name="domain" ${isReadOnly() ? 'disabled' : ''}>
                    <option value="parliamentary">Parliamentary</option>
                    <option value="economic">Economic</option>
                    <option value="legal">Legal</option>
                </select>
                <input name="notes" placeholder="Notes" ${isReadOnly() ? 'disabled' : ''}>
                <button ${isReadOnly() ? 'disabled' : ''}>Add</button>
            </form>
            <table class="glossary-table">
                <thead><tr><th>Source</th><th>Target</th><th>Domain</th></tr></thead>
                <tbody>${terms || '<tr><td colspan="3">No glossary terms.</td></tr>'}</tbody>
            </table>
        </section>
    `;
}

function historyHtml() {
    if (! state.history) {
        return '';
    }

    const edits = (state.history.edits || []).slice(0, 6).map((edit) => `
        <li>${escapeHtml(edit.kind)} edit on block ${escapeHtml(edit.block_id)} at ${escapeHtml(edit.created_at || '')}</li>
    `).join('');

    return `<ul class="history-list">${edits || '<li>No translator edits recorded.</li>'}</ul>`;
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
    root.querySelectorAll('[data-action="request-ai"]').forEach((button) => button.addEventListener('click', () => requestAi(root)));
    root.querySelectorAll('[data-action="save-block"]').forEach((button) => button.addEventListener('click', () => saveBlock(root, button.dataset.blockId)));
    root.querySelectorAll('[data-action="accept-ai"]').forEach((button) => button.addEventListener('click', () => acceptAi(root, button.dataset.blockId)));
    root.querySelectorAll('[data-action="finalise-hv"]').forEach((button) => button.addEventListener('click', () => finaliseDraft(root, 'hv')));
    root.querySelectorAll('[data-action="forward-supervisor"]').forEach((button) => button.addEventListener('click', () => forwardSupervisor(root)));
    root.querySelectorAll('[data-action="commit"]').forEach((button) => button.addEventListener('click', () => commitAssignment(root)));
    root.querySelectorAll('[data-action="glossary"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            saveGlossary(root, form);
        });
    });
    root.querySelectorAll('[data-action="history"]').forEach((button) => button.addEventListener('click', () => loadHistory(root)));
}

export { initTranslatorWorkspace };
