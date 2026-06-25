const TOKEN_KEY = 'vani_setu_api_token';

const state = {
    token: window.localStorage.getItem(TOKEN_KEY) || '',
    me: null,
    roles: [],
    inbox: [],
    jobs: [],
    activeJob: null,
    audit: [],
    error: '',
    notice: '',
    authMode: 'checking',
};

function initDirectorWorkspace(root) {
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
        if (! isDirector()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        await loadQueues();
        const initialJob = root.dataset.initialJob;
        if (initialJob) {
            await openJob(initialJob);
        } else if (state.inbox[0]) {
            await openJob(state.inbox[0].id);
        }
    } catch (error) {
        if (error.status === 401) {
            clearSession();
        } else {
            state.error = error.message || 'Director workspace could not be loaded.';
        }
    }

    render(root);
}

function isDirector() {
    return state.roles.includes('director');
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

        if (! isDirector()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        window.history.replaceState({}, '', '/app/director');
        await loadQueues();
        if (state.inbox[0]) {
            await openJob(state.inbox[0].id);
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
    window.history.replaceState({}, '', '/app/director');
    render(root);
}

function clearSession() {
    window.localStorage.removeItem(TOKEN_KEY);
    state.token = '';
    state.me = null;
    state.roles = [];
    state.inbox = [];
    state.jobs = [];
    state.activeJob = null;
    state.audit = [];
    state.authMode = 'login';
}

async function loadMe() {
    const payload = await api('/api/me');
    state.me = payload.user;
    state.roles = payload.roles || [];
}

async function loadQueues() {
    state.inbox = await api('/api/director/inbox');
    state.jobs = await api('/api/director/jobs');
}

async function openJob(jobId) {
    state.error = '';
    state.notice = '';
    const payload = await api(`/api/director/jobs/${jobId}`);
    state.activeJob = payload.job;
    await loadJobLog(jobId);
    window.history.replaceState({}, '', `/app/director/jobs/${jobId}`);
}

async function loadJobLog(jobId = state.activeJob?.id) {
    if (! jobId) {
        state.audit = [];
        return;
    }

    const payload = await api(`/api/director/jobs/${jobId}/log`);
    state.activeJob = payload.job;
    state.audit = payload.audit || [];
}

async function publishJob(root) {
    if (! state.activeJob) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        const payload = await api(`/api/director/jobs/${state.activeJob.id}/publish`, {
            method: 'POST',
            body: {},
        });
        state.activeJob = payload.job;
        state.notice = 'Publish job completed.';
        await loadQueues();
        await loadJobLog(state.activeJob.id);
    } catch (error) {
        state.error = error.message || 'Publish failed.';
    }

    render(root);
}

function render(root) {
    if (state.authMode === 'checking') {
        root.innerHTML = '<div class="workspace-loading">Loading Director workspace...</div>';
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
                    <h1>M18 Director</h1>
                    <p class="form-error">Your account is not assigned to the Director publishing workspace.</p>
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
                <h1>M18 Director</h1>
                ${state.error ? `<p class="form-error">${escapeHtml(state.error)}</p>` : ''}
                <form class="login-form" data-form="login">
                    <label>Employee ID <input name="employee_id" autocomplete="username" value="DIR-001"></label>
                    <label>Password <input name="password" type="password" autocomplete="current-password" value="director123"></label>
                    <button type="submit">Sign in</button>
                </form>
            </section>
        </main>
    `;
}

function workspaceMarkup() {
    return `
        <main class="m5-shell">
            <aside class="assignment-rail">
                <div class="rail-header">
                    <div>
                        <p class="eyebrow">Director</p>
                        <h1>Publish Jobs</h1>
                    </div>
                    <button class="icon-button" data-action="logout" title="Sign out">×</button>
                </div>
                <div class="pill-row">
                    <span class="mini-pill">${state.inbox.length} inbox</span>
                    <span class="mini-pill">${state.jobs.length} total</span>
                </div>
                <div class="assignment-list">
                    ${state.jobs.length ? state.jobs.map(jobCard).join('') : '<p class="empty-state">No Director jobs.</p>'}
                </div>
            </aside>
            <section class="workspace-main">
                ${state.error ? `<div class="alert error">${escapeHtml(state.error)}</div>` : ''}
                ${state.notice ? `<div class="alert notice">${escapeHtml(state.notice)}</div>` : ''}
                ${state.activeJob ? jobMarkup(state.activeJob) : '<p class="empty-state">Select a publish job.</p>'}
            </section>
        </main>
    `;
}

function jobCard(job) {
    const active = state.activeJob?.id === job.id ? ' active' : '';
    const windowCode = job.window?.window_code || `Window ${job.window_id}`;

    return `
        <button class="assignment-card${active}" data-action="open-job" data-job-id="${job.id}">
            <span>${escapeHtml(job.status)}</span>
            <strong>${escapeHtml(windowCode)}</strong>
            <small>${escapeHtml(job.window?.sitting?.sitting_date || 'No sitting date')}</small>
            <div class="pill-row">
                <em class="mini-pill">${job.window?.block_count || 0} blocks</em>
                <em class="mini-pill">${escapeHtml(job.crc_pdf_path ? 'CRC ready' : 'CRC pending')}</em>
            </div>
        </button>
    `;
}

function jobMarkup(job) {
    const canPublish = ['queued', 'failed'].includes(job.status) && job.window?.status === 'approved';

    return `
        <header class="slot-header">
            <div>
                <p class="eyebrow">Window ${escapeHtml(job.window?.window_code || job.window_id)}</p>
                <h2>Director Publish Job #${job.id}</h2>
                <div class="pill-row">
                    <span class="status-pill">${escapeHtml(job.status)}</span>
                    <span class="mini-pill">Window ${escapeHtml(job.window?.status || 'missing')}</span>
                    <span class="mini-pill">${job.window?.block_count || 0} blocks</span>
                </div>
            </div>
            <div class="slot-actions">
                <button data-action="refresh-job">Refresh</button>
                <button data-action="publish-job" ${canPublish ? '' : 'disabled'}>Publish</button>
            </div>
        </header>
        ${job.last_error ? `<div class="alert error">${escapeHtml(job.last_error)}</div>` : ''}
        <section class="media-stage">
            <div class="section-heading">
                <h3>CRC and Digital Sansad</h3>
                <span>${escapeHtml(job.finished_at || job.queued_at || '')}</span>
            </div>
            <div class="detail-grid">
                <div><strong>CRC PDF</strong><span>${escapeHtml(job.crc_pdf_path || 'Pending')}</span></div>
                <div><strong>Sansad URL</strong><span>${job.sansad_url ? `<a href="${escapeAttribute(job.sansad_url)}">${escapeHtml(job.sansad_url)}</a>` : 'Pending'}</span></div>
                <div><strong>Director</strong><span>${escapeHtml(job.director?.name || state.me?.name || 'Unassigned')}</span></div>
            </div>
        </section>
        <section class="editor-surface">
            <div class="section-heading">
                <h3>Publish Audit</h3>
                <span>${state.audit.length} events</span>
            </div>
            <div class="assignment-list">
                ${state.audit.length ? state.audit.map(auditRow).join('') : '<p class="empty-state">No publish audit events yet.</p>'}
            </div>
        </section>
    `;
}

function auditRow(log) {
    return `
        <article class="assignment-card">
            <span>${escapeHtml(log.action)}</span>
            <strong>${escapeHtml(log.created_at || '')}</strong>
            <small>${escapeHtml(log.this_hash || '')}</small>
        </article>
    `;
}

function bind(root) {
    root.querySelector('[data-form="login"]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        login(root, event.currentTarget);
    });

    root.querySelectorAll('[data-action="logout"]').forEach((button) => button.addEventListener('click', () => logout(root)));
    root.querySelectorAll('[data-action="open-job"]').forEach((button) => button.addEventListener('click', async () => {
        await openJob(button.dataset.jobId);
        render(root);
    }));
    root.querySelectorAll('[data-action="refresh-job"]').forEach((button) => button.addEventListener('click', async () => {
        await loadQueues();
        await loadJobLog();
        render(root);
    }));
    root.querySelectorAll('[data-action="publish-job"]').forEach((button) => button.addEventListener('click', () => publishJob(root)));
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

export { initDirectorWorkspace };
