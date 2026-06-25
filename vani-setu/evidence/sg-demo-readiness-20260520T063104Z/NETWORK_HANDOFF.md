# Network Handoff: Vani Setu Public TLS

Audience: Rajya Sabha network / infrastructure team

## Current State

- Application host private IP: `10.21.217.17`
- Private fallback TLS is active through Caddy with an mkcert certificate.
- Public ACME is pending because Let's Encrypt cannot resolve valid public A/AAAA records for `vanisetu.rajyasabha.digital`.
- The public/external HTTPS surface currently responds as a separate `uvicorn` service. Caddy must own the public `:443` listener for production rollout.

## Required Public DNS

Create records for:

- `vanisetu.rajyasabha.digital`
- Optional UAT: `uat.vanisetu.rajyasabha.digital`

Records:

- `A` record to the public NAT address that forwards to `10.21.217.17`.
- `AAAA` record only if IPv6 is routed to the same Caddy endpoint. If IPv6 is not available, do not publish AAAA.

## ACME Challenge

Preferred:

- Public TCP `80` reaches the Caddy container on `10.21.217.17`.
- Caddy will complete HTTP-01 automatically.

Alternative:

- Provide an internal DNS API token for the `rajyasabha.digital` zone.
- Caddy can then be configured for DNS-01 challenge without public port `80`.

## Port Ownership

- Caddy must own public TCP `443`.
- The existing `uvicorn` service on the external HTTPS surface must move to another port or be removed from that public binding.
- Private fallback Caddy currently listens on host `:443` and `:80`; UAT Caddy listens on `:9443` because `:8443` is occupied by `sds-fake-parichay`.

## Firewall

- TCP `443`: inbound from anywhere.
- TCP `80`: inbound from Let's Encrypt validation ranges is acceptable, or from anywhere during issuance.
- No direct public access is required for Postgres, Redis, Meilisearch, Horizon worker, ML gateway, or realtime sidecar.

## Certificate Policy Option

If Let's Encrypt is not permissible for government domains, provide a corporate certificate from C-DAC or NIC CA for:

- `vanisetu.rajyasabha.digital`
- `uat.vanisetu.rajyasabha.digital`

Deliver PEM certificate and key, or certificate chain plus documented key custody procedure.

## External Verification Commands

Run these from outside the Rajya Sabha network:

```bash
dig +short A vanisetu.rajyasabha.digital
dig +short AAAA vanisetu.rajyasabha.digital
curl -I http://vanisetu.rajyasabha.digital/.well-known/acme-challenge/probe
curl -I https://vanisetu.rajyasabha.digital
curl -s https://vanisetu.rajyasabha.digital/api/health
openssl s_client -connect vanisetu.rajyasabha.digital:443 -servername vanisetu.rajyasabha.digital </dev/null 2>/dev/null | openssl x509 -noout -issuer -subject -dates
```

Expected after cutover:

- DNS returns the public NAT for `10.21.217.17`.
- `curl -I https://vanisetu.rajyasabha.digital` returns `200`.
- `/api/health` returns `{"status":"ok","service":"vani-setu",...}`.
- Certificate issuer is either Let's Encrypt, C-DAC, or NIC CA as approved.
