const TOKEN_KEY = 'vani_setu_api_token';

const state = {
    token: window.localStorage.getItem(TOKEN_KEY) || '',
    me: null,
    roles: [],
    queue: [],
    activeChunk: null,
    history: null,
    error: '',
    notice: '',
    authMode: 'checking',
};

function initSynopsisWorkspace(root) {
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
        if (! isWriter()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        await loadQueue();
        const initial = root.dataset.initialConsolidation;
        if (initial) {
            await openChunk(initial);
        } else if (state.queue[0]) {
            await openChunk(state.queue[0].id);
        }
    } catch (error) {
        if (error.status === 401) {
            clearSession();
        } else {
            state.error = error.message || 'Synopsis workspace could not be loaded.';
        }
    }

    render(root);
}

function isWriter() {
    return state.roles.includes('synopsis_writer') || state.roles.includes('admin');
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

        if (! isWriter()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        window.history.replaceState({}, '', '/app/synopsis');
        await loadQueue();
        if (state.queue[0]) {
            await openChunk(state.queue[0].id);
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
    window.history.replaceState({}, '', '/app/synopsis');
    render(root);
}

function clearSession() {
    window.localStorage.removeItem(TOKEN_KEY);
    state.token = '';
    state.me = null;
    state.roles = [];
    state.queue = [];
    state.activeChunk = null;
    state.history = null;
    state.authMode = 'login';
}

async function loadMe() {
    const payload = await api('/api/me');
    state.me = payload.user;
    state.roles = payload.roles || [];
}

async function loadQueue() {
    state.queue = await api('/api/synopsis/queue');
}

async function openChunk(chunkId) {
    state.error = '';
    state.notice = '';
    state.activeChunk = await api(`/api/synopsis/chunks/${chunkId}`);
    state.history = null;
    window.history.replaceState({}, '', `/app/synopsis/chunks/${chunkId}`);
}

async function reloadActiveChunk() {
    if (! state.activeChunk?.chunk?.id) {
        return;
    }

    state.activeChunk = await api(`/api/synopsis/chunks/${state.activeChunk.chunk.id}`);
    state.history = null;
    await loadQueue();
}

async function loadHistory(root) {
    if (! state.activeChunk) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        state.history = await api(`/api/synopsis/chunks/${state.activeChunk.chunk.id}/history`);
        state.notice = 'History loaded.';
    } catch (error) {
        state.error = error.message || 'History could not be loaded.';
    }

    render(root);
}

async function generateFromBlocks(root) {
    await mutateChunk(root, `/api/synopsis/chunks/${state.activeChunk.chunk.id}/generate`, {}, 'Synopsis generated from accepted chunk.');
}

async function generateFromText(root, form) {
    await mutateChunk(root, `/api/synopsis/chunks/${state.activeChunk.chunk.id}/generate-from-text`, {
        title: form.querySelector('[name="title"]').value.trim(),
        source_text: form.querySelector('[name="source_text"]').value.trim(),
    }, 'Synopsis generated from pasted text.');
}

async function saveDraft(root, form) {
    const attributionRows = Array.from(root.querySelectorAll('[data-attribution]')).map((row) => ({
        speaker_name: row.querySelector('[name="speaker_name"]').value.trim(),
        constituency: row.querySelector('[name="constituency"]').value.trim() || null,
        summary_text: row.querySelector('[name="summary_text"]').value.trim(),
    })).filter((row) => row.speaker_name || row.summary_text);

    await mutateChunk(root, `/api/synopsis/chunks/${state.activeChunk.chunk.id}/draft`, {
        title: form.querySelector('[name="title"]').value.trim(),
        body: form.querySelector('[name="body"]').value.trim(),
        version: state.activeChunk.document?.version,
        attributions: attributionRows,
    }, 'Draft saved.', 'PUT');
}

async function submitDraft(root) {
    await mutateChunk(root, `/api/synopsis/chunks/${state.activeChunk.chunk.id}/submit`, {}, 'Draft submitted.');
}

async function finaliseDraft(root) {
    await mutateChunk(root, `/api/synopsis/chunks/${state.activeChunk.chunk.id}/finalise`, {}, 'Synopsis finalised.');
}

async function mutateChunk(root, path, body, notice, method = 'POST') {
    if (! state.activeChunk) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        state.activeChunk = await api(path, { method, body });
        await loadQueue();
        state.notice = notice;
    } catch (error) {
        if (error.status === 409 && error.payload) {
            const conflict = conflictMessage(error.payload);
            if (hasVersionConflictState(error.payload)) {
                applyDraftConflictPayload(error.payload);
                await loadQueue();
            } else {
                await reloadActiveChunk();
            }
            state.error = conflict;
        } else {
            state.error = error.message || 'Action failed.';
        }
    }

    render(root);
}

