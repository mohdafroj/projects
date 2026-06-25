<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vanisetu · Listen (per-language)</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 0; background: #0f1115; color: #e7e9ee; }
    header { padding: 14px 18px; background: #161a22; border-bottom: 1px solid #262c38; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    header h1 { font-size: 1rem; margin: 0; font-weight: 600; }
    .controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-left: auto; }
    input, select, button { background: #0f1115; color: #e7e9ee; border: 1px solid #303747; border-radius: 6px; padding: 6px 9px; font-size: 0.9rem; }
    button { cursor: pointer; background: #2a6df0; border-color: #2a6df0; }
    .status { font-size: 0.8rem; padding: 2px 8px; border-radius: 10px; }
    .status.on { background: #173a23; color: #5fdc8b; }
    .status.off { background: #3a1717; color: #ff8b8b; }
    main { padding: 18px; max-width: 820px; margin: 0 auto; }
    .line { padding: 10px 12px; margin: 8px 0; background: #161a22; border: 1px solid #232938; border-radius: 8px; }
    .line .t { font-size: 1.15rem; line-height: 1.5; }
    .line .m { font-size: 0.72rem; color: #8b93a6; margin-top: 4px; }
    .empty { color: #8b93a6; text-align: center; margin-top: 40px; }
    .status.play { background: #16263f; color: #7fb4ff; }
    .line.playing { border-color: #2a6df0; }
    button.muted { background: #3a1717; border-color: #3a1717; }
  </style>
</head>
<body>
  <header>
    <h1>वाणीसेतु · Listen</h1>
    <span id="conn" class="status off">disconnected</span>
    <div class="controls">
      <input id="session" type="number" placeholder="Session #" style="width:110px">
      <select id="lang">
        @foreach ($languages as $l)
          <option value="{{ $l['code'] }}">{{ $l['label'] }}</option>
        @endforeach
      </select>
      <button id="join">Listen</button>
      <button id="mute" title="Mute / unmute audio" aria-pressed="false">🔊</button>
      <span id="nowplaying" class="status play" style="display:none"></span>
    </div>
  </header>
  <main>
    <div id="feed"><div class="empty">Pick a session and your language, then press Listen.</div></div>
  </main>

  <script src="https://unpkg.com/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
  <script src="https://unpkg.com/laravel-echo@1.16.1/dist/echo.iife.js"></script>
  <script>
    const REVERB = {
      key: @json($reverbKey),
      host: @json($reverbHost),
      port: @json((int) $reverbPort),
      scheme: @json($reverbScheme),
    };
    const $ = (id) => document.getElementById(id);
    const params = new URLSearchParams(location.search);
    if (params.get('session')) $('session').value = params.get('session');
    if (params.get('lang')) $('lang').value = params.get('lang');

    let echo = null, channel = null, currentName = null;
    const audioEl = new Audio();

    // Sequential audio queue: in a live interpretation booth, translated
    // sentences must play one after another. Setting audioEl.src on each
    // arrival would cut off the sentence currently playing, so we FIFO-queue
    // the URLs and advance only when the previous clip ends (or errors).
    const audioQueue = [];
    let playing = false, muted = false, playingEl = null;
    function setNowPlaying() {
      const np = $('nowplaying');
      if (muted) { np.style.display = ''; np.textContent = 'muted'; return; }
      if (playing) { np.style.display = ''; np.textContent = audioQueue.length ? `playing · ${audioQueue.length} queued` : 'playing'; }
      else { np.style.display = 'none'; }
    }
    function pumpQueue() {
      if (playing || muted || !audioQueue.length) { setNowPlaying(); return; }
      const item = audioQueue.shift();
      playing = true;
      if (playingEl) playingEl.classList.remove('playing');
      playingEl = item.el || null;
      if (playingEl) playingEl.classList.add('playing');
      setNowPlaying();
      try {
        audioEl.src = item.url;
        audioEl.play().catch(() => { playing = false; pumpQueue(); });
      } catch (_) { playing = false; pumpQueue(); }
    }
    audioEl.addEventListener('ended', () => { playing = false; if (playingEl) playingEl.classList.remove('playing'); playingEl = null; pumpQueue(); });
    audioEl.addEventListener('error', () => { playing = false; pumpQueue(); });

    function setConn(ok, label) {
      const el = $('conn'); el.textContent = label; el.className = 'status ' + (ok ? 'on' : 'off');
    }
    function ensureEcho() {
      if (echo) return echo;
      echo = new Echo({
        broadcaster: 'reverb',
        key: REVERB.key,
        wsHost: REVERB.host,
        wsPort: REVERB.port,
        wssPort: REVERB.port,
        forceTLS: REVERB.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
      });
      const pusher = echo.connector && echo.connector.pusher;
      if (pusher) {
        pusher.connection.bind('connected', () => setConn(true, 'connected'));
        pusher.connection.bind('disconnected', () => setConn(false, 'disconnected'));
        pusher.connection.bind('error', () => setConn(false, 'error'));
      }
      return echo;
    }
    function render(e) {
      const feed = $('feed');
      if (feed.querySelector('.empty')) feed.innerHTML = '';
      const div = document.createElement('div');
      div.className = 'line';
      const t = document.createElement('div'); t.className = 't'; t.textContent = ''; // ephemeral: no transcript text shown
      const m = document.createElement('div'); m.className = 'm';
      m.textContent = `${e.language_code || ''} · seg ${e.segment_id ?? '-'} · ${e.status || ''}${e.audio_url ? ' · audio' : ''}`;
      div.appendChild(t); div.appendChild(m);
      feed.prepend(div);
      if (e.audio_url) { audioQueue.push({ url: e.audio_url, el: div }); pumpQueue(); }
    }
    function join() {
      const session = ($('session').value || '').trim();
      const lang = $('lang').value;
      if (!session) { alert('Enter a session number'); return; }
      ensureEcho();
      const safeLang = lang.replace(/[:.\/]/g, '-');
      const name = `s2s-live.${session}.${safeLang}`;
      if (channel && currentName) { try { echo.leave(currentName); } catch (_) {} }
      $('feed').innerHTML = '<div class="empty">Listening for ' + lang + ' on session ' + session + '…</div>';
      channel = echo.channel(name).listen('.output', render);
      currentName = name;
    }
    $('mute').addEventListener('click', () => {
      muted = !muted;
      const b = $('mute');
      b.textContent = muted ? '🔇' : '🔊';
      b.setAttribute('aria-pressed', muted ? 'true' : 'false');
      b.classList.toggle('muted', muted);
      if (muted) {
        // Stop current playback and drop the backlog so unmuting resumes with
        // live speech, not a stale queue from while the listener was muted.
        try { audioEl.pause(); } catch (_) {}
        audioQueue.length = 0;
        playing = false;
        if (playingEl) { playingEl.classList.remove('playing'); playingEl = null; }
      }
      setNowPlaying();
      pumpQueue();
    });
    $('join').addEventListener('click', join);
    if (params.get('session') && params.get('lang')) join();
  </script>
</body>
</html>
