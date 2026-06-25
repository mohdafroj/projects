const TOKEN_KEY = 'vani_setu_api_token';

const state = {
    token: window.localStorage.getItem(TOKEN_KEY) || '',
    me: null,
    roles: [],
    permissions: [],
    destinations: [],
    error: '',
    notice: '',
    mode: 'checking',
};

const DESTINATIONS = [
    {
        role: 'reporter',
        label: 'M5 Reporter',
        description: 'House slot reporting, block correction, speaker edits, audio close and submission.',
        path: '/app/reporter',
    },
    {
        role: 'translator',
        label: 'M10 Translator',
        description: 'E&T translation assignments, glossary control, AI assist and finalisation.',
        path: '/app/translator',
    },
    {
        role: 'supervisor',
        label: 'M17 Reviewer',
        description: 'Supervisor review, return context and forwarding to the next lane.',
        path: '/app/reviewer',
    },
    {
        role: 'sg',
        label: 'M16 SG',
        description: 'Secretary General decision windows and governed final actions.',
        path: '/app/sg',
    },
    {
        role: 'director',
        label: 'M18 Director',
        description: 'Director publication queue and final release workflow.',
        path: '/app/director',
    },
    {
        role: 'synopsis_writer',
        label: 'Synopsis Writer',
        description: 'E&T synopsis drafting from accepted chunks or long pasted text.',
        path: '/app/synopsis',
    },
];

function initAppRouterWorkspace(root) {
    render(root);
    boot(root);
}

async function boot(root) {
    if (! state.token) {
        state.mode = 'login';
        render(root);
        return;
    }

    try {
        await loadMe();
        routeOrRender(root);
    } catch (error) {
        if (error.status === 401) {
            clearSession();
        } else {
            state.error = error.message || 'App entry could not be loaded.';
            state.mode = 'login';
        }
        render(root);
    }
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
    state.mode = 'checking';
    render(root);

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
        state.permissions = payload.permissions || [];
        window.localStorage.setItem(TOKEN_KEY, state.token);
        routeOrRender(root);
    } catch (error) {
        state.error = error.message || 'Login failed.';
        state.mode = 'login';
        render(root);
    }
}

async function logout(root) {
    try {
        if (state.token) {
            await api('/api/auth/logout', { method: 'POST' });
        }
    } catch (error) {
        // Local logout still applies when the token has expired.
    }

    clearSession();
    render(root);
}

async function loadMe() {
    const payload = await api('/api/me');
    state.me = payload.user;
    state.roles = payload.roles || [];
    state.permissions = payload.permissions || [];
}

function routeOrRender(root) {
    state.destinations = destinationsForRoles(state.roles);

    if (state.destinations.length === 1) {
        window.location.assign(state.destinations[0].path);
        return;
    }

    state.mode = state.destinations.length > 1 ? 'choose' : 'blocked';
    render(root);
}

function destinationsForRoles(roles) {
    const roleSet = new Set(roles || []);
    const destinations = DESTINATIONS.filter((destination) => roleSet.has(destination.role));

    if (roleSet.has('admin')) {
        destinations.push({
            role: 'admin-review',
            label: 'Admin Review',
            description: 'Open the reviewer workspace with admin-level access.',
            path: '/app/reviewer',
        });
        destinations.push({
            role: 'admin-synopsis',
            label: 'Admin Synopsis',
            description: 'Open the synopsis workspace with admin-level access.',
            path: '/app/synopsis',
        });
    }

    return destinations;
}

function clearSession() {
    window.localStorage.removeItem(TOKEN_KEY);
    state.token = '';
    state.me = null;
    state.roles = [];
    state.permissions = [];
    state.destinations = [];
    state.error = '';
    state.notice = '';
    state.mode = 'login';
}

function render(root) {
    if (state.mode === 'checking') {
        root.innerHTML = '<main class="workspace-loading">Loading app entry...</main>';
        return;
    }

    if (state.mode === 'choose') {
        root.innerHTML = chooseMarkup();
    } else if (state.mode === 'blocked') {
        root.innerHTML = blockedMarkup();
    } else {
        root.innerHTML = loginMarkup();
    }

    bind(root);
}

function bind(root) {
    const form = root.querySelector('[data-router-login]');
    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            login(root, form);
        });
    }

    root.querySelectorAll('[data-destination]').forEach((button) => {
        button.addEventListener('click', () => {
            window.location.assign(button.dataset.destination);
        });
    });

    root.querySelector('[data-router-logout]')?.addEventListener('click', () => logout(root));
}

function loginMarkup() {
    return `
        <main class="login-shell">
            <section class="login-panel">
                <p class="eyebrow">Authenticated app entry</p>
                <h1>Vani Setu</h1>
                <form class="login-form" data-router-login>
                    <label>
                        Employee ID
                        <input name="employee_id" autocomplete="username" required>
                    </label>
                    <label>
                        Password
                        <input name="password" type="password" autocomplete="current-password" required>
                    </label>
                    ${state.error ? `<p class="form-error">${escapeHtml(state.error)}</p>` : ''}
                    <button type="submit">Sign in</button>
                </form>
            </section>
        </main>
    `;
}

function chooseMarkup() {
    return `
        <main class="login-shell">
            <section class="login-panel router-panel">
                <div class="rail-header">
                    <div>
                        <p class="eyebrow">Choose workspace</p>
                        <h1>${escapeHtml(state.me?.name || 'Vani Setu')}</h1>
                    </div>
                    <button class="icon-button" type="button" data-router-logout title="Sign out">X</button>
                </div>
                <div class="assignment-list">
                    ${state.destinations.map((destination) => `
                        <button class="assignment-card" type="button" data-destination="${destination.path}">
                            <span>${escapeHtml(destination.label)}</span>
                            <strong>${escapeHtml(destination.path)}</strong>
                            <small>${escapeHtml(destination.description)}</small>
                        </button>
                    `).join('')}
                </div>
            </section>
        </main>
    `;
}

function blockedMarkup() {
    return `
        <main class="login-shell">
            <section class="login-panel">
                <p class="eyebrow">No workspace assigned</p>
                <h1>Access unavailable</h1>
                <p class="muted">This account is authenticated but has no supported role workspace.</p>
                <button type="button" data-router-logout>Sign out</button>
            </section>
        </main>
    `;
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

export { initAppRouterWorkspace };
