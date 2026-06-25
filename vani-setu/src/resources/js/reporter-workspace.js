const TOKEN_KEY = 'vani_setu_api_token';
const DEMO_AUDIO_URL = '/demo-audio/RTI_Playbook_se_sarkaari_record_nikalwayein.m4a';

const LANG_LABELS = {
    en: 'English',
    hi: 'Hindi',
    ta: 'Tamil',
    ur: 'Urdu',
    bn: 'Bengali',
    mr: 'Marathi',
};

const state = {
    token: window.localStorage.getItem(TOKEN_KEY) || '',
    me: null,
    roles: [],
    permissions: [],
    assignments: [],
    activeAssignment: null,
    activeSlot: null,
    selectedMemberIdByBlock: {},
    expungeMarksByBlock: {},
    members: [],
    loading: false,
    error: '',
    notice: '',
    authMode: 'checking',
    audio: {
        status: 'idle',
        speed: '1',
        sourceUrl: DEMO_AUDIO_URL,
        fileName: 'RTI_Playbook_se_sarkaari_record_nikalwayein.m4a',
        currentMs: 0,
        durationMs: 0,
        activeBlockId: null,
    },
};

function initReporterWorkspace(root) {
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
        if (! isReporter()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        await Promise.all([loadAssignments(), loadMembers()]);

        const initialSlot = root.dataset.initialSlot;
        if (initialSlot) {
            const assignment = state.assignments.find((item) => String(item.slot.id) === String(initialSlot));
            if (assignment) {
                await openAssignment(assignment.id);
            } else {
                await openSlot(initialSlot);
            }
        } else if (state.assignments.length > 0) {
            await openAssignment(state.assignments[0].id);
        }
    } catch (error) {
        if (error.status === 401) {
            clearSession();
            state.authMode = 'login';
        } else {
            state.error = error.message || 'Workspace could not be loaded.';
        }
    }

    render(root);
}

function isReporter() {
    return state.roles.includes('reporter');
}