function hasVersionConflictState(payload) {
    return Boolean(
        payload.current_version
        && Object.prototype.hasOwnProperty.call(payload, 'current_title')
        && Object.prototype.hasOwnProperty.call(payload, 'current_body')
        && Array.isArray(payload.current_attributions),
    );
}

function applyDraftConflictPayload(payload) {
    if (! state.activeChunk?.document) {
        return;
    }

    state.activeChunk = {
        ...state.activeChunk,
        chunk: {
            ...state.activeChunk.chunk,
            version: payload.current_version,
        },
        document: {
            ...state.activeChunk.document,
            title: payload.current_title,
            body: payload.current_body,
            attributions: payload.current_attributions,
            version: payload.current_version,
        },
    };
}

function conflictMessage(payload) {
    if (payload.source_sha256 || payload.generated_source_sha256) {
        const current = payload.source_sha256 ? ` Current source ${shortHash(payload.source_sha256)}.` : '';
        const generated = payload.generated_source_sha256 ? ` Draft source ${shortHash(payload.generated_source_sha256)}.` : '';

        return `${payload.message || 'Source blocks changed after synopsis generation. Regenerate before submit or finalise.'}${current}${generated}`;
    }

    if (payload.current_version) {
        return `Version conflict. Current saved version is ${payload.current_version}. Reloaded latest draft.`;
    }

    return payload.message || 'Conflict detected. Reloaded latest draft.';
}

function render(root) {
    if (state.authMode === 'checking') {
        root.innerHTML = '<div class="workspace-loading">Loading Synopsis workspace...</div>';
        return;
    }

    if (state.authMode === 'login') {
        root.innerHTML = loginMarkup();
        bind(root);
        return;
    }

    if (state.authMode === 'forbidden') {
        root.innerHTML = `
            <main class="login-shell">
                <section class="login-panel">
                    <p class="eyebrow">Access blocked</p>
                    <h1>Synopsis Writer</h1>
                    <p class="form-error">Your account is not assigned to the Synopsis writer workspace.</p>
                    <button data-action="logout">Use another account</button>
                </section>
            </main>
        `;
        bind(root);
        return;
    }

    root.innerHTML = workspaceMarkup();
    bind(root);
}

function loginMarkup() {
    return `
        <main class="login-shell">
            <section class="login-panel">
                <p class="eyebrow">Authenticated workspace</p>
                <h1>Synopsis Writer</h1>
                ${state.error ? `<p class="form-error">${escapeHtml(state.error)}</p>` : ''}
                <form class="login-form" data-form="login">
                    <label>Employee ID <input name="employee_id" autocomplete="username" value="CHF-EN-001"></label>
                    <label>Password <input name="password" type="password" autocomplete="current-password" value="chief123"></label>
                    <button type="submit">Sign in</button>
                </form>
            </section>
        </main>
    `;
}

function workspaceMarkup() {
    const list = state.queue.map(chunkCard).join('');
    return `
        <main class="m5-shell">
            <aside class="assignment-rail">
                <div class="rail-header">
                    <div>
                        <p class="eyebrow">Synopsis</p>
                        <h1>${escapeHtml(state.me?.name || 'Writer')}</h1>
                    </div>
                    <button class="icon-button" data-action="logout" title="Sign out">Out</button>
                </div>
                <div class="assignment-list">${list || '<p class="empty-state">No accepted chunks ready for synopsis.</p>'}</div>
            </aside>
            <section class="workspace-main">
                ${state.error ? `<div class="alert error">${escapeHtml(state.error)}</div>` : ''}
                ${state.notice ? `<div class="alert notice">${escapeHtml(state.notice)}</div>` : ''}
                ${state.activeChunk ? chunkMarkup() : '<p class="empty-state">Select a chunk.</p>'}
            </section>
        </main>
    `;
}

