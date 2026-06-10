# FastAPI Security & Architecture: Interview Q&A

This document contains a curated list of technical interview questions and answers based on the advanced security and architectural patterns implemented in this FastAPI project. It is designed to help engineers prepare for senior-level backend and security interviews.

---

## 1. Authentication & Identity

### Q: How do you secure JWT payloads from being decoded by the client?
**A:** Standard JWTs (JSON Web Signatures or JWS) are only Base64 encoded, meaning anyone can read the payload. To prevent this, we use **JWE (JSON Web Encryption)**. The payload is first signed (JWS) to ensure integrity, and then wrapped in an AES256GCM encryption envelope (JWE) using a secret key. This makes the token completely opaque to the client.

### Q: Explain the Double-Submit Cookie pattern and why it's used.
**A:** It is used to prevent **Cross-Site Request Forgery (CSRF)** attacks when using HTTP-Only cookies for session management. 
When a user logs in, the server sets two cookies: an HTTP-Only cookie (e.g., `refresh_token`) and a readable cookie (e.g., `csrf_token`). On subsequent state-changing requests, the frontend must read the `csrf_token` cookie and attach its value to a custom header (like `X-CSRF-Token`). The server validates that the header matches the cookie. Since a malicious third-party site cannot read cookies from your domain due to the Same-Origin Policy, they cannot attach the required header, thus neutralizing the attack.

### Q: How do you handle password resets securely?
**A:** We use **Action Tokens**. These are short-lived, signed, and encrypted JWTs with a specific `purpose` claim (e.g., `purpose="password-reset"`). When verified, the password is changed, and crucially, all existing active sessions (refresh tokens) for that user are immediately revoked from the database to prevent account takeover.

---

## 2. Anti-Abuse & Brute Force Prevention

### Q: How do you implement an Account Lockout policy in a distributed system?
**A:** We use **Redis** to track failed login attempts keyed by the username (e.g., `attempts:john_doe`). If the count reaches a threshold (e.g., 5 attempts), we set a `lockout:john_doe` key in Redis with a Time-To-Live (TTL) of 15 minutes. The authentication middleware checks for this lockout key before even verifying the password, blocking the request instantly.

### Q: What is k-Anonymity, and how is it used in password security?
**A:** k-Anonymity is a property that ensures individual data cannot be distinguished from at least *k-1* other individuals. In password security, we use it to check against the "Have I Been Pwned" database. We hash the user's password using SHA-1, but we only send the **first 5 characters** of the hash to the external API. The API returns hundreds of hashes starting with those 5 characters. We do the final match locally. This proves a password is compromised without ever sending the actual password (or full hash) over the network.

### Q: How do you rate limit APIs using FastAPI?
**A:** By creating a custom FastAPI Dependency that uses the **Fixed Window Counter** algorithm backed by Redis. The dependency generates a Redis key based on the client's IP and the requested route. It increments the counter and sets a TTL. If the counter exceeds the allowed threshold, it raises an `HTTP 429 Too Many Requests` exception with a `retry_after` value based on the remaining Redis TTL.

---

## 3. Session & Access Control

### Q: JWTs are stateless. How do you implement an instant "Kill Switch" or logout for access tokens?
**A:** By implementing an **Access Token Blacklist** using Redis. When a user logs out, we extract the remaining lifespan (TTL) of their current access token. We store the token signature in Redis with an expiration exactly matching that TTL. Our authentication dependency checks this Redis blacklist on every request. This provides instant revocation with zero long-term memory bloat.

### Q: What is Device Binding, and how does it prevent Session Hijacking?
**A:** Device Binding ties a session (Refresh Token) to the physical attributes of the client, such as the IP Address, User-Agent, or a mobile Device ID. When the client attempts to use the refresh token, the server compares the current metadata with the metadata stored at login. If it detects a mismatch (e.g., the User-Agent suddenly changed), it assumes the token was stolen, revokes the session immediately, and forces a re-login.

### Q: Explain Dynamic Role-Based Access Control (RBAC).
**A:** Unlike hardcoded roles (e.g., `if user.role == 'admin'`), Dynamic RBAC abstracts permissions. Roles and Permissions are stored in the database. A Role is a collection of Permissions. A User is assigned Roles. The API routes are protected by a dependency like `require_permission('user:delete')`. This allows administrators to change what "Admin" or "Manager" means in real-time via an API without deploying new code.

---

## 4. Observability & Data Protection

### Q: How do you trace a single request across multiple microservices or logs?
**A:** By implementing a **Request Correlation ID Middleware**. It intercepts incoming requests and generates a unique UUID (`X-Request-ID`), storing it in an asynchronous context variable (`contextvars`). This ID is injected into Audit Logs, application logs, and returned in the HTTP response headers. If a user reports an error with that ID, developers can easily query the logs to see the exact lifecycle of that transaction.

### Q: What are the risks of logging in production, and how do you mitigate them?
**A:** The primary risk is leaking sensitive data (PII, passwords, access tokens) into centralized logging systems (ELK, Datadog). We mitigate this by building a custom `logging.Formatter`. It uses Regular Expressions to intercept log strings and automatically redact known sensitive keys (e.g., replacing password values with `***MASKED***`) before the log is ever written to standard output.

---

## 5. Infrastructure & Architecture

### Q: What are Security Headers, and which ones are essential?
**A:** Security Headers are HTTP response headers that tell the browser how to behave securely. Essential ones include:
*   `Strict-Transport-Security` (HSTS): Forces the browser to use HTTPS.
*   `X-Frame-Options: DENY`: Prevents the site from being framed (Clickjacking).
*   `X-Content-Type-Options: nosniff`: Prevents MIME-sniffing attacks.
*   `Content-Security-Policy` (CSP): Restricts where resources (scripts, images) can be loaded from to prevent Cross-Site Scripting (XSS).

### Q: Why shouldn't a Docker container run as root, and how do you fix it?
**A:** Running as root introduces a "container escape" vulnerability; if an attacker executes arbitrary code inside the container, they have root privileges which might allow them to exploit the host kernel. To fix this, you modify the `Dockerfile` to create an unprivileged user (e.g., `RUN useradd appuser`), change directory ownership to that user, and use the `USER appuser` directive before executing the application.

### Q: What is the benefit of Centralized Exception Handling in an API?
**A:** It ensures consistency and security. By catching a base custom exception (like `AppException`) at the application level, you guarantee that all errors return the exact same JSON structure (e.g., `{success: false, message: ..., data: null}`). More importantly, it acts as a firewall that prevents raw technical stack traces or database errors from leaking to the client, which could reveal system architecture to attackers.