async function api(path, options = {}) {
    const headers = {
        Accept: 'application/json',
        ...(options.headers || {}),
    };

    if (state.token) {
        headers.Authorization = `Bearer ${state.token}`;
    }

    if (options.body && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(path, {
        ...options,
        headers,
        body: options.body && !(options.body instanceof FormData) ? JSON.stringify(options.body) : options.body,
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

    const employeeId = form.querySelector('[name="employee_id"]').value.trim();
    const password = form.querySelector('[name="password"]').value;

    try {
        const payload = await api('/api/auth/login', {
            method: 'POST',
            body: {
                employee_id: employeeId,
                password,
            },
        });

        state.token = payload.token;
        state.me = payload.user;
        state.roles = payload.roles || [];
        state.permissions = payload.permissions || [];
        window.localStorage.setItem(TOKEN_KEY, state.token);

        if (! isReporter()) {
            state.authMode = 'forbidden';
            render(root);
            return;
        }

        state.authMode = 'workspace';
        window.history.replaceState({}, '', '/app/reporter');
        await Promise.all([loadAssignments(), loadMembers()]);
        if (state.assignments[0]) {
            await openAssignment(state.assignments[0].id);
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
    window.history.replaceState({}, '', '/app/login');
    render(root);
}

function clearSession() {
    window.localStorage.removeItem(TOKEN_KEY);
    state.token = '';
    state.me = null;
    state.roles = [];
    state.permissions = [];
    state.assignments = [];
    state.activeAssignment = null;
    state.activeSlot = null;
    state.authMode = 'login';
}

async function loadMe() {
    const payload = await api('/api/me');
    state.me = payload.user;
    state.roles = payload.roles || [];
    state.permissions = payload.permissions || [];
}

async function loadAssignments() {
    state.assignments = await api('/api/me/assignments');
}

async function loadMembers(query = '') {
    const params = new URLSearchParams();
    if (query) {
        params.set('q', query);
    }
    const payload = await api(`/api/members?${params.toString()}`);
    state.members = payload.data || payload;
}

async function openAssignment(assignmentId) {
    state.error = '';
    state.notice = '';
    state.activeAssignment = state.assignments.find((assignment) => assignment.id === Number(assignmentId)) || null;
    if (! state.activeAssignment) {
        return;
    }

    await openSlot(state.activeAssignment.slot.id);
}

async function openSlot(slotId) {
    state.activeSlot = await api(`/api/slots/${slotId}`);
    state.activeSlot.blocks = (state.activeSlot.blocks || []).sort((left, right) => left.sequence - right.sequence);
    state.audio.sourceUrl = state.activeSlot.reporter_audio_url || DEMO_AUDIO_URL;
    state.audio.fileName = state.activeSlot.reporter_audio_url ? `Slot ${state.activeSlot.code} audio` : 'RTI_Playbook_se_sarkaari_record_nikalwayein.m4a';
    state.audio.activeBlockId = null;
    window.history.replaceState({}, '', `/app/reporter/slots/${slotId}`);
}

function currentLaneBlocks() {
    if (! state.activeSlot) {
        return [];
    }

    const lang = state.activeAssignment?.lang_role;
    return (state.activeSlot.blocks || []).filter((block) => ! lang || block.original_lang === lang);
}

function isReadOnly() {
    return state.activeAssignment?.status === 'committed' || ! ['reporter', 'returned'].includes(state.activeAssignment?.workflow_stage);
}

async function saveBlock(root, blockId) {
    const block = findBlock(blockId);
    const editor = document.querySelector(`[data-block-editor="${blockId}"]`);
    if (! block || ! editor) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        const updated = await api(`/api/blocks/${blockId}`, {
            method: 'PUT',
            body: {
                text: editor.value,
                version: block.version,
            },
        });

        replaceBlock(updated);
        state.notice = 'Draft saved.';
    } catch (error) {
        if (error.status === 409) {
            state.error = `Version conflict. Current saved version is ${error.payload.current_version}. Reload the slot before saving this block.`;
            block.conflict = error.payload;
        } else {
            state.error = error.message || 'Block could not be saved.';
        }
    }

    render(root);
}

async function setSpeaker(root, blockId) {
    const memberId = Number(state.selectedMemberIdByBlock[blockId] || 0) || null;
    state.error = '';
    state.notice = '';

    try {
        const updated = await api(`/api/blocks/${blockId}/speaker`, {
            method: 'PUT',
            body: {
                member_id: memberId,
                custom_member_id: null,
            },
        });

        replaceBlock(updated);
        state.notice = 'Speaker updated.';
    } catch (error) {
        state.error = error.message || 'Speaker could not be updated.';
    }

    render(root);
}

async function createCustomMember(root, blockId, form) {
    state.error = '';
    state.notice = '';

    try {
        const custom = await api(`/api/blocks/${blockId}/custom-members`, {
            method: 'POST',
            body: {
                name_en: form.querySelector('[name="name_en"]').value,
                name_hi: form.querySelector('[name="name_hi"]').value,
                role_title: form.querySelector('[name="role_title"]').value || null,
            },
        });

        const block = findBlock(blockId);
        if (block) {
            block.member_id = null;
            block.member = null;
            block.custom_member_id = custom.id;
            block.custom_member = custom;
        }

        state.notice = 'Custom speaker saved.';
    } catch (error) {
        state.error = error.message || 'Custom speaker could not be saved.';
    }

    render(root);
}

async function sendAssignedAudioToAsr(root) {
    if (! state.activeSlot || isReadOnly()) {
        return;
    }

    state.audio.status = 'uploading';
    state.error = '';
    state.notice = '';
    render(root);

    try {
        const response = await fetch(state.audio.sourceUrl);
        if (! response.ok) throw new Error(`Assigned audio unavailable: ${response.status}`);
        const blob = await response.blob();
        const file = new File([blob], state.audio.fileName || 'assigned-audio.m4a', { type: blob.type || 'audio/mp4' });
        const body = new FormData();
        body.append('seq', '1');
        body.append('chunk', file);

        await api(`/api/reporter/slot/${state.activeSlot.id}/audio-chunk`, { method: 'POST', body });
        state.audio.status = 'processing';
        const result = await api(`/api/reporter/slot/${state.activeSlot.id}/audio-close`, { method: 'POST' });
        state.audio.status = result.closed ? 'closed' : 'needs-audio';
        state.notice = result.asr?.transcript ? 'ASR transcript created from assigned audio.' : 'Assigned audio sent to ASR. Existing transcript is ready for vetting.';
        await openSlot(state.activeSlot.id);
    } catch (error) {
        state.audio.status = 'unavailable';
        state.error = error.message || 'Assigned audio could not be sent to ASR.';
    }

    render(root);
}

async function submitSlot(root) {
    if (! state.activeSlot || ! state.activeAssignment || isReadOnly()) {
        return;
    }

    state.error = '';
    state.notice = '';

    try {
        state.activeAssignment = await api(`/api/slots/${state.activeSlot.id}/commit`, {
            method: 'POST',
            body: {
                lang_role: state.activeAssignment.lang_role,
            },
        });
        await loadAssignments();
        await openSlot(state.activeSlot.id);
        state.notice = 'Submitted to supervisor.';
    } catch (error) {
        state.error = error.message || 'Slot could not be submitted.';
    }

    render(root);
}

function findBlock(blockId) {
    return (state.activeSlot?.blocks || []).find((block) => block.id === Number(blockId));
}

function replaceBlock(updated) {
    if (! state.activeSlot) {
        return;
    }

    state.activeSlot.blocks = state.activeSlot.blocks.map((block) => block.id === updated.id ? updated : block);
}

function speakerName(block) {
    return block.member?.name_en || block.custom_member?.name_en || 'Unattributed';
}

function formatTime(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const minutes = String(Math.floor(total / 60)).padStart(2, '0');
    const seconds = String(total % 60).padStart(2, '0');
    return `${minutes}:${seconds}`;
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
                <h1>M5 Reporter</h1>
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
                <h1>Reporter role required</h1>
                <p class="muted">This workspace is limited to authenticated M5 reporters. API authorization remains the source of truth for every action.</p>
                <button data-action="logout">Use another account</button>
            </section>
        </main>
    `;
}

function workspaceHtml() {
    const assignments = state.assignments.map((assignment) => assignmentCardHtml(assignment)).join('');
    const slot = state.activeSlot;
    const blocks = currentLaneBlocks().map((block) => blockHtml(block)).join('');
    const returnEvent = state.activeAssignment?.latest_return_event;

    return `
        <main class="m5-shell">
            <aside class="assignment-rail">
                <div class="rail-header">
                    <div>
                        <p class="eyebrow">M5 Reporter</p>
                        <h1>${escapeHtml(state.me?.name || 'Reporter')}</h1>
                    </div>
                    <button class="icon-button" data-action="logout" title="Sign out" aria-label="Sign out">Out</button>
                </div>
                <div class="assignment-list">${assignments || '<p class="empty-state">No active assignments.</p>'}</div>
            </aside>
            <section class="workspace-main">
                ${state.error ? `<div class="alert error">${escapeHtml(state.error)}</div>` : ''}
                ${state.notice ? `<div class="alert notice">${escapeHtml(state.notice)}</div>` : ''}
                ${slot ? `
                    <header class="slot-header">
                        <div>
                            <p class="eyebrow">${escapeHtml(slot.sitting?.session_no || '')} / ${escapeHtml(slot.sitting?.sitting_no || '')}</p>
                            <h2>Slot ${escapeHtml(slot.code)} &middot; ${escapeHtml(slot.topic)}</h2>
                        </div>
                        <div class="slot-actions">
                            <span class="status-pill">${escapeHtml(state.activeAssignment?.workflow_stage || slot.status)}</span>
                            <button data-action="submit-slot" ${isReadOnly() ? 'disabled' : ''}>Submit</button>
                        </div>
                    </header>
                    ${returnEvent ? `<div class="return-note"><strong>Returned by supervisor:</strong> ${escapeHtml(returnEvent.reason || 'No reason recorded.')}</div>` : ''}
                    ${mediaHtml(slot)}
                    <section class="editor-surface">
                        <div class="section-heading">
                            <h3>Transcript</h3>
                            <span>${blocks ? currentLaneBlocks().length : 0} blocks &middot; ${escapeHtml(LANG_LABELS[state.activeAssignment?.lang_role] || state.activeAssignment?.lang_role || 'All')}</span>
                        </div>
                        ${blocks || '<p class="empty-state">No blocks are available in this assignment lane.</p>'}
                    </section>
                ` : '<p class="empty-state">Select an assignment to begin.</p>'}
            </section>
        </main>
    `;
}

function assignmentCardHtml(assignment) {
    const active = state.activeAssignment?.id === assignment.id ? 'active' : '';
    const returned = assignment.workflow_stage === 'returned' ? '<span class="mini-pill warn">Returned</span>' : '';
    const committed = assignment.status === 'committed' ? '<span class="mini-pill">Read only</span>' : '';

    return `
        <button class="assignment-card ${active}" data-action="open-assignment" data-assignment-id="${assignment.id}">
            <span>Slot ${escapeHtml(assignment.slot.code)}</span>
            <strong>${escapeHtml(assignment.slot.topic)}</strong>
            <small>${escapeHtml(LANG_LABELS[assignment.lang_role] || assignment.lang_role)} &middot; ${escapeHtml(assignment.status)}</small>
            <span class="pill-row">${returned}${committed}</span>
        </button>
    `;
}

function mediaHtml(slot) {
    const duration = state.audio.durationMs || slot.duration_ms || 0;
    const progress = Math.max(0, Math.min(100, Math.round((state.audio.currentMs / Math.max(duration || 1, 1)) * 100)));
    const readOnlyText = isReadOnly() ? 'Submitted lanes are read only.' : 'Assigned audio is ready for ASR and transcript vetting.';

    return `
        <section class="media-stage">
            <div class="media-topline">
                <div>
                    <p class="eyebrow">Assigned audio</p>
                    <h3>${escapeHtml(readOnlyText)}</h3>
                    <small>${escapeHtml(state.audio.fileName || 'No audio selected')}</small>
                </div>
                <span class="status-pill">${escapeHtml(state.audio.status)}</span>
            </div>
            <audio class="source-audio" data-role="source-audio" controls preload="metadata" src="${escapeHtml(state.audio.sourceUrl)}"></audio>
            <div class="playback-row">
                <button class="round-button" data-action="play-pause" title="Play or pause assigned audio" aria-label="Play or pause assigned audio">&gt;</button>
                <div class="waveform" aria-label="Timeline"><span style="width:${progress}%"></span></div>
                <select data-action="speed" aria-label="Playback speed">
                    ${['0.75', '1', '1.25', '1.5', '2'].map((speed) => `<option value="${speed}" ${state.audio.speed === speed ? 'selected' : ''}>${speed}x</option>`).join('')}
                </select>
                <button data-action="audio-close" ${isReadOnly() ? 'disabled' : ''}>Send to ASR</button>
                <a class="button-link" href="${escapeHtml(state.audio.sourceUrl)}" download>Download</a>
            </div>
            <div class="timeline-meta">
                <span>${formatTime(state.audio.currentMs)}</span>
                <span>${formatTime(duration)}</span>
            </div>
        </section>
    `;
}

function blockHtml(block) {
    const changed = block.ai_text !== block.text;
    const readonly = isReadOnly() ? 'readonly' : '';
    const conflict = block.conflict ? `<div class="conflict-box">Server text: ${escapeHtml(block.conflict.current_text)}</div>` : '';
    const expunged = state.expungeMarksByBlock[block.id] ? 'checked' : '';

    return `
        <article class="block-row ${changed ? 'changed' : ''} ${state.audio.activeBlockId === block.id ? 'active' : ''}" data-action="seek-block" data-block-id="${block.id}" data-start-ms="${block.start_ms}">
            <div class="block-meta">
                <span>#${block.sequence}</span>
                <strong>${escapeHtml(speakerName(block))}</strong>
                <small>${formatTime(block.start_ms)}-${formatTime(block.end_ms)} &middot; v${block.version} &middot; edits ${block.reporter_edit_count}</small>
            </div>
            <textarea data-block-editor="${block.id}" ${readonly}>${escapeHtml(block.text)}</textarea>
            ${changed ? `<div class="change-strip"><span>ASR</span>${escapeHtml(block.ai_text)}</div>` : ''}
            ${conflict}
            <div class="block-tools">
                <select data-action="select-member" data-block-id="${block.id}" ${isReadOnly() ? 'disabled' : ''}>
                    <option value="">Speaker roster</option>
                    ${state.members.map((member) => `<option value="${member.id}" ${block.member_id === member.id ? 'selected' : ''}>${escapeHtml(member.name_en)} (${escapeHtml(member.roster_id)})</option>`).join('')}
                </select>
                <button data-action="set-speaker" data-block-id="${block.id}" ${isReadOnly() ? 'disabled' : ''}>Set speaker</button>
                <button data-action="save-block" data-block-id="${block.id}" ${isReadOnly() ? 'disabled' : ''}>Save draft</button>
                <label class="inline-check"><input type="checkbox" data-action="expunge" data-block-id="${block.id}" ${expunged} ${isReadOnly() ? 'disabled' : ''}> Expunge mark</label>
            </div>
            <details class="custom-speaker">
                <summary>Custom speaker</summary>
                <form data-action="custom-member" data-block-id="${block.id}">
                    <input name="name_en" placeholder="Name EN" required ${isReadOnly() ? 'disabled' : ''}>
                    <input name="name_hi" placeholder="Name HI" required ${isReadOnly() ? 'disabled' : ''}>
                    <input name="role_title" placeholder="Role" ${isReadOnly() ? 'disabled' : ''}>
                    <button ${isReadOnly() ? 'disabled' : ''}>Save</button>
                </form>
            </details>
            <div class="draft-note unavailable">
                <label>Reporter comments</label>
                <textarea disabled>Comment API unavailable in this stage.</textarea>
            </div>
        </article>
    `;
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
    root.querySelectorAll('[data-action="save-block"]').forEach((button) => button.addEventListener('click', () => saveBlock(root, button.dataset.blockId)));
    root.querySelectorAll('[data-action="set-speaker"]').forEach((button) => button.addEventListener('click', () => setSpeaker(root, button.dataset.blockId)));
    root.querySelectorAll('[data-action="select-member"]').forEach((select) => {
        select.addEventListener('change', () => {
            state.selectedMemberIdByBlock[select.dataset.blockId] = select.value;
        });
    });
    root.querySelectorAll('[data-action="custom-member"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            createCustomMember(root, form.dataset.blockId, form);
        });
    });
    root.querySelectorAll('[data-action="expunge"]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            state.expungeMarksByBlock[checkbox.dataset.blockId] = checkbox.checked;
        });
    });
    root.querySelectorAll('[data-action="audio-close"]').forEach((button) => button.addEventListener('click', () => sendAssignedAudioToAsr(root)));
    root.querySelectorAll('[data-action="play-pause"]').forEach((button) => button.addEventListener('click', () => togglePlayback(root)));
    root.querySelectorAll('[data-action="seek-block"]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('textarea, select, button, input, details, summary, form')) return;
            seekToBlock(root, row.dataset.blockId, Number(row.dataset.startMs || 0));
        });
    });
    root.querySelectorAll('[data-action="submit-slot"]').forEach((button) => button.addEventListener('click', () => submitSlot(root)));
    root.querySelectorAll('[data-action="speed"]').forEach((select) => {
        select.addEventListener('change', () => {
            state.audio.speed = select.value;
            const audio = root.querySelector('[data-role="source-audio"]');
            if (audio) audio.playbackRate = Number(select.value);
        });
    });
    root.querySelectorAll('[data-role="source-audio"]').forEach((audio) => bindAudio(root, audio));
}

function bindAudio(root, audio) {
    audio.playbackRate = Number(state.audio.speed);
    audio.addEventListener('loadedmetadata', () => {
        state.audio.durationMs = Number.isFinite(audio.duration) ? Math.round(audio.duration * 1000) : 0;
        state.audio.status = state.audio.status === 'idle' ? 'ready' : state.audio.status;
        const status = root.querySelector('.media-stage .status-pill');
        const lastTime = root.querySelector('.timeline-meta span:last-child');
        if (status) status.textContent = state.audio.status;
        if (lastTime) lastTime.textContent = formatTime(state.audio.durationMs || state.activeSlot?.duration_ms || 0);
    }, { once: true });
    audio.addEventListener('timeupdate', () => {
        state.audio.currentMs = Math.round(audio.currentTime * 1000);
        const fill = root.querySelector('.waveform span');
        const duration = state.audio.durationMs || Math.round((audio.duration || 0) * 1000) || state.activeSlot?.duration_ms || 1;
        if (fill) fill.style.width = `${Math.max(0, Math.min(100, Math.round((state.audio.currentMs / duration) * 100)))}%`;
        const firstTime = root.querySelector('.timeline-meta span:first-child');
        if (firstTime) firstTime.textContent = formatTime(state.audio.currentMs);
    });
}

function togglePlayback(root) {
    const audio = root.querySelector('[data-role="source-audio"]');
    if (! audio) return;
    if (audio.paused) audio.play();
    else audio.pause();
}

function seekToBlock(root, blockId, startMs) {
    const audio = root.querySelector('[data-role="source-audio"]');
    state.audio.activeBlockId = Number(blockId);
    if (audio) {
        audio.currentTime = Math.max(0, startMs) / 1000;
        audio.playbackRate = Number(state.audio.speed);
        audio.play().catch(() => undefined);
    }
    root.querySelectorAll('.block-row.active').forEach((row) => row.classList.remove('active'));
    root.querySelector(`[data-block-id="${blockId}"]`)?.classList.add('active');
}

export { initReporterWorkspace };