function chunkCard(chunk) {
    const active = state.activeChunk?.chunk?.id === chunk.id ? ' active' : '';
    const generation = chunk.latest_generation;
    return `
        <button class="assignment-card${active}" data-action="open-chunk" data-chunk-id="${chunk.id}">
            <span>Chunk ${escapeHtml(chunk.chunk_code)}</span>
            <strong>${escapeHtml(chunk.status)}</strong>
            <small>${chunk.block_count} blocks &middot; v${escapeHtml(chunk.version || 1)}</small>
            <div class="pill-row">
                <em class="mini-pill">${escapeHtml(chunk.source_status)}</em>
                ${chunk.ai_first_draft ? '<em class="mini-pill warn">AI draft</em>' : ''}
                ${generation?.provider ? `<em class="mini-pill">${escapeHtml(generation.provider)}</em>` : ''}
                ${generation?.source_sha256 ? `<em class="mini-pill">src ${escapeHtml(shortHash(generation.source_sha256))}</em>` : ''}
            </div>
        </button>
    `;
}

function chunkMarkup() {
    const chunk = state.activeChunk.chunk;
    const document = state.activeChunk.document || {};
    const generation = document.latest_generation || chunk.latest_generation;
    const final = document.status === 'final';
    const sourceReady = ['dual_committed', 'forwarded_to_js'].includes(chunk.source_status);
    const sourceLocked = ! sourceReady;
    const draftLocked = document.status === 'submitted' || final || sourceLocked;
    const readOnlyReason = sourceLocked
        ? 'Chief consolidation must be accepted before synopsis drafting.'
        : '';

    return `
        <header class="slot-header">
            <div>
                <p class="eyebrow">Chunk ${escapeHtml(chunk.chunk_code)}</p>
                <h2>Synopsis Draft &middot; ${escapeHtml(document.status || 'empty')}</h2>
                <div class="pill-row">
                    <span class="status-pill">v${escapeHtml(document.version || 1)}</span>
                    <span class="mini-pill">${escapeHtml(chunk.block_count)} source blocks</span>
                    ${document.ai_first_draft ? '<span class="mini-pill warn">Hosted model first draft</span>' : ''}
                    ${generationPill(generation)}
                    ${sourceHashPill(generation)}
                    ${sourceLocked ? '<span class="mini-pill warn">Source not ready</span>' : ''}
                </div>
            </div>
            <div class="slot-actions">
                <button data-action="generate-blocks" ${draftLocked ? 'disabled' : ''}>Generate</button>
                <button data-action="history">History</button>
                <button data-action="submit" ${document.status === 'draft' && sourceReady ? '' : 'disabled'}>Submit</button>
                <button data-action="finalise" ${document.status === 'submitted' && sourceReady ? '' : 'disabled'}>Finalise</button>
                <button data-action="export" ${document.status === 'final' ? '' : 'disabled'}>Export</button>
            </div>
        </header>
        ${readOnlyReason ? `<div class="alert notice">${escapeHtml(readOnlyReason)}</div>` : ''}
        <section class="media-stage">
            <div class="section-heading">
                <h3>Paste Long Proceedings</h3>
                <span>${generationLabel(generation)}</span>
            </div>
            <form class="draft-note" data-form="generate-text">
                <label>Title <input name="title" value="${escapeAttribute(document.title || '')}" ${draftLocked ? 'disabled' : ''}></label>
                <label>Proceedings text <textarea name="source_text" rows="7" ${draftLocked ? 'disabled' : ''} placeholder="Paste long proceedings text here."></textarea></label>
                <button ${draftLocked ? 'disabled' : ''}>Generate From Text</button>
            </form>
        </section>
        <section class="editor-surface">
            <form class="draft-note" data-form="save-draft">
                <div class="section-heading">
                    <h3>Draft Body</h3>
                    <span>${escapeHtml(document.source_mode || 'scratch')}</span>
                </div>
                <label>Title <input name="title" value="${escapeAttribute(document.title || '')}" ${draftLocked ? 'disabled' : ''}></label>
                <label>Body <textarea name="body" rows="16" ${draftLocked ? 'readonly' : ''}>${escapeHtml(document.body || '')}</textarea></label>
                ${attributionMarkup(document.attributions || [], draftLocked)}
                <button ${draftLocked ? 'disabled' : ''}>Save Draft</button>
            </form>
        </section>
        ${draftNotesMarkup()}
        ${historyMarkup()}
        ${sourceBlocksMarkup()}
    `;
}

