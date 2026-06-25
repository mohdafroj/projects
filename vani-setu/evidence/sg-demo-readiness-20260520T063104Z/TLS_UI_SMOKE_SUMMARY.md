# Vani Setu TLS UI Smoke Summary

Timestamp: 20260520T061100Z

Base URL: `https://vanisetu.rajyasabha.digital`

Result: PASS

Validated:

- Reporter, Supervisor, Chief EN, Chief HI, JS, SG, and Director walkthroughs.
- Laravel API over private TLS through Caddy.
- Sanctum CSRF route over TLS returns `204` and sets cookies.
- Reverb websocket route over TLS returns `101 Switching Protocols` with `--http1.1`.

Network note:

- Browser smoke forced `vanisetu.rajyasabha.digital` to `127.0.0.1` because the internal `10.21.217.17` HTTPS surface still routes some requests to an existing uvicorn service outside Caddy. This is documented in `docs/NETWORK_HANDOFF.md`.
