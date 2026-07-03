# Vani Setu — Kubernetes Deployment Architecture & Project Analysis

This document provides a comprehensive analysis of the project setup, development workflow, and CI/CD pipelines of **Vani Setu** (वाणी सेतु), followed by a detailed architectural blueprint for deploying the application on a **Kubernetes (k3s)** cluster.

---

## 1. Architectural & Project Setup Analysis

Vani Setu is the AI-powered language bridge for the Rajya Sabha Digital Ecosystem (Sampurna Digital Sansad - SDS). Its role is real-time transcription, translation, and interpretation across the 22 scheduled languages of India.

The project currently runs as a multi-container stack orchestrated via Docker Compose. The components of this stack, as defined in [docker-compose.yml](file:///d:/works/projects/vani-setu/docker-compose.yml), are:

```
                                  +-----------------------+
                                  |    Browser Client     |
                                  +-----------+-----------+
                                              | HTTPS/WebSockets (443/80)
                                              v
                                  +-----------+-----------+
                                  |   Caddy Reverse Proxy |
                                  +-----------+-----------+
                                              |
      +-----------------+---------------------+-----------------+-----------------+
      | /api, /sanctum  | /v1                 | /collab         | /app, /apps     |
      v                 v                     v                 v                 v
+-----+-----+     +-----+-----+        +------+------+    +-----+-----+     +-----+-----+
|    web    |     |ml-gateway |        |  realtime-  |    |  reverb   |     |   MinIO   |
|  (Nginx)  |     | (FastAPI) |        |   sidecar   |    | (Laravel) |     |  (Object  |
+-----+-----+     +-----+-----+        | (Node.js)   |    +-----+-----+     |  Storage) |
      |                 |              +------+------+          |           +-----------+
      v                 |                     |                 |
+-----+-----+           |                     |                 |
|    app    |<----------+---------------------+-----------------+
| (PHP-FPM) |
+--+-----+--+
   |     |
   |     |     +-------------+
   |     +---->|    redis    |<-- (Cache, Queue, WebSockets)
   |           +-------------+
   |           +-------------+
   +---------->|  postgres   |<-- (Application DB)
   |           +-------------+
   |           +-------------+
   +---------->| meilisearch |<-- (Search Engine)
               +-------------+
```

### Component Analysis
1.  **Frontend SPA:** A static Vite-powered Single Page Application (SPA) currently configured under `/opt/vanisetu/frontend`. In production, static assets are built and served directly by Caddy.
2.  **Laravel API (`web` + `app`):**
    *   `web` ([web.Dockerfile](file:///d:/works/projects/vani-setu/docker/web.Dockerfile)): Runs Nginx to proxy web requests.
    *   `app` ([app.Dockerfile](file:///d:/works/projects/vani-setu/docker/app.Dockerfile)): Runs PHP-FPM 8.3 with custom configurations. This container hosts the Laravel core logic.
3.  **Queue Workers (`worker` + `audit`):**
    *   `worker`: Runs Laravel Horizon (`php artisan horizon`) for asynchronous background processing (e.g. scheduling, translation triggers).
    *   `audit`: Runs a dedicated queue listener (`php artisan queue:work redis --queue=audit`) to handle tamper-proof cryptographic audit log sealing.
4.  **Laravel Reverb (`reverb`):** Runs the Laravel Reverb WebSocket server (`php artisan reverb:start`) to handle live broadcast events to client browsers.
5.  **ML Gateway (`ml-gateway`):** A FastAPI server ([Dockerfile](file:///d:/works/projects/vani-setu/ml-gateway/Dockerfile)) that coordinates Speech-to-Text (STT), Machine Translation, and Text-to-Speech (TTS). It relies primarily on outbound HTTPS connections to Sarvam AI (`api.sarvam.ai`) and has a fallback to the on-premise GPU-accelerated Tijori ASR service.
6.  **Real-Time Sidecar (`realtime-sidecar`):** A Node.js/TypeScript collaborative server ([Dockerfile](file:///d:/works/projects/vani-setu/realtime-sidecar/Dockerfile)) managing CRDT document synchronization. It connects directly to PostgreSQL.
7.  **Meilisearch:** Used as the search engine for transcripts and documents.
8.  **Caddy:** Serves as the SSL edge, routes paths, and serves the static frontend assets.
9.  **Postgres & Redis:** Shared database of record and in-memory cache/broker.
10. **MinIO:** Handles secure, on-premise, erasure-coded object storage (e.g. raw/transcribed audio) to enforce sovereignty guidelines (data never leaves sovereign Indian cloud).

---

## 2. Development & Deployment Pipeline Analysis

As outlined in the GitLab pipeline configuration [.gitlab-ci.yml](file:///d:/works/projects/vani-setu/.gitlab-ci.yml), the lifecycle phases are:

### Development Lifecycle
*   **Local Setup:** Runs entirely inside Docker Compose. Databases (Postgres, Redis, Meilisearch) are run as local containers. Local mounts link `./src` to `/var/www/html` for rapid iteration.
*   **Lints & Tests:**
    *   `lint:ml-gateway`: Scans code using `ruff`.
    *   `lint:realtime-sidecar`: Runs ESLint on NodeJS sidecar code.
    *   `lint:laravel`: Formats and checks syntax using Laravel Pint.
    *   `test:ml-gateway`: Executes unit tests using `pytest`.

### Deployment Lifecycle
*   **Container Builder:** Kaniko (`gcr.io/kaniko-project/executor`) builds Docker images from the repositories and pushes them to the local GitLab container registry (`registry.gitlab.sds.local`). Kaniko runs daemonless, ideal for secure environments.
*   **Infrastructure Provisioning:** Managed via Terraform under the [terraform/](file:///d:/works/projects/vani-setu/terraform/) folder. It installs `k3s` (v1.35.4) on the target node, creates namespaces, sets up storage classes (`local-path`), boots Argo CD, and installs platform-wide tools (Cert-Manager, Linkerd, Vault, Harbor, Prometheus/Grafana).
*   **Workload Deployment:**
    *   *Legacy Mode:* Triggered via a shell runner (`vani-prod-deploy`) executing `docker compose pull && docker compose up -d` on the production server `10.21.217.17`.
    *   *Kubernetes Transition Mode (Active):* The Docker Compose deploy job is gated with `when: never`. Workloads are being migrated to the `k3s` Kubernetes cluster on host `10.21.217.132` using **Argo CD GitOps** pulling from the approved Git repo `platform-gitops.git`.

---

## 3. Kubernetes Deployment Design

The architecture team has planned a **Transitional Laravel Chart** (`vani-setu-laravel`) to migrate the Laravel monolith as-is without rewriting the stack into Python/FastAPI (which is the long-term "Stage-6" plan).

The following design maps the Vani Setu components to native Kubernetes primitives:

```
                                    +----------------------+
                                    |    Ingress Route     |
                                    | (Traefik/Nginx/Mesh) |
                                    +----------+-----------+
                                               |
      +-----------------+----------------------+-----------------+-----------------+
      | /api, /sanctum  | /v1                 | /collab         | /app, /apps     |
      v                 v                     v                 v                 v
+-----+-----+     +-----+-----+        +------+------+    +-----+-----+     +-----+-----+
| web-pod   |     |mlgateway  |        |  realtime-  |    |  reverb   |     | Platform  |
| (Nginx &  |     |Deployment |        |   sidecar   |    |Deployment |     |   MinIO   |
| PHP-FPM)  |     +-----+-----+        | Deployment  |    +-----+-----+     +-----------+
+-----+-----+           |              +------+------+          |
      |                 |                     |                 |
      v                 v                     v                 v
+-----+-----------------+---------------------+-----------------+-----------------+
|                                Kubernetes Service Mesh                          |
+-----+-----------------+---------------------+-----------------+-----------------+
      |                 |                     |
      v                 v                     v
+-----+-----+     +-----+-----+        +------+------+
| Platform  |     | Platform  |        | MeiliSearch |
| Postgres  |     |  Redis    |        | StatefulSet |
+-----------+     +-----------+        +-------------+
```

### 3.1 Namespace Strategy
The deployment uses two separated namespaces:
*   `vani-laravel` (Transitional): Contains the transitional Laravel PHP workloads, local databases, and temporary resources.
*   `vani-system` (Reserved): Holds the eventual Stage-6 native FastAPI microservices.
This allows both deployments to coexist. We execute the final traffic cutover by swapping the Ingress path mappings.

### 3.2 Pod & Workload Mapping
1.  **Web & App Co-Location (Nginx + PHP-FPM):**
    *   *Design:* Deploy them in a single Pod with two containers: `web` (Nginx) and `app` (PHP-FPM).
    *   *Reasoning:* PHP-FPM requires direct filesystem access to serve static PHP templates, and Nginx communicates with FPM over TCP (`localhost:9000`) or a shared Unix socket. Putting them in one Pod allows them to share an `emptyDir` volume for code files and socks.
2.  **Horizon & Audit Workers:**
    *   *Design:* Deploy as separate Kubernetes `Deployments`.
    *   *Reasoning:* Background queues have completely different resource-scaling properties compared to HTTP traffic. They do not expose ports and do not need Nginx sidecars.
3.  **Laravel Reverb:**
    *   *Design:* Run as a separate `Deployment` with a dedicated ClusterIP Service.
    *   *Reasoning:* WebSockets require persistent connections. Reverb should use `sessionAffinity: ClientIP` on its service to prevent connections from dropping during scale events.
4.  **ML Gateway & Real-Time Sidecar:**
    *   *Design:* Standard horizontal `Deployments` backed by `Services`.
5.  **Stateful Services (Postgres, Redis, Meilisearch):**
    *   *Pre-Prod/UAT:* Deployed using lightweight `StatefulSets` with `local-path` PersistentVolumeClaims (PVCs).
    *   *Production:* Databases are externalized to the platform-managed shared PostgreSQL cluster (`sb-pg`) and Redis clusters. Secrets are dynamically sourced via HashiCorp Vault.

---

## 4. Kubernetes Manifest Templates

Here are the target manifests for deploying the transitional Vani Setu stack.

### 4.1 Nginx config ConfigMap ([configmap-nginx.yaml](file:///d:/works/projects/vani-setu/docs/MIGRATION_PHASE3_PLAN.md#L520))
Provides the Nginx reverse proxy configuration to route to the PHP-FPM container running on localhost.

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: vani-nginx-config
  namespace: vani-laravel
data:
  default.conf: |
    server {
        listen 80;
        server_name vanisetu.rajyasabha.digital;
        root /var/www/html/public;
        index index.php index.html;
        server_tokens off;
        client_max_body_size 512m;

        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header Referrer-Policy "strict-origin-when-cross-origin" always;

        charset utf-8;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        error_page 404 /index.php;

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_hide_header X-Powered-By;
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }
    }
```

### 4.2 Web & App Pod Deployment ([deployment-web-app.yaml](file:///d:/works/projects/vani-setu/docs/MIGRATION_PHASE3_PLAN.md#L523-L524))
Deploys Nginx and PHP-FPM in a single Pod sharing static assets and Unix volumes.

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: vani-web-app
  namespace: vani-laravel
  labels:
    app.kubernetes.io/name: vani-web-app
spec:
  replicas: 2
  selector:
    matchLabels:
      app: vani-web-app
  template:
    metadata:
      labels:
        app: vani-web-app
      annotations:
        # Vault integration annotations to dynamically inject secrets
        vault.hashicorp.com/agent-inject: "true"
        vault.hashicorp.com/role: "vani-setu-role"
        vault.hashicorp.com/agent-inject-secret-.env: "kv/staging/vani-app-secrets"
        vault.hashicorp.com/agent-inject-template-.env: |
          {{- with secret "kv/staging/vani-app-secrets" -}}
          APP_KEY={{ .Data.data.app_key }}
          DB_PASSWORD={{ .Data.data.db_password }}
          REDIS_PASSWORD={{ .Data.data.redis_password }}
          MEILI_MASTER_KEY={{ .Data.data.meili_master_key }}
          REVERB_APP_SECRET={{ .Data.data.reverb_app_secret }}
          REALTIME_AUDIT_SECRET={{ .Data.data.realtime_audit_secret }}
          ASR_INGEST_SECRET={{ .Data.data.asr_ingest_secret }}
          SARVAM_API_KEY={{ .Data.data.sarvam_api_key }}
          {{- end -}}
    spec:
      imagePullSecrets:
        - name: regcred
      containers:
        # 1. PHP-FPM Application Container
        - name: app
          image: registry.gitlab.sds.local/vani/setu/app:latest
          imagePullPolicy: Always
          workingDir: /var/www/html
          envFrom:
            - configMapRef:
                name: vani-app-env
          resources:
            requests:
              memory: "512Mi"
              cpu: "250m"
            limits:
              memory: "1Gi"
              cpu: "500m"
          volumeMounts:
            - name: shared-html
              mountPath: /var/www/html
          ports:
            - containerPort: 9000
              name: fpm

        # 2. Nginx Web Server Container (Sidecar)
        - name: web
          image: registry.gitlab.sds.local/vani/setu/web:latest
          imagePullPolicy: Always
          ports:
            - containerPort: 80
              name: http
          volumeMounts:
            - name: shared-html
              mountPath: /var/www/html
            - name: nginx-config
              mountPath: /etc/nginx/conf.d
      volumes:
        - name: shared-html
          emptyDir: {}
        - name: nginx-config
          configMap:
            name: vani-nginx-config
```

### 4.3 Ingress Routing configuration ([ingressroute.yaml](file:///d:/works/projects/vani-setu/docs/MIGRATION_PHASE3_PLAN.md#L535))
Using Traefik or standard Nginx Ingress routes to dispatch requests dynamically based on URL patterns.

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: vani-ingress
  namespace: vani-laravel
  annotations:
    cert-manager.io/cluster-issuer: "letsencrypt-prod-dns01"
    nginx.ingress.kubernetes.io/client-max-body-size: "512m"
    nginx.ingress.kubernetes.io/websocket-services: "vani-reverb"
spec:
  ingressClassName: nginx
  tls:
    - hosts:
        - vanisetu.rajyasabha.digital
      secretName: vani-tls-public
  rules:
    - host: vanisetu.rajyasabha.digital
      http:
        paths:
          # WebSockets (Laravel Reverb)
          - path: /app
            pathType: Prefix
            backend:
              service:
                name: vani-reverb
                port:
                  number: 8080
          # WebSockets (Laravel Reverb) fallback
          - path: /apps
            pathType: Prefix
            backend:
              service:
                name: vani-reverb
                port:
                  number: 8080
          # Real-time CRDT sidecar
          - path: /collab
            pathType: Prefix
            backend:
              service:
                name: vani-realtime-sidecar
                port:
                  number: 1234
          # ML Gateway (ASR / translation)
          - path: /v1
            pathType: Prefix
            backend:
              service:
                name: vani-ml-gateway
                port:
                  number: 8000
          # Static SPA, Laravel API, and normal HTTP web pages
          - path: /
            pathType: Prefix
            backend:
              service:
                name: vani-web
                port:
                  number: 80
```

### 4.4 Automated Database Migrations ([job-migrate.yaml](file:///d:/works/projects/vani-setu/docs/MIGRATION_PHASE3_PLAN.md#L538))
Runs migrations safely before or during updates utilizing Helm Hooks.

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: vani-db-migrate
  namespace: vani-laravel
  annotations:
    "helm.sh/hook": post-install,post-upgrade
    "helm.sh/hook-weight": "1"
    "helm.sh/hook-delete-policy": hook-succeeded
spec:
  template:
    spec:
      restartPolicy: OnFailure
      containers:
        - name: migrate
          image: registry.gitlab.sds.local/vani/setu/app:latest
          command: ["php", "artisan", "migrate", "--force"]
          envFrom:
            - configMapRef:
                name: vani-app-env
```

---

## 5. Deployment Step-by-Step

Here is the operational process for onboarding Vani Setu on Kubernetes:

### Step 1: Platform Preparation (via Terraform)
Use the Terraform configs in `terraform/envs/staging` (or `/prod`) to:
1.  Verify host baseline checks and firewall ports.
2.  Enable Kubernetes node provisioning (`enable_k3s = true`).
3.  Deploy Cert-Manager, Linkerd Service Mesh, HashiCorp Vault, Harbor, and Argo CD.
4.  Configure namespaces: `kubectl create namespace vani-laravel`.

### Step 2: Secret Provisioning (via Vault / K8s Secrets)
Before deploying workloads, create the secret `vani-app-secrets` (or map dynamic secrets via Vault integration) in the `vani-laravel` namespace. This matches [docs/PRODUCTION_READINESS.md](file:///d:/works/projects/vani-setu/docs/PRODUCTION_READINESS.md#L5-L19):
*   `app-key`
*   `db-password` (or dynamic credentials from Vault Postgres engine)
*   `redis-password`
*   `meili-master-key`
*   `reverb-app-key`/`reverb-app-secret`
*   `realtime-audit-secret`
*   `asr-ingest-secret`
*   `sarvam-api-key`

### Step 3: CI/CD Pipeline Transition
Modify [.gitlab-ci.yml](file:///d:/works/projects/vani-setu/.gitlab-ci.yml) to retire the legacy Compose-based SSH runner and execute Helm upgrades on commit:

```yaml
deploy:prod:
  stage: deploy
  image: alpine/helm:3.14.2
  before_script:
    - mkdir -p ~/.kube
    - echo "$KUBECONFIG_BASE64" | base64 -d > ~/.kube/config
  script:
    - helm upgrade --install vani-laravel ./deploy/vani-setu-laravel/helm/vani-setu-laravel/
      --namespace vani-laravel
      --values ./deploy/vani-setu-laravel/helm/vani-setu-laravel/values.yaml
      --set image.app.tag=$CI_COMMIT_SHORT_SHA
      --set image.web.tag=$CI_COMMIT_SHORT_SHA
      --set image.mlGateway.tag=$CI_COMMIT_SHORT_SHA
      --set image.realtimeSidecar.tag=$CI_COMMIT_SHORT_SHA
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
```

### Step 4: Verification and Cutover
1.  **Smoke Tests:** Verify endpoints (`/v1/health`, `/api/health`, `/collab`) return 200 OK using kubectl port-forwarding or local headers.
2.  **DNS Switch:** Swap DNS `vanisetu.rajyasabha.digital` (or in-cluster Ingress) to route to the Traefik/Nginx LoadBalancer IP.
3.  **Audit Genesis Seeding:** Execute the post-install genesis job `job-audit-genesis.yaml` to register the first cryptographic row in the audit database.

---

## 6. Production Readiness Controls

To ensure high-availability and security in production, enforce the following guidelines:
1.  **Database Isolation:** Switch from container-local Postgres/Redis to the platform's dedicated `sb-pg` and shared Redis cluster using secure TLS handshakes.
2.  **MinIO Erasure-Coding:** Maintain local MinIO buckets (`vani-audio-raw-rs`, `vani-artifacts-non-sensitive`) with erasure-coding enabled.
3.  **Observability Scrapes:** Configure Prometheus endpoints to scrape:
    *   Laravel Horizon: `/api/horizon/metrics` for queue tracking.
    *   Reverb: websocket count metrics.
    *   ML Gateway: Latency indicators, failure rates on Sarvam API, and fallback counts.
4.  **Security Mesh:** Enable Linkerd service mesh injection (`linkerd.io/inject: enabled`) on all workloads to secure inter-container communication with mutual TLS (mTLS).