function generationPill(generation) {
    if (! generation?.provider) {
        return '';
    }

    const fallback = generation.fallback_reason
        ? `: ${generation.fallback_reason}`
        : '';
    const label = generation.provider === 'fallback'
        ? `Fallback${fallback}`
        : `${generation.provider} ${generation.model || ''}`.trim();

    return `<span class="mini-pill${generation.provider === 'fallback' ? ' warn' : ''}">${escapeHtml(label)}</span>`;
}

function generationLabel(generation) {
    if (! generation?.provider) {
        return 'Hosted E&amp;T synopsis model';
    }

    if (generation.provider === 'fallback') {
        return `Fallback used: ${escapeHtml(generation.fallback_reason || 'local governed template')}`;
    }

    return `Hosted E&amp;T model: ${escapeHtml(generation.model || generation.provider)}`;
}

function sourceHashPill(generation) {
    if (! generation?.source_sha256) {
        return '';
    }

    return `<span class="mini-pill">Source ${escapeHtml(shortHash(generation.source_sha256))}</span>`;
}

function shortHash(hash) {
    return String(hash || '').slice(0, 12);
}

function historyMarkup() {
    if (! state.history) {
        return '';
    }

    const rows = (state.history.edits || []).slice(0, 12).map((edit) => `
        <article class="block-row">
            <div class="block-meta">
                <span>${escapeHtml(edit.kind)}</span>
                <strong>v${escapeHtml(edit.from_version || '')} to v${escapeHtml(edit.to_version || '')}</strong>
                <small>${escapeHtml(edit.audit_log?.action || '')} &middot; ${escapeHtml(edit.audit_log?.this_hash || '')}</small>
                ${auditEvidenceMarkup(edit.audit_evidence)}
            </div>
            <p>${escapeHtml(edit.after_excerpt || edit.before_excerpt || '')}</p>
        </article>
    `).join('');
    const auditRows = (state.history.audit_events || []).slice(0, 8).map((event) => `
        <article class="block-row">
            <div class="block-meta">
                <span>${escapeHtml(event.action || '')}</span>
                <strong>${escapeHtml(event.created_at || '')}</strong>
                <small>${escapeHtml(event.this_hash || '')}</small>
                ${auditEvidenceMarkup(event.audit_evidence)}
            </div>
        </article>
    `).join('');

    return `
        <section class="editor-surface">
            <div class="section-heading">
                <h3>Edit History</h3>
                <span>${(state.history.edits || []).length} edits</span>
            </div>
            ${rows || '<p class="empty-state">No synopsis edits recorded.</p>'}
            <div class="section-heading">
                <h3>Audit Evidence</h3>
                <span>${(state.history.audit_events || []).length} events</span>
            </div>
            ${auditRows || '<p class="empty-state">No synopsis audit events recorded.</p>'}
        </section>
    `;
}

function auditEvidenceMarkup(evidence) {
    if (! evidence) {
        return '';
    }

    const pills = [];
    if (evidence.generation_provider) {
        const model = evidence.generation_model ? ` ${evidence.generation_model}` : '';
        pills.push(`${evidence.generation_provider}${model}`);
    }

    if (evidence.generation_fallback_reason) {
        pills.push(evidence.generation_fallback_reason);
    }

    if (evidence.generation_fallback_detail) {
        pills.push(evidence.generation_fallback_detail);
    }

    if (evidence.generation_http_status) {
        pills.push(`HTTP ${evidence.generation_http_status}`);
    }

    if (evidence.generation_request_id) {
        pills.push(`req ${shortHash(evidence.generation_request_id)}`);
    }

    if (evidence.source_sha256) {
        pills.push(`src ${shortHash(evidence.source_sha256)}`);
    }

    if (evidence.pdf_sha256) {
        pills.push(`pdf ${shortHash(evidence.pdf_sha256)}`);
    }

    if (! pills.length) {
        return '';
    }

    return `<div class="pill-row">${pills.map((pill) => `<em class="mini-pill">${escapeHtml(pill)}</em>`).join('')}</div>`;
}

