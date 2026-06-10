# API Security Policies & Implementation Guide

This document outlines the 22 layers of security implemented in this FastAPI backend. It serves as a reference for developers, security auditors, and DevOps teams.

## 1. Authentication & Identity
1. **Encrypted Tokens (JWE - AES256GCM)**: Access and Refresh tokens are nested (JWS signed, then JWE encrypted) ensuring payloads are completely opaque to clients and attackers.
2. **Two-Factor Authentication (TOTP)**: High-tier account security using Time-Based One-Time Passwords (e.g., Google Authenticator, Authy).
3. **Hashed HTTP-Only Secure Cookies**: Refresh tokens are stored in strict HTTP-only cookies, shielding them from Cross-Site Scripting (XSS) attacks.
4. **Double-Submit Cookie Pattern (CSRF Protection)**: Mitigates Cross-Site Request Forgery attacks by requiring a matching `X-CSRF-Token` header for cookie-authenticated routes.
5. **Action Tokens**: Specialized, short-lived, signed, and encrypted tokens used strictly for specific flows like password resets and email verification.

## 2. Anti-Abuse & Brute Force Prevention
6. **Account Lockout Policy**: Tracks failed login attempts via Redis and automatically locks the account for 15 minutes after 5 consecutive failures.
7. **Distributed Rate Limiting**: Uses a Redis-backed Fixed Window Counter to apply granular rate limits (e.g., 5 req/min for login, 3 req/min for registration) to prevent DDoS and automated credential stuffing.
8. **Captcha Verification**: Requires visual challenge-response verification for the login process to block automated bots.
9. **Pwned Password Verification (k-Anonymity)**: Checks user passwords against the 'Have I Been Pwned' database using a secure hash-prefix comparison to prevent the use of globally compromised credentials.
10. **Strict OWASP Password Policies**: Enforces a minimum of 10 characters, requiring a mix of uppercase, lowercase, numbers, and special symbols.

## 3. Session & Access Control
11. **Granular Dynamic RBAC**: A complete Role-Based Access Control engine supporting dynamic creation of roles, permissions, and user assignments, evaluated per-request.
12. **Real-time Access Token Blacklisting**: Intercepts active access tokens during logout and adds them to a Redis blacklist for instant session termination (true kill-switch).
13. **Device & Session Binding**: Cryptographically binds refresh tokens to the client's IP address and User-Agent. If the token is hijacked and used on a different device, it is automatically revoked.
14. **User Active Session Management**: Provides APIs for users to view all active devices/sessions and remotely revoke unauthorized access.
15. **Password History & Expiration**: Enforces a 90-day password expiration policy and maintains a `PasswordHistory` ledger to prevent users from reusing any of their last 5 passwords.

## 4. Observability & Data Protection
16. **Automated Audit Logging**: A custom middleware that silently tracks every state-changing action (POST, PATCH, DELETE), recording the User ID, action, resource, IP, and status code to the database.
17. **Request Correlation IDs**: Assigns a unique `X-Request-ID` to every incoming request, passing it through logs and response headers for flawless end-to-end traceability.
18. **Sensitive Data Log Masking**: A custom Python logging formatter that intercepts application output and automatically redacts secrets (passwords, tokens, keys) before they are printed to stdout or log files.

## 5. Infrastructure & Code Quality
19. **Security Headers Middleware**: Hardens browser interactions by injecting `Strict-Transport-Security` (HSTS), `Content-Security-Policy` (CSP), `X-Frame-Options` (anti-clickjacking), and `X-Content-Type-Options` (anti-sniffing).
20. **Centralized Exception Handling**: Intercepts all application errors (validation, auth, business logic) and converts them into safe, standardized `IResponse` JSON objects, preventing technical stack traces from leaking to the client.
21. **Docker Container Hardening**: The Dockerfile is configured to create an unprivileged, non-root `appuser`. The FastAPI process runs under this restricted profile to prevent container-escape vulnerabilities.
22. **Automated Pytest Security Suite**: An automated test suite using `httpx` to verify that RBAC borders, Account Lockouts, Captchas, and Password Policies function perfectly, acting as a tripwire against future code regressions.

---
*System Status: 100% Production-Ready / "Fort Knox" Compliance Level.*