function draftNotesMarkup() {
    return `
        <section class="editor-surface">
            <div class="section-heading">
                <h3>Draft Notes</h3>
                <span>Unavailable</span>
            </div>
            <div class="draft-note unavailable">
                <label>Writer comments and suggestions</label>
                <textarea disabled rows="4">Comment API unavailable in this stage. Notes are not persisted.</textarea>
            </div>
        </section>
    `;
}

function attributionMarkup(attributions, disabled = false) {
    const rows = (attributions.length ? attributions : [{ speaker_name: '', constituency: '', summary_text: '' }]).map((item) => `
        <div class="glossary-form" data-attribution>
            <input name="speaker_name" placeholder="Speaker" value="${escapeAttribute(item.speaker_name || '')}" ${disabled ? 'disabled' : ''}>
            <input name="constituency" placeholder="Constituency" value="${escapeAttribute(item.constituency || '')}" ${disabled ? 'disabled' : ''}>
            <input name="summary_text" placeholder="Summary" value="${escapeAttribute(item.summary_text || '')}" ${disabled ? 'disabled' : ''}>
        </div>
    `).join('');

    return `
        <div class="section-heading">
            <h3>Attributions</h3>
            <span>${attributions.length} rows</span>
        </div>
        ${rows}
    `;
}

function sourceBlocksMarkup() {
    const rows = (state.activeChunk.source_blocks || []).slice(0, 8).map((block) => `
        <article class="block-row">
            <div class="block-meta">
                <span>#${block.sequence}</span>
                <strong>${escapeHtml(block.member?.name_en || block.custom_member?.name_en || 'Proceedings')}</strong>
                <small>v${block.version}</small>
            </div>
            <p>${escapeHtml(block.text)}</p>
        </article>
    `).join('');

    return `
        <section class="editor-surface">
            <div class="section-heading">
                <h3>Source Blocks</h3>
                <span>${(state.activeChunk.source_blocks || []).length} blocks</span>
            </div>
            ${rows || '<p class="empty-state">No source blocks.</p>'}
        </section>
    `;
}

function bind(root) {
    root.querySelector('[data-form="login"]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        login(root, event.currentTarget);
    });
    root.querySelectorAll('[data-action="logout"]').forEach((button) => button.addEventListener('click', () => logout(root)));
    root.querySelectorAll('[data-action="open-chunk"]').forEach((button) => button.addEventListener('click', async () => {
        await openChunk(button.dataset.chunkId);
        render(root);
    }));
    root.querySelectorAll('[data-action="generate-blocks"]').forEach((button) => button.addEventListener('click', () => generateFromBlocks(root)));
    root.querySelectorAll('[data-action="history"]').forEach((button) => button.addEventListener('click', () => loadHistory(root)));
    root.querySelector('[data-form="generate-text"]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        generateFromText(root, event.currentTarget);
    });
    root.querySelector('[data-form="save-draft"]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        saveDraft(root, event.currentTarget);
    });
    root.querySelectorAll('[data-action="submit"]').forEach((button) => button.addEventListener('click', () => submitDraft(root)));
    root.querySelectorAll('[data-action="finalise"]').forEach((button) => button.addEventListener('click', () => finaliseDraft(root)));
    root.querySelectorAll('[data-action="export"]').forEach((button) => button.addEventListener('click', () => exportSynopsis(root)));
}

async function exportSynopsis(root) {
    if (! state.activeChunk) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        const response = await fetch(`/api/synopsis/chunks/${state.activeChunk.chunk.id}/export.pdf`, {
            headers: {
                Accept: 'application/pdf',
                Authorization: `Bearer ${state.token}`,
            },
        });

        if (! response.ok) {
            throw new Error(response.statusText || 'Export failed.');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 30000);
        state.notice = 'Synopsis PDF opened.';
    } catch (error) {
        state.error = error.message || 'Export failed.';
    }

    render(root);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#096;');
}

export { initSynopsisWorkspace };
