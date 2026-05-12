---
status: draft
date: 2026-02-24
decision-makers: [ffayard]
consulted: []
informed: []
---

# Migrate the Basil backend (Target API) to the ChapsMind architecture

## Context and problem

The Basil project has a monolithic backend in **PHP 8.4 / Symfony 7.3 / API Platform 4.1** serving all strategic monitoring features (WatchFiles, Documents, Actors, Sources, AI Chat, Collect, DeepSearch). This backend operates autonomously with its own stack (PostgreSQL 17, Elasticsearch 9.1, Valkey 8, RabbitMQ 4, N8N, Mercure/Caddy, Keycloak 26).

In parallel, the **ChapsMind** project is evolving towards a multi-module platform (Screen, Target, Explore, Translation) architected around a **Global Service** (API Gateway + shared services) compliant with ADR-0009. The Target frontend has already begun its migration (ADR-2026-007: Nuxt 4 → Vue 3 with unplugin-vue-router).

**The problem**: how to integrate the Basil backend as a `target` module in the ChapsMind ecosystem while preserving:

- The entirety of the existing DDD business logic (18 bounded contexts)
- Compatibility with the Global Service (gateway, auth, module check)
- Deployment and development autonomy
- Specific services (Elasticsearch, N8N, Mercure, Valkey)

The ChapsMind workspace is migrating to a **monorepo** architecture (ADR-0011), which simplifies Target code integration.

This document covers **17 architectural decisions** and a **5-phase migration plan**.

---

## Decision drivers

- **Architectural consistency**: comply with ADR-0009 (Global Service as Gateway) and the Strangler Fig pattern already adopted by ChapsMind
- **Pre-declared `TARGET` module**: the `ModuleName` enum in `back/app/models/organization.py` already contains `TARGET = "target"`, the `organization_modules` table is ready
- **Team autonomy**: the Target and Screen teams must be able to work and deploy independently
- **Code preservation**: 18 DDD bounded contexts, 35 Doctrine migrations, 23 AI templates, 50+ integration tests — no rewrite
- **Multi-tenancy**: align Target with the ChapsMind Keycloak organization system
- **Performance**: the PHP backend handles WebSocket (Bakus/Collect), SSE streaming (Mercure), and priority queues (RabbitMQ) — these capabilities must be maintained
- **Security**: a single internet entry point (Global Service), internal communication secured by Internal JWT
- **Progressiveness**: phased migration without service interruption

---

## Decisions

This document groups 17 interdependent decisions. Each decision is detailed in its own section below.

**Architecture and integration:**

1. [Integrate Target API code into the ChapsMind monorepo](#decision-1--integrate-target-api-code-into-the-chapsmind-monorepo)
2. [Integrate Target API into monorepo CI/CD via `include:local`](#decision-2--integrate-target-api-into-monorepo-cicd-via-includelocal)
3. [Route `/api/target/*` via the Global Service](#decision-3--route-apitarget-via-the-global-service)
4. [Implement an `InternalJwtAuthenticator` in Symfony](#decision-4--implement-an-internaljwtauthenticator-in-symfony)
5. [Use a dedicated `target_db` database](#decision-5--use-a-dedicated-target_db-database)
6. [Deploy dedicated Elasticsearch, Valkey, N8N instances](#decision-6--deploy-dedicated-elasticsearch-valkey-n8n-instances)
7. [Use Docker Compose profiles for dev isolation](#decision-7--use-docker-compose-profiles-for-dev-isolation)
8. [Have N8N communicate directly with `target` (bypass gateway)](#decision-8--have-n8n-communicate-directly-with-target)
9. [Reuse the `OrganizationModule` module check system](#decision-9--reuse-the-organizationmodule-module-check-system)

**Real-time and streaming:**

10. [Route SSE/Mercure streams via the Global Service with authentication exception](#decision-10--route-ssemercure-streams-via-the-global-service-with-authentication-exception)

**CI/CD and operations:**

11. [Maintain the Basil pipeline CI quality level](#decision-11--maintain-the-basil-pipeline-ci-quality-level)
12. [Configure GitLab runners for the PHP backend](#decision-12--configure-gitlab-runners-for-the-php-backend)
13. [Expose OpenAPI documentation via the Global Service](#decision-13--expose-openapi-documentation-via-the-global-service)
14. [Per-module technical documentation with lightweight centralization](#decision-14--per-module-technical-documentation-with-lightweight-centralization)

**Deployment and migration:**

15. [Adapt the multi-client deployment infrastructure](#decision-15--adapt-the-multi-client-deployment-infrastructure)
16. [Adopt a 5-phase migration plan (Strangler Fig)](#decision-16--adopt-a-5-phase-migration-plan)

**N8N and AI workflows:**

17. [Migrate the N8N ecosystem into the ChapsMind monorepo](#decision-17--migrate-the-n8n-ecosystem-into-the-chapsmind-monorepo)

---

## Decision 1 : Integrate Target API code into the ChapsMind monorepo

### Context

The ChapsMind workspace is migrating to a **monorepo** architecture (ADR-0011). The former submodules (`front/`, `back/`, `global-service/`, `infra/`) are merged into a single repo with the `apps/` structure:

```
chapsmind/
├── apps/
│   ├── front/               # Vue 3 SPA
│   ├── back/                # Python/FastAPI Screen
│   ├── global-service/      # API Gateway
│   └── infra/               # Docker/Infra
├── .gitlab-ci.yml           # CI orchestrator (include:local)
└── docs/
```

The Basil API code (`target/basil/api/`) must be integrated into this structure as `apps/target/`.

**Monorepo benefits for Target** (cf. ADR-0011):

- 1 MR = 1 cross-stack feature (API + front + gateway), instead of 3 coordinated MRs
- Zero submodule ref maintenance (47% of current workspace commits are noise)
- Global `git grep`, unified `git blame`, cross-stack `git bisect`
- Conditional CI (`rules:changes`) — only jobs for modified directories run

### Options considered

#### Option A: Copy code without history

**Description**: copy files from `basil/api/` to `apps/target/` without preserving Git history.

**Pros:**

- Simplicity, no complex Git manipulation

**Cons:**

- Loss of Git history (18 months of commits, 35+ Doctrine migrations)
- `git blame` and `git log` unusable for understanding past decisions
- Loss of traceability for security audits

#### Option B: Import with `git subtree add` preserving history (Retained)

**Description**: use `git subtree add` to import code from `basil/api/` into `apps/target/` preserving the full Git history.

**Pros:**

- **History preserved**: all commits, authors, dates are kept
- `git log -- apps/target/` traces the complete history from Basil
- `git blame` works on every file
- Consistent with the import strategy for other components (ADR-0011 Phase 2)

**Cons:**

- Imported history adds volume to the repo (commits are rewritten with the new prefix)
- More complex initial Git manipulation

#### Option C: Keep Target as a separate repo (submodule or multi-repo)

**Description**: keep an autonomous `mint/target` repo, linked to the monorepo via submodule or by convention.

**Pros:**

- Full Target repo autonomy
- Independent CI/CD

**Cons:**

- **Inconsistent** with the monorepo decision (ADR-0011)
- Falls back into submodule problems (desynchronization, bumps, 3 MRs per feature)
- Excludes Target from monorepo benefits (unified review, CODEOWNERS, single git flow)

### Decision

> **Option B retained**: import Basil API code into `apps/target/` via `git subtree add`.

Import:

```bash
# From basil/, extract API code with history
cd basil/
git subtree split --prefix=api -b target-export

# In the ChapsMind monorepo, import
cd chapsmind/
git subtree add --prefix=apps/target /path/to/basil target-export
```

Resulting structure:

```
chapsmind/
├── apps/
│   ├── front/               # Vue 3 SPA
│   ├── back/                # Python/FastAPI Screen (will be renamed "screen")
│   ├── global-service/      # API Gateway
│   ├── target/              # PHP/Symfony Target ← NEW
│   └── infra/               # Docker/Infra
├── .gitlab-ci.yml           # CI orchestrator
├── .gitlab/CODEOWNERS       # Automatic review by domain
└── docs/
```

CODEOWNERS (addition):

```
# .gitlab/CODEOWNERS (addition)
apps/target/             @chapsmind/team-target
```

### Consequences

- **Positive**: history preserved, consistent with monorepo, atomic cross-stack MRs, CODEOWNERS per team
- **Negative**: initial `git subtree` manipulation, imported history adds volume
- **Neutral**: the `basil` repo is archived after migration (not deleted)

### Confirmation

- `git log -- apps/target/` shows the complete Basil API code history
- `apps/target/` contains the same tree structure as `basil/api/`
- CODEOWNERS assigns `@chapsmind/team-target` for changes in `apps/target/`

---

## Decision 2 : Integrate Target API into monorepo CI/CD via `include:local`

### Context

The ChapsMind monorepo (ADR-0011) adopts a distributed CI architecture based on `include:local`: each application owns its own `.gitlab-ci.yml` included by the root file. Jobs execute conditionally via `rules:changes` — only modified directories trigger jobs.

This pattern **eliminates submodule problems** (ref desynchronization, bump commits, maintenance MRs) in favor of atomic commits and a unified pipeline.

The Target API code (PHP/Symfony) must integrate into this pattern, while the other apps are in Python or TypeScript.

### Options considered

#### Option A: Standalone CI pipeline in `apps/target/` without monorepo integration

**Description**: `apps/target/.gitlab-ci.yml` is self-sufficient, triggered by a pipeline trigger.

**Pros:**

- Full isolation
- No modification of the root `.gitlab-ci.yml`

**Cons:**

- Loses the atomic MR advantage (CI doesn't validate cross-stack in a single pipeline)
- Inconsistent with the monorepo pattern (ADR-0011)

#### Option B: `include:local` with conditional execution (Retained)

**Description**: the root `.gitlab-ci.yml` includes `apps/target/.gitlab-ci.yml`. Target jobs use `rules:changes` to only run when `apps/target/**/*` is modified.

**Pros:**

- **Consistent** with the monorepo pattern (ADR-0011 section 5.2)
- Single pipeline per MR, even if the MR touches front + target + global-service
- Each team maintains its CI independently in its directory
- Trivial addition: 1 `include` line in the root file

**Cons:**

- The monorepo pipeline must support GitLab CI `services:` (PG, ES, Valkey) for PHP jobs

### Decision

> **Option B retained**: `include:local` in the monorepo.

Addition to the root file:

```yaml
# chapsmind/.gitlab-ci.yml (addition)
include:
  - local: apps/front/.gitlab-ci.yml
  - local: apps/back/.gitlab-ci.yml
  - local: apps/global-service/.gitlab-ci.yml
  - local: apps/target/.gitlab-ci.yml # ← NEW
  - local: infra/.gitlab-ci.yml
```

Target API CI file:

```yaml
# apps/target/.gitlab-ci.yml
# Maintained by the Target team, isolated from the rest

.target-changes: &target-changes
  changes:
    - apps/target/**/*

target:lint:
  stage: lint
  image: $CI_REGISTRY_IMAGE/ci-images/php-target:latest
  rules:
    - <<: *target-changes
  script:
    - cd apps/target && composer install --no-scripts
    - vendor/bin/ecs check
    - vendor/bin/phpstan analyse --level=9

target:test:
  stage: test
  image: $CI_REGISTRY_IMAGE/ci-images/php-target:latest
  services:
    - postgres:17
    - elasticsearch:9.1.0
    - valkey/valkey:8
  rules:
    - <<: *target-changes
  script:
    - cd apps/target && composer install
    - php vendor/bin/phpunit

target:security:
  stage: test
  rules:
    - <<: *target-changes
  script:
    - cd apps/target && composer audit
    - trivy image --severity CRITICAL,HIGH $TARGET_IMAGE

target:build:
  stage: build
  rules:
    - <<: *target-changes
  image: docker:29-cli
  script:
    - docker build -t $CI_REGISTRY_IMAGE/target:$CI_COMMIT_SHORT_SHA apps/target/
    - docker push $CI_REGISTRY_IMAGE/target:$CI_COMMIT_SHORT_SHA

target:deploy:integration:
  stage: deploy:integration
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
      <<: *target-changes
  environment:
    name: integration
  script:
    - docker tag $CI_REGISTRY_IMAGE/target:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/target:integration
    - docker push $CI_REGISTRY_IMAGE/target:integration
```

### Consequences

- **Positive**: unified CI in the monorepo, conditional execution, cross-stack review in a single pipeline, trivial addition (1 include line)
- **Negative**: runners must support heavy CI services (PG + ES + Valkey) for PHP jobs
- **Neutral**: the Target team maintains its CI file independently in its directory

### Confirmation

- The root `.gitlab-ci.yml` file includes `apps/target/.gitlab-ci.yml`
- An MR touching only `apps/front/` triggers no `target:*` jobs
- An MR touching `apps/target/` triggers lint, test, security, build jobs
- CI services (PG 17, ES 9.1, Valkey 8) work on ChapsMind runners

---

## Decision 3 : Route `/api/target/*` via the Global Service

### Context

ChapsMind's ADR-0009 defines the **Global Service as the sole entry point** for all frontend requests. Routing is based on URL prefix:

- `/api/screen/*` → Screen Service (Python backend)
- `/api/tokens/*`, `/api/folders/*`, etc. → handled directly by the Global Service
- `/api/target/*` → **Target Service (to be defined)**

The Global Service is currently being implemented (spec `2026-01-14-global-service-api-gateway`). Routing to `target` must integrate into this pattern.

### Options considered

#### Option A: Expose `target` directly to the frontend via Nginx

**Description**: add an Nginx rule `location /api/target/ { proxy_pass http://target:8002; }` without going through the Global Service.

**Pros:**

- Simpler, no dependency on the Global Service
- Less latency (one fewer hop)

**Cons:**

- Violates ADR-0009 (Global Service = sole entry point)
- No centralized JWT validation
- No module check (`OrganizationModule`)
- Two entry points to secure
- The frontend must handle multiple API URLs

#### Option B: Route via the Global Service with HTTP proxy (Retained)

**Description**: the Global Service receives `/api/target/*`, validates the JWT, checks the module check, then forwards the request to `target:8002` with an Internal JWT.

**Pros:**

- Compliant with ADR-0009
- Centralized JWT validation
- Integrated module check (does the organization have the Target module enabled?)
- Unified frontend (single API URL)
- Simplified security (only the Global Service is exposed)

**Cons:**

- Additional latency (~1-5ms for internal proxy)
- Dependency on the Global Service (single point of failure)
- The Global Service must be implemented and functional (blocker)

#### Option C: gRPC communication between Global Service and Target

**Description**: use gRPC instead of REST for internal communication.

**Pros:**

- Superior performance (protobuf, HTTP/2 multiplexing)
- Strong API contracts (`.proto` files)

**Cons:**

- PHP/Symfony lacks mature native gRPC support (unlike Python/FastAPI)
- Disproportionate implementation complexity
- ADR-0009 plans gRPC for Phase 6, not first iteration

### Decision

> **Option B retained**: HTTP routing via the Global Service.

The proxy will be implemented in the Global Service as follows:

```python
# global-service/app/api/routes/target_proxy.py

@router.api_route("/api/target/{path:path}", methods=["GET", "POST", "PUT", "PATCH", "DELETE"])
async def proxy_to_target(request: Request, path: str):
    """Forward request to Target API service."""
    internal_jwt = create_internal_jwt(
        user_id=request.state.user_id,
        organization_id=request.state.organization_id,
        username=request.state.username,
    )
    async with httpx.AsyncClient() as client:
        response = await client.request(
            method=request.method,
            url=f"{TARGET_SERVICE_URL}/api/{path}",
            headers={
                "Authorization": f"Bearer {internal_jwt}",
                "Content-Type": request.headers.get("Content-Type", "application/json"),
            },
            content=await request.body(),
            params=request.query_params,
        )
        return Response(
            content=response.content,
            status_code=response.status_code,
            headers=dict(response.headers),
        )
```

`target` will listen on port **8002** (by convention: backend=8000, global-service=8001, target=8002).

### Consequences

- **Positive**: consistent architecture, centralized security, integrated module check
- **Negative**: dependency on the Global Service, proxy latency (~1-5ms)
- **Neutral**: gRPC remains a future option if performance requires it

### Confirmation

- `curl -H "Authorization: Bearer $JWT" https://chapsmind.local/api/target/watch_files` returns data
- Requests without an active Target module receive HTTP 403
- Proxy latency metrics are monitored

---

## Decision 4 : Implement an `InternalJwtAuthenticator` in Symfony

### Context

The Basil backend currently uses two Symfony firewalls:

- **`webhook`**: shared token + IP allowlist authentication (for N8N)
- **`main`**: OIDC authentication via Keycloak userinfo endpoint

In the ChapsMind architecture, the Global Service validates the original Keycloak JWT and issues an **Internal JWT** (HMAC-SHA256) containing the extracted user information. Internal services (Screen, Target) receive this Internal JWT instead of the original Keycloak JWT.

The ChapsMind Python backend (Screen) already uses an `InternalTrustIDPWrapper` that verifies this internal JWT. An equivalent must be created for the PHP/Symfony backend.

### Options considered

#### Option A: Keep direct OIDC authentication

**Description**: `target` continues to call the Keycloak userinfo endpoint for each request.

**Pros:**

- No modification to existing code
- Authentication directly verified with Keycloak

**Cons:**

- Double validation (Global Service + Target API) for each request
- Additional network call to Keycloak per request
- Does not transmit the organizational context (org_id) already extracted by the Global Service
- Inconsistent with the ChapsMind internal pattern

#### Option B: Trust headers (X-User-Id, X-Organization-Id)

**Description**: the Global Service transmits user information via HTTP headers, Target API trusts the internal network.

**Pros:**

- Trivial implementation (no crypto)
- Pattern used in some simple architectures

**Cons:**

- **Insufficient security**: any service on the Docker network can forge these headers
- No cryptographic authentication
- No TTL or replay protection

#### Option C: Internal JWT HMAC-SHA256 (Retained)

**Description**: create an `InternalJwtAuthenticator` Symfony that verifies Internal JWTs issued by the Global Service with a shared secret key (`INTERNAL_JWT_SECRET`).

**Pros:**

- Cryptographically secured (HMAC-SHA256, shared secret key)
- Consistent with the existing Python pattern (`InternalTrustIDPWrapper`)
- Carries user_id, organization_id, username, roles
- Configurable TTL (replay protection)
- No additional network call (local verification)

**Cons:**

- New component to develop and maintain
- Requires secure sharing of the secret (`INTERNAL_JWT_SECRET`)
- If the secret is compromised, all internal services are vulnerable

### Decision

> **Option C retained**: create an `InternalJwtAuthenticator` in Symfony.

Planned implementation:

```php
// src/Infrastructure/User/Security/InternalJwtAuthenticator.php

final class InternalJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly string $internalJwtSecret,
        private readonly UserProviderInterface $userProvider,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr($request->headers->get('Authorization', ''), 7);
        $payload = $this->verifyInternalJwt($token);

        // Extract user info from Internal JWT claims
        $user = $this->userProvider->loadUserByIdentifier($payload['sub']);

        return new SelfValidatingPassport(
            new UserBadge($payload['sub'], fn() => $user),
        );
    }

    private function verifyInternalJwt(string $token): array
    {
        // Decode and verify HMAC-SHA256 signature
        // Verify exp claim (TTL)
        // Return payload with sub, org_id, username, roles
    }
}
```

The Symfony firewall will be adjusted:

```yaml
security:
  firewalls:
    webhook:
      pattern: ^/api/webhook
      stateless: true
      custom_authenticator: 'App\Infrastructure\User\Security\WebhookTokenAuthenticator'
    internal:
      pattern: ^/api
      stateless: true
      custom_authenticator: 'App\Infrastructure\User\Security\InternalJwtAuthenticator'
```

The `main` firewall (OIDC userinfo) will be **replaced** by `internal` since all requests will go through the Global Service. Direct OIDC mode will no longer be needed.

### Consequences

- **Positive**: secure authentication, consistent with the ecosystem, no network call
- **Negative**: new component to maintain, shared secret to secure
- **Neutral**: the existing `WebhookTokenAuthenticator` remains for direct N8N calls

### Confirmation

- An integration test verifies that a valid Internal JWT grants access
- A test verifies that an expired or malformed JWT is rejected (HTTP 401)
- The `INTERNAL_JWT_SECRET` secret is injected via Docker environment variable

---

## Decision 5 : Use a dedicated `target_db` database

### Context

The ChapsMind architecture follows the **database-per-service** pattern:

- `global_db`: tokens, folders, organizations, modules (Global Service)
- `mint_db`: companies, tasks, company data (Screen/Python backend)
- `keycloak`: Keycloak database

The Basil backend currently uses a single PostgreSQL database containing all tables (WatchFile, Document, User, Actor, Source, Chat, etc.) with 35 Doctrine migrations.

### Options considered

#### Option A: Separate schema in the same PostgreSQL instance (`target_schema` in `mint_db`)

**Description**: use a separate PostgreSQL schema in the existing database.

**Pros:**

- No additional database to create
- Single PostgreSQL server to manage

**Cons:**

- Risk of accidental coupling (cross-schema queries possible)
- The ChapsMind pattern uses separate databases, not schemas (except for global/screen which share the same instance)
- Alembic and Doctrine migrations in the same instance may create conflicts

#### Option B: Dedicated `target_db` database on the same PostgreSQL instance (Retained)

**Description**: create a `target_db` database on the existing PostgreSQL instance, like `global_db` and `mint_db`.

**Pros:**

- Complete isolation (no accidental cross-database queries)
- Consistent with the existing pattern (`global_db`, `mint_db`, `keycloak`)
- Isolated Doctrine migrations
- Possibility to separate to a dedicated instance later if needed
- No additional server (same PG instance)

**Cons:**

- `initdb.d/` initialization script to enrich
- No cross-database JOINs (by design)

#### Option C: Separate PostgreSQL instance

**Description**: deploy a second dedicated PostgreSQL server for Target.

**Pros:**

- Total isolation (network, CPU, memory)
- Independent scalability

**Cons:**

- Operational overhead (backup, monitoring, updates)
- Premature for current volume
- Complicates dev infrastructure

### Decision

> **Option B retained**: create `target_db` on the existing PostgreSQL instance.

The initialization script will be enriched:

```sql
-- infra/files/db/initdb.d/init-target-db.sql
CREATE DATABASE target_db;
GRANT ALL PRIVILEGES ON DATABASE target_db TO postgres;
```

The Doctrine configuration in `target` will point to:

```
DATABASE_URL=postgresql://postgres:postgres@db:5432/target_db
```

The 35 existing Doctrine migrations will be executed on `target_db` during the first deployment.

### Consequences

- **Positive**: clean isolation, consistent with existing setup, autonomous migrations
- **Negative**: no cross-service JOINs (shared data like organizations go through the Global Service API)
- **Neutral**: migration to a dedicated instance possible in the future without code changes

### Confirmation

- `\l` in `psql` shows `target_db`, `global_db`, `mint_db`, `keycloak`
- `docker compose exec target php bin/console doctrine:migrations:status` shows 35 executed migrations

**Note**: the ChapsMind environment uses PostgreSQL 16, Target requires PostgreSQL 17. Unification to PG 17 is a prerequisite operational action (see Dependencies section).

---

## Decision 6 : Deploy dedicated Elasticsearch, Valkey, N8N instances

### Context

The Basil backend depends on three ancillary services that the ChapsMind stack does not currently use:

- **Elasticsearch 9.1**: full-text indexing and search for Documents and WatchFileEvents (12 ES migrations)
- **Valkey 8** (Redis fork): application cache, Mercure debounce, sessions
- **N8N**: AI workflow orchestration (6 workflows, 5+ sub-workflows)

The current ChapsMind stack has no Elasticsearch, no Redis/Valkey, and no N8N.

### Options considered

#### Option A: Share instances with existing services

**Description**: reuse existing RabbitMQ and PostgreSQL, add a shared Elasticsearch.

**Pros:**

- Fewer Docker containers
- Resource savings

**Cons:**

- Elasticsearch and Valkey don't exist in the current stack — nothing to share
- N8N is Target-specific, no reason to share it
- Operational coupling (an ES crash impacts all services)

#### Option B: Dedicated instances per Target service (Retained)

**Description**: each Target ancillary service is deployed as a dedicated Docker container, prefixed `target-`.

**Pros:**

- Total isolation (configuration, data, version upgrades)
- No impact on existing services
- Specific configuration (ES tuning, Valkey cache policies)
- Consistent with Target module autonomy

**Cons:**

- More Docker containers (~4 additional: ES, Valkey, N8N, Kibana)
- Increased memory consumption (~2-3 GB additional in dev)
- More services to monitor

### Decision

> **Option B retained**: deploy `target-elasticsearch`, `target-valkey`, `target-n8n` as dedicated containers.

Additional Docker services:

```yaml
# infra/docker-compose.yml (excerpt)
target-elasticsearch:
  image: elasticsearch:9.1.0
  environment:
    - discovery.type=single-node
    - ES_JAVA_OPTS=-Xms512m -Xmx512m
  volumes:
    - target_es_data:/usr/share/elasticsearch/data
  networks:
    - mint-network
  profiles: ['target']

target-valkey:
  image: valkey/valkey:8-alpine
  volumes:
    - target_valkey_data:/data
  networks:
    - mint-network
  profiles: ['target']

target-n8n:
  image: n8nio/n8n:latest
  environment:
    - WEBHOOK_URL=http://target:8002
    - N8N_PORT=5679
  volumes:
    - target_n8n_data:/home/node/.n8n
  networks:
    - mint-network
  profiles: ['target']
```

The existing RabbitMQ will be **shared** with Target (queues separated by naming convention: `target_async_priority_high`, `target_agent_commands`, etc.).

### Consequences

- **Positive**: isolation, no impact on Screen, independent configuration
- **Negative**: increased memory footprint in dev, more services to manage
- **Neutral**: shared RabbitMQ (vhosts or naming conventions for logical isolation)

### Confirmation

- `docker compose --profile target ps` shows `target-*` services
- `curl http://target-elasticsearch:9200` returns cluster info
- `target-valkey` responds to `PING` → `PONG`
- N8N dashboard accessible on configured port

---

## Decision 7 : Use Docker Compose profiles for dev isolation

### Context

The ChapsMind `docker-compose.yml` already contains ~10 services (nginx, frontend, backend, global-service, keycloak, db, rabbitmq, celery worker, celery flower). Adding ~5 Target services (target, target-elasticsearch, target-valkey, target-n8n, target-caddy) brings the total to ~15 services.

A developer working only on Screen should not have to start Target services, and vice versa.

### Options considered

#### Option A: Separate `docker-compose.target.yml` file

**Description**: a secondary compose file activated via `docker compose -f docker-compose.yml -f docker-compose.target.yml up`.

**Pros:**

- Clear file separation
- No pollution of the main compose

**Cons:**

- Long and error-prone commands
- Duplication of network/volume configurations
- Hard to orchestrate in CI/CD

#### Option B: Docker Compose profiles (Retained)

**Description**: use Docker Compose profiles (`profiles: ["target"]`) to enable/disable Target services.

**Pros:**

- Single compose file
- Simple activation: `docker compose --profile target up`
- Native Docker Compose support
- Common services (db, rabbitmq, keycloak) remain without profile (always started)

**Cons:**

- Longer compose file
- Requires Docker Compose V2+

#### Option C: Kubernetes/Helm for orchestration

**Description**: migrate to Kubernetes with Helm charts per module.

**Pros:**

- Production orchestration
- Scalability

**Cons:**

- Enormous complexity for local dev
- Premature — ChapsMind uses Docker Compose in dev and preprod

### Decision

> **Option B retained**: Docker Compose profiles.

Profile organization:

```
No profile (always up) : nginx, frontend, global-service, keycloak, db, rabbitmq
Profile "screen"       : backend, backend_celery_worker, backend_celery_flower
Profile "target"       : target, target-elasticsearch, target-valkey, target-n8n, target-caddy
```

Commands:

```bash
# Screen dev only
docker compose --profile screen up

# Target dev only
docker compose --profile target up

# All modules
docker compose --profile screen --profile target up

# Common only (gateway, db, keycloak)
docker compose up
```

### Consequences

- **Positive**: lightweight dev, fast startup, single compose file
- **Negative**: larger compose file
- **Neutral**: shared services (db, rabbitmq, keycloak) remain always active

### Confirmation

- `docker compose --profile target config` shows Target services
- `docker compose --profile screen up` does not start `target-*` services

---

## Decision 8 : Have N8N communicate directly with `target`

### Context

N8N orchestrates the Target AI workflows (orchestrator-router, chat, watchfile-builder, deepsearch, etc.). These workflows communicate with the backend via HTTP webhooks.

Currently in Basil, N8N calls `http://api:80/api/webhook` directly with a shared token (`X-Webhook-Token`). The Symfony `webhook` firewall authenticates these calls with a shared token and IP allowlist.

### Options considered

#### Option A: N8N goes through the Global Service (`/api/target/webhook`)

**Description**: N8N sends its webhooks to the Global Service which routes them to `target`.

**Pros:**

- Architecture strictly compliant with ADR-0009

**Cons:**

- N8N has no Keycloak JWT (it's a machine-to-machine service)
- The Global Service would have to handle a specific authentication mode for webhooks
- Unnecessary latency (additional hop for internal traffic)
- N8N and target are on the same Docker network

#### Option B: Direct N8N → `target` communication (Retained)

**Description**: N8N calls `http://target:8002/api/webhook` directly using the existing `WebhookTokenAuthenticator`.

**Pros:**

- Pattern already functional and proven in Basil
- No Global Service modification
- No additional latency
- Shared token + IP allowlist authentication sufficient (internal Docker network)
- N8N and target are internal services, not exposed to the Internet

**Cons:**

- Bypasses the Global Service for this specific flow
- Two authentication methods in target (Internal JWT + Webhook Token)

### Decision

> **Option B retained**: direct N8N → `target` communication.

The existing `WebhookTokenAuthenticator` is kept without modification. Configuration:

```yaml
# target security.yaml (unchanged)
security:
  firewalls:
    webhook:
      pattern: ^/api/webhook
      stateless: true
      custom_authenticator: 'App\Infrastructure\User\Security\WebhookTokenAuthenticator'
    internal:
      pattern: ^/api
      stateless: true
      custom_authenticator: 'App\Infrastructure\User\Security\InternalJwtAuthenticator'
```

Justification: ADR-0009 stipulates that only **frontend traffic** goes through the Global Service. Internal machine-to-machine communication on the Docker network can legitimately bypass the gateway.

### Consequences

- **Positive**: zero modification to existing code, minimal latency, proven pattern
- **Negative**: exception to the gateway pattern (accepted for M2M traffic)
- **Neutral**: N8N logs show direct calls for debugging

### Confirmation

- N8N webhook to `http://target:8002/api/webhook` returns HTTP 200
- The `webhook` firewall blocks requests from IPs outside the Docker network

---

## Decision 9 : Reuse the `OrganizationModule` module check system

### Context

ChapsMind already has a **per-module feature gating** system:

```python
class ModuleName(str, Enum):
    SCREEN = "screen"
    TARGET = "target"      # ← already declared
    EXPLORE = "explore"
    TRANSLATION = "translation"
```

The `organization_modules` table stores `(organization_id, module_name, enabled)` pairs. This system allows enabling/disabling modules per organization.

### Options considered

#### Option A: No module check (Target always accessible)

**Description**: any authenticated user can access Target endpoints.

**Pros:**

- Simplest implementation
- No additional dependency

**Cons:**

- No access control per organization
- Organizations without a Target license see the endpoints
- Inconsistent with the ChapsMind business model

#### Option B: Module check in the Global Service (Retained)

**Description**: the Global Service checks `OrganizationModule(org_id, 'target', enabled=True)` before proxying to target.

**Pros:**

- Centralized check (single implementation)
- Target API doesn't handle licensing logic
- Consistent with the existing pattern for Screen
- The check is already planned in the Global Service (API Gateway spec)

**Cons:**

- Dependency on the Global Service for access
- Additional query to `global_db` (cacheable)

#### Option C: Duplicated module check in Target API

**Description**: target verifies the module check itself.

**Pros:**

- Total autonomy

**Cons:**

- Logic duplication
- Target API must access `global_db` (database-per-service violation)
- Inconsistent with ADR-0009

### Decision

> **Option B retained**: the Global Service handles the module check.

```python
# global-service/app/middleware/module_check.py
async def check_module_access(request: Request, module: ModuleName):
    org_id = request.state.organization_id
    module_enabled = await organization_module_service.is_enabled(org_id, module)
    if not module_enabled:
        raise HTTPException(status_code=403, detail=f"Module {module.value} not enabled")

# In the target proxy:
@router.api_route("/api/target/{path:path}")
async def proxy_to_target(request: Request, path: str):
    await check_module_access(request, ModuleName.TARGET)
    # ... proxy logic
```

### Consequences

- **Positive**: consistent, centralized, cacheable access control
- **Negative**: dependency on the Global Service (accepted — that's its role)
- **Neutral**: the module check can be cached in memory (TTL ~60s) to avoid repeated queries

### Confirmation

- An organization with `TARGET` disabled receives HTTP 403 on `/api/target/*`
- An organization with `TARGET` enabled accesses normally
- The module check cache is invalidated on admin changes

---

## Decision 10 : Route SSE/Mercure streams via the Global Service with authentication exception

### Context

The Basil backend uses **Mercure** (SSE protocol based on HTTP) for real-time updates. Mercure is integrated into **Caddy/FrankenPHP** via the native Mercure module. Updates cover:

- WatchFile analysis progress (state change: `NEW` → `NEEDS_ANALYZED` → ... → `MONITORING_TYPE_DETECTED`)
- Document updates
- AI chat notifications (streaming messages)
- Collection events (Bakus WebSocket → SSE)

Current architecture (standalone Basil):

- User-scoped topics: `/users/{userId}/watch-files/{watchFileId}` (ADR-2025-001)
- JWT with URI Templates for constant token size
- Debounce via Valkey (30s) to avoid bursts
- HTTP/2 mandatory (Caddy HTTPS → automatic HTTP/2) to avoid browser connection pool exhaustion (6 conn/domain limit in HTTP/1.1)
- Subscription API restricted to internal network (`172.16.0.0/12`, `192.168.0.0/16`, `10.0.0.0/8`)

ChapsMind already uses native SSE (without Mercure) for Screen module task events:

- Single SSE stream per user on `/api/events/stream`
- Proxied via Nginx → Global Service → backend **with `proxy_buffering off`** (already configured)
- Keepalive 30s, `X-Accel-Buffering: no`

**Meeting consensus**: Mercure remains a service exclusive to the Target module — there is no need on the horizon to share it across the Global Service. SSE routing must go through the Global Service but with an authentication exception, since the Mercure JWT (subscriber) is self-sufficient and distinct from the application JWT.

### Options considered

#### Option A: Direct connection to the Mercure hub (bypass Nginx/Gateway)

**Description**: the Target frontend connects directly to the Mercure hub on a dedicated port/domain (`mercure.chapsmind.local` or `chapsmind.local:8443`), without going through Nginx or the Global Service.

**Pros:**

- Zero proxy latency for streaming
- Unchanged Mercure configuration
- No dependency on Nginx for SSE

**Cons:**

- **Additional URL** to configure for the frontend (`VITE_TARGET_MERCURE_URL`)
- **Additional port** to expose and secure
- **Inconsistent** with the ChapsMind pattern (everything goes through Nginx port 80)
- **CORS**: different domain = pre-flight OPTIONS on each SSE connection
- **TLS certificate**: HTTP/2 requires HTTPS, so an additional certificate to manage
- The frontend must differentiate API and SSE URLs per module

#### Option B: Direct Nginx proxy → Caddy/Mercure (bypass Global Service)

**Description**: Nginx routes SSE requests to the `target-caddy` Mercure hub via a dedicated `location`, without going through the Global Service.

**Pros:**

- Simple Nginx configuration
- No Global Service modification

**Cons:**

- **Bypasses the Global Service**: two different access paths for the Target module
- Routing is fragmented (API via Global Service, SSE via direct Nginx)
- No Global Service visibility on SSE connections

#### Option C: Proxy via the Global Service with authentication exception (Retained)

**Description**: the Global Service routes Mercure requests to `target-caddy` with an **authentication exception** — it does not validate the Keycloak JWT for this path because the Mercure JWT (subscriber) is a distinct and self-sufficient token. The Global Service acts as a transparent proxy for SSE.

**Pros:**

- **Consistent** with ADR-0009 (all frontend traffic goes through the Global Service)
- **Unified URL**: the frontend uses the same base URL
- **Single entry point** (Global Service)
- The Global Service has visibility on SSE connections (logs, metrics)
- Mercure handles its own authentication (distinct subscriber JWT)
- No additional port to expose

**Cons:**

- Authentication exception to implement in the Global Service
- The Global Service must support SSE stream proxying (`proxy_buffering off`, long timeouts)
- Negligible latency (~0.1ms for the proxy)

#### Option D: Replace Mercure with native SSE via the Global Service

**Description**: migrate to the ChapsMind SSE pattern (`StreamingResponse` + `asyncio.Queue`) going through the Global Service as for Screen.

**Pros:**

- Unified architecture (same pattern for Screen and Target)
- No additional Caddy/Mercure

**Cons:**

- **Major rewrite**: Mercure handles topics, JWT auth, reconnection, debounce — all of this would need to be reimplemented
- Loss of URI Templates (constant token size)
- The Global Service (Python) would have to proxy SSE from a PHP backend — integration complexity
- The ChapsMind `asyncio.Queue` pattern is in-memory, does not survive restarts (Mercure persists via Bolt)

### Decision

> **Option C retained**: the Global Service routes SSE/Mercure traffic to `target-caddy` with an authentication exception.

Implementation in the Global Service:

```python
# global-service/app/api/routes/target_mercure_proxy.py

@router.api_route("/.well-known/mercure", methods=["GET", "POST"])
async def proxy_mercure(request: Request):
    """
    Proxy Mercure hub requests to target-caddy.
    Auth exception: Mercure uses its own JWT (subscriber_jwt),
    distinct from the Keycloak JWT. No Global Service auth validation needed.
    """
    async with httpx.AsyncClient() as client:
        # Stream the SSE response
        async with client.stream(
            method=request.method,
            url=f"{TARGET_CADDY_URL}/.well-known/mercure",
            headers={
                "Authorization": request.headers.get("Authorization", ""),
                "Last-Event-ID": request.headers.get("Last-Event-ID", ""),
            },
            params=request.query_params,
            content=await request.body(),
        ) as upstream:
            return StreamingResponse(
                upstream.aiter_bytes(),
                status_code=upstream.status_code,
                headers={
                    "Content-Type": upstream.headers.get("Content-Type", "text/event-stream"),
                    "Cache-Control": "no-cache",
                    "X-Accel-Buffering": "no",
                },
            )
```

Nginx configuration (unchanged from the standard pattern — everything goes through the Global Service):

```nginx
# infra/files/nginx.conf
# Mercure traffic goes through the Global Service like everything else
location /.well-known/mercure {
    proxy_pass http://global-service:8001;
    proxy_buffering off;
    proxy_cache off;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    chunked_transfer_encoding off;
    # SSE-specific timeouts
    proxy_read_timeout 24h;
    proxy_send_timeout 24h;
}
```

The frontend uses a single base URL:

- Target API: `https://chapsmind.local/api/target/*` → Nginx → Global Service → target
- SSE Mercure: `https://chapsmind.local/.well-known/mercure` → Nginx → Global Service → target-caddy

**Mercure remains a service exclusive to the Target module.** There is no need for sharing on the horizon. If other modules need SSE, they will use their own solution (like Screen's `StreamingResponse`).

`target-caddy` is deployed as a sidecar of `target` in the same Docker Compose profile:

```yaml
target-caddy:
  image: dunglas/mercure:latest # or custom FrankenPHP image
  environment:
    MERCURE_PUBLISHER_JWT_KEY: ${TARGET_MERCURE_JWT_SECRET}
    MERCURE_SUBSCRIBER_JWT_KEY: ${TARGET_MERCURE_JWT_SECRET}
    MERCURE_PUBLISHER_JWT_ALG: HS256
    MERCURE_SUBSCRIBER_JWT_ALG: HS256
  networks:
    - mint-network
  profiles: ['target']
```

### Consequences

- **Positive**: unified URL, all traffic goes through the Global Service (consistent with ADR-0009), Mercure remains exclusive to Target, zero modification to frontend/backend SSE code
- **Negative**: authentication exception in the Global Service, `target-caddy` as additional container
- **Neutral**: Mercure handles its own authentication via a distinct subscriber JWT — the Global Service is a transparent proxy for this flow

### Confirmation

- `EventSource('https://chapsmind.local/.well-known/mercure?topic=...')` receives events
- Topics are protected by the Mercure JWT (subscriber_jwt)
- The Global Service does not block Mercure requests (auth exception)
- `proxy_buffering off` is verified via response headers (`X-Accel-Buffering: no`)
- Automatic reconnection works after a network disconnection

---

## Decision 11 : Maintain the Basil pipeline CI quality level

### Context

The Basil project has a mature CI/CD GitLab pipeline of **1196 lines** covering: ECS, PHPStan level 9, PHPUnit, N8N validation, OpenAPI export, Doctrine validation, Composer audit, Trivy, SAST, Secret Detection, Coverage Cobertura, staging deployment per MR, multi-stage Docker FrankenPHP build.

In comparison, the ChapsMind (Screen) pipelines are minimalistic: ~35 lines (build + publish Docker).

Integration into the monorepo (Decision 2) is done via `include:local` in `apps/target/.gitlab-ci.yml`. The question is: what quality level to maintain?

### Decision

> **The full pipeline is preserved** in `apps/target/.gitlab-ci.yml`, adapted to the monorepo `include:local` pattern (cf. Decision 2 for detailed CI structure).

**To keep:** ECS, PHPStan level 9, PHPUnit (unit + integration), Doctrine schema validation, Trivy scanning, Composer audit, SAST, Secret Detection, OpenAPI export, N8N workflow validation, Coverage Cobertura, multi-stage Docker FrankenPHP build.

**To remove:** PWA jobs (in `apps/front/`), Docs MkDocs jobs (cf. Decision 14), Renovate (to be configured at monorepo level).

**To adapt:** Docker registry (`$CI_REGISTRY_IMAGE/target`), staging URLs, `rules:changes` to `apps/target/**/*`.

### Consequences

- **Positive**: code quality maintained, no regression compared to the Basil pipeline
- **Negative**: Target jobs are heavier (~15 min) than other apps (~3 min)
- **Neutral**: the Target team maintains its CI in its directory, independently of other apps

### Confirmation

- Jobs `target:lint`, `target:test`, `target:security`, `target:build` pass in the monorepo pipeline
- PHPStan level 9: 0 errors
- Trivy: 0 critical, < 10 high
- The Docker image is published to the ChapsMind registry

---

## Decision 12 : Configure GitLab runners for the PHP backend

### Context

The Target backend is in **PHP 8.4 with FrankenPHP**. Its CI needs are specific:

- FrankenPHP Docker image with compiled extensions (amqp, redis, pdo_pgsql, excimer, xsl, etc.)
- PostgreSQL as CI service for integration tests (RAM database)
- Elasticsearch as CI service for indexing tests
- Valkey as CI service for cache/debounce tests
- Memory: PHPStan level 9 is resource-hungry (~1.5 GB RAM)
- Disk: PHP vendor (~200 MB) and Docker build are large

Current ChapsMind pipelines run on runners with **Python 3.11** and **Node 22** images. The ChapsMind `ci-images` repo provides specialized CI images (`web-linters`, `assets-optimizer`, `claude`, `jira`) but none for PHP.

### Options considered

#### Option A: Shared runner with dynamic Docker image

**Description**: reuse existing ChapsMind runners, each CI job pulls its own Docker image (FrankenPHP for PHP jobs, PostgreSQL/ES/Valkey as CI services).

**Pros:**

- No additional runner infrastructure
- GitLab CI `services:` provide PG/ES/Valkey on demand
- Existing runners support Docker-in-Docker or Docker socket

**Cons:**

- FrankenPHP image pull time per job (~2 min without cache)
- Runners must have enough RAM for PHP + PG + ES + Valkey in parallel (~4 GB)
- [INVESTIGATE: Verify that current ChapsMind runners support GitLab CI `services:`]

#### Option B: Dedicated PHP CI image in the `ci-images` repo (Retained)

**Description**: create a `php-target` CI image in the ChapsMind `ci-images` repo, pre-built with required PHP extensions and CI tools (composer, PHPStan, ECS).

**Pros:**

- **Fast startup**: pre-built image, no extension compilation per job
- **Consistent** with the `ci-images` pattern (web-linters, assets-optimizer, claude, jira)
- Heavy extensions (amqp, redis, excimer) compiled once
- Versionable and testable (like other CI images)
- The CI image Dockerfile is distinct from the production Dockerfile

**Cons:**

- Additional image to maintain in `ci-images`
- Must be updated during PHP bumps (8.4 → 8.5)

#### Option C: Dedicated PHP runner

**Description**: deploy a dedicated GitLab runner for PHP/Target jobs.

**Pros:**

- Total isolation
- Persistent local cache (vendor, Docker layers)

**Cons:**

- Additional infrastructure to manage
- Underutilized if few Target MRs
- Disproportionate operational overhead

### Decision

> **Option B retained**: create a `php-target` CI image in the `ci-images` repo.

Planned image:

```dockerfile
# ci-images/php-target/Dockerfile
FROM dunglas/frankenphp:latest-php8.4-bookworm

# Pre-compiled PHP extensions
RUN install-php-extensions \
    apcu intl opcache zip bcmath \
    amqp redis pcntl excimer xsl pdo_pgsql

# CI tools
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# PHPStan, ECS as global for lint jobs
RUN composer global require \
    phpstan/phpstan \
    symplify/easy-coding-standard

ENV PATH="${PATH}:/root/.composer/vendor/bin"
```

CI jobs will use:

```yaml
# target/.gitlab-ci.yml (excerpt)

.php-job:
  image: registry.git.mediaspeech.com/mint/ci-images/php-target:latest
  before_script:
    - composer install --no-interaction --prefer-dist

phpstan:
  extends: .php-job
  stage: coding_standards
  script:
    - php -d memory_limit=2G vendor/bin/phpstan analyse

tests:integration:
  extends: .php-job
  services:
    - postgres:17-alpine
    - elasticsearch:9.1.0
    - valkey/valkey:8-alpine
  variables:
    DATABASE_URL: postgresql://postgres:postgres@postgres:5432/target_test_db
    ELASTICSEARCH_URL: http://elasticsearch:9200
    REDIS_URL: redis://valkey:6379
```

### Consequences

- **Positive**: fast CI job startup, consistent with ci-images, pre-compiled extensions
- **Negative**: additional image to maintain, ~500 MB
- **Neutral**: existing runners remain sufficient (no dedicated runner needed)

### Confirmation

- The `php-target` image is built and tested in the `ci-images` pipeline
- `docker run php-target php -m` shows all required extensions
- CI jobs `target` use this image and pass
- [INVESTIGATE: Verify ChapsMind runner compatibility with CI services (PG, ES, Valkey)]

---

## Decision 13 : Expose OpenAPI documentation via the Global Service

### Context

The Basil backend automatically exposes its API documentation via **API Platform 4.1**:

- **Swagger UI**: `https://basil.local/api/docs` (interactive interface)
- **OpenAPI JSON**: `https://basil.local/api/docs.json`
- **OpenAPI YAML**: `https://basil.local/api/docs.yaml`
- **GraphQL Playground**: `https://basil.local/api/graphql`

The CI exports OpenAPI in JSON and YAML as artifacts (lines 366-377 of `.gitlab-ci.yml`). Custom OpenAPI factories add:

- Rate limiting information (priority 15)
- i18n support (priority 10)
- Mercure subscriptions (priority 5)

In the ChapsMind architecture, the Global Service is the sole entry point. Target API documentation must be accessible without exposing `target` directly.

### Options considered

#### Option A: No exposed API doc (docs only in CI)

**Description**: OpenAPI documentation is only exported as a CI artifact, not accessible at runtime.

**Pros:**

- No modification
- Secure (no documentation endpoint exposed)

**Cons:**

- Frontend developers cannot test interactively
- No Swagger UI in dev/staging
- Loss of productivity

#### Option B: Proxy documentation via the Global Service (Retained)

**Description**: the Global Service routes `/api/target/docs*` to `target:8002/api/docs*`.

**Pros:**

- Documentation accessible via the unified URL (`https://chapsmind.local/api/target/docs`)
- Interactive Swagger UI available in dev/staging
- Consistent with existing API routing (same `/api/target/` prefix)
- Frontend developers test endpoints directly

**Cons:**

- Documentation accessible to any authenticated user (module check applies)
- The base URL in the OpenAPI spec must be rewritten (`/api/` → `/api/target/`)

#### Option C: Centralized aggregated documentation

**Description**: the Global Service aggregates OpenAPI specs from all modules into unified documentation.

**Pros:**

- Overview of the entire ChapsMind API
- Single Swagger UI

**Cons:**

- OpenAPI spec aggregation complexity
- Schema conflicts between modules (Python vs PHP)
- Heavy spec merge maintenance

### Decision

> **Option B retained**: documentation is proxied via the Global Service.

The `/api/target/docs*` routing is already covered by the generic `/api/target/{path:path}` proxy. API Platform will generate documentation with an adapted base path.

API Platform configuration in `target`:

```yaml
# api/config/packages/api_platform.yaml
api_platform:
  title: 'Target API'
  version: '1.0.0'
  # The prefix will be /api because target sees its requests without the /target prefix
  # The Global Service strips /api/target → /api before forwarding
```

The OpenAPI response will be available:

- Dev: `https://chapsmind.local/api/target/docs` (Swagger UI)
- CI: `openapi.json` / `openapi.yaml` artifact in the pipeline

**For production**: documentation can be disabled via an environment variable (`API_PLATFORM_DOCS_ENABLED=false`) for security reasons.

### Consequences

- **Positive**: interactive documentation in dev/staging, zero additional config (existing proxy suffices)
- **Negative**: the OpenAPI base path requires special attention (`/target` stripping by the gateway)
- **Neutral**: production docs can be disabled if needed

### Confirmation

- `https://chapsmind.local/api/target/docs` shows the Swagger UI
- Endpoints listed in Swagger are functional via "Try it out"
- CI exports `openapi.json` and `openapi.yaml` as artifacts

---

## Decision 14 : Per-module technical documentation with lightweight centralization

### Context

The Basil project has a **comprehensive documentation portal** based on **MkDocs Material** (170+ navigation lines, CI pipeline with orphan docs validation, Docker build, `mike` versioning).

ChapsMind has no equivalent portal. Documentation is scattered between `CLAUDE.md`, `README.md`, and miscellaneous docs.

**Meeting consensus**: each module maintains its own documentation, and we find an elegant, low-cost solution to centralize the display on a single website. The detailed centralization strategy will be the subject of a **dedicated ADR involving all teams** (Target, Screen, Platform).

### Options considered

#### Option A: Keep MkDocs docs in the `target` repo only

**Description**: docs remain in `target/docs/`, the MkDocs build is in the `target` pipeline.

**Pros:**

- Zero effort, docs already exist
- CI pipeline already configured
- The Target team has autonomous docs

**Cons:**

- Fragmented documentation (Target has a portal, Screen doesn't)
- Developers must navigate between multiple sources

#### Option B: Unified MkDocs portal at workspace level

**Description**: create an `mkdocs.yml` at the `chapsmind-workspace/` level that aggregates documentation from all modules.

**Pros:**

- Unified platform vision
- Navigation by module

**Cons:**

- Significant migration effort
- Restructuring effort to organize docs in the monorepo
- Teams must coordinate documentation changes
- Unilateral decision — impacts all teams without consulting them

#### Option C: Per-module documentation + lightweight centralization (Retained)

**Description**: each module (Target, Screen, Global Service) maintains its own documentation in its repo. A lightweight mechanism centralizes display for consultation.

**Pros:**

- **Autonomy**: each team manages its docs independently
- **No friction**: no inter-team coordination for doc changes
- **Progressive**: centralization can be added after migration, without blocking
- **Low cost**: no heavy restructuring

**Cons:**

- No immediate unified view
- The centralization solution remains to be defined

### Decision

> **Option C retained**: per-module documentation with lightweight centralization.

**For `target`**: the existing MkDocs documentation is migrated as-is into the `target/docs/` repo. The CI build and validation pipeline is preserved.

**For centralization**: a dedicated ADR will be created to define the centralization strategy with all teams. Avenues to explore:

- `mkdocs-monorepo-plugin` to aggregate docs from different modules
- A simple index portal with links to each module's docs
- A CI build that copies docs from each module into a central site

**[ADR TO CREATE: ChapsMind centralized technical documentation strategy — involves all teams]**

### Consequences

- **Positive**: team autonomy, Target documentation preserved, no migration blocker
- **Negative**: no unified portal until the centralization ADR is done
- **Neutral**: each module can evolve its docs independently

### Confirmation

- `target/docs/` contains the migrated Basil documentation
- The CI `target` pipeline builds and validates docs (MkDocs + `validate-mkdocs-nav.py`)
- The centralization ADR is on the backlog

---

## Decision 17 : Migrate the N8N ecosystem into the ChapsMind monorepo

### Context

The N8N integration in Basil is a mature and complex ecosystem that goes beyond simple workflow files. The complete inventory includes:

**Workflows (22 JSON files, ~700 KB)**:

- **Orchestration**: `1-orchestrator-router` (AI entry point), `2-chat-session-message` (chat message handling)
- **WatchFile tools**: `3-tool-watchfile-builder` + 5 sub-workflows (3a–3c, 3aa, 3ab) for actors, sources, reference topics
- **Miscellaneous tools**: `4-tool-rename-watchfile`, `5-tool-classify-watchfile`, `7-tool-activate-watchfile`
- **DeepSearch**: `6-tool-deepsearch-agent` + 3 sub-workflows (6a–6c) for strategic questions, query generation and execution
- **Internal utilities**: document event extraction, summary generation, validation, error handling, system messages, webhook API, web search grounding

**Test infrastructure (1,983 lines)**:

- `test-workflow.js` (1,166 lines) — webhook test runner with snapshot comparison, parallel/sequential execution, timestamped reports
- `validate-datasets.js` (209 lines) — AJV dataset schema validation
- `dataset-schema.json` (158 lines) — dataset JSON schema
- 5 evaluation datasets (~247 KB) covering actors, sources, deepsearch, reference topics, watchfile-builder

**PHP validation framework (16 files, ~120 lines each)**:

- `validate-n8n-workflows.php` — main orchestrator (console + JUnit)
- 8 validators: `AgentNodeValidator`, `FeedbackNodeValidator`, `CallWorkflowToolNodeValidator`, `ExecuteSubWorkflowNodeValidator`, `RabbitMQMessageValidator`, `NamingConventionValidator`, `NodeVersionValidator`, `WebhookPathUniqueValidator`
- Console and JUnit reporters for CI

**Export/import scripts**:

- `export-n8n-workflows.py` (348 lines) — CLI export with kebab-case conversion, pinData cleanup
- `export-n8n-credentials.py` (254 lines) — credentials export with validation
- `entrypoint.sh` (103 lines) — custom initialization (daemon startup, user creation, readiness wait)

**Configuration**:

- `api/config/services/n8n.yaml` — DI for `N8nApiClient`
- `api/src/Infrastructure/N8n/N8nApiClient.php` (86 lines) — HTTP client for the N8N API
- RabbitMQ + Webhook API Token credentials (encrypted)
- Environment variables: `N8N_BASE_URL`, `N8N_API_KEY`, `N8N_ENCRYPTION_KEY`, etc.
- `api/frankenphp/n8n.Caddyfile` — HTTPS reverse proxy

**Docker services**:

- `n8n` (image `n8nio/n8n:1.121.3`, port 5678, dedicated PostgreSQL database `n8n_db`)
- `n8n-import` (automatic workflow/credentials import on startup)
- DB initialization script `docker/db/initdb.d/n8n.sh`

**Documentation** (10 Markdown files, 5,248 lines):

- Setup, concepts, naming conventions, best practices, testing, validation, upgrade

**Taskfile** (`docker/n8n/Taskfile.yaml`, 104 lines):

- `n8n:export-workflows`, `n8n:export-credentials`, `n8n:reset`, `n8n:test:setup`, `n8n:test`, `n8n:validate`, `n8n:validate:datasets`

### Options considered

#### Option A: Migrate only workflow JSON files and reconfigure everything

**Description**: migrate only the 22 workflow JSON files, rebuild the rest (tests, validation, scripts, docs) afterward.

**Pros:**

- Fast initial migration

**Cons:**

- Loss of test and validation infrastructure (2,000+ lines of code)
- Loss of 5,248 lines of documentation
- Major quality regression — workflows would no longer be tested or validated in CI
- Disproportionate reconstruction effort

#### Option B: Migrate the complete N8N ecosystem into the monorepo (Retained)

**Description**: migrate the entire N8N ecosystem into the monorepo, under `apps/target/n8n/` — N8N is a Target module component, not an independent module.

**Pros:**

- **Zero loss**: workflows, tests, validation, scripts, documentation, credentials
- CI can validate workflows and run tests immediately
- Export/import scripts remain functional
- Documentation is preserved for the team
- **Colocated with Target code**: `apps/target/` contains everything related to the Target module (PHP API + N8N)
- Already covered by the `apps/target/` CODEOWNERS (no additional rule)
- Already covered by the `rules:changes` for `apps/target/**/*` (Target CI jobs also trigger on N8N changes)

**Cons:**

- Volume of files to migrate (~171 files, 11 MB)
- Some paths need adaptation (Docker Compose paths, Caddyfile)

### Decision

> **Option B retained**: complete migration of the N8N ecosystem under `apps/target/n8n/`.

**Structure in the monorepo**:

```
chapsmind/
└── apps/
    └── target/                              # Entire Target module
        ├── src/                             # PHP Symfony code (ex-basil/api/src/)
        │   └── Infrastructure/N8n/          # N8nApiClient.php (unchanged)
        ├── config/services/n8n.yaml         # DI (unchanged)
        ├── tests/N8N/                       # PHP validators (unchanged)
        ├── n8n/                             # N8N ecosystem (ex-basil/docker/n8n/)
        │   ├── workflows/                   # 22 JSON files
        │   ├── credentials/                 # Encrypted credentials
        │   ├── tests/
        │   │   ├── test-workflow.js          # Test runner
        │   │   ├── validate-datasets.js      # Dataset validation
        │   │   ├── dataset-schema.json       # Schema
        │   │   └── datasets/                 # 5 evaluation datasets
        │   ├── scripts/
        │   │   ├── export-n8n-workflows.py   # Workflow export
        │   │   └── export-n8n-credentials.py # Credentials export
        │   ├── docker/
        │   │   ├── entrypoint.sh             # Custom init
        │   │   └── initdb.d/n8n.sh           # PostgreSQL n8n_db init script
        │   ├── docs/                         # 10 Markdown files (5,248 lines)
        │   └── Taskfile.yaml                 # N8N tasks
        └── ...
```

**Required adaptations**:

1. **Docker Compose**: the `n8n` and `n8n-import` services are in the monorepo compose with the `target` profile. Volumes point to `apps/target/n8n/`.

   ```yaml
   # compose.yaml (excerpt)
   n8n-import:
     profiles: ['target']
     volumes:
       - ./apps/target/n8n/workflows:/workflows:ro
       - ./apps/target/n8n/credentials:/credentials:ro
   ```

2. **CI/CD**: no additional CI file. N8N jobs are in `apps/target/.gitlab-ci.yml` (already included in the monorepo, cf. Decision 2). The existing `rules:changes` (`apps/target/**/*`) already covers N8N modifications:

   ```yaml
   # apps/target/.gitlab-ci.yml (addition to existing jobs)
   target:n8n-validate:
     stage: lint
     image: $CI_REGISTRY_IMAGE/ci-images/php-target:latest
     rules:
       - <<: *target-changes    # apps/target/**/* — also covers n8n/
     script:
       - cd apps/target
       - composer install --no-scripts
       - php tests/N8N/validate-n8n-workflows.php --workflows-dir=n8n/workflows

   target:n8n-test-datasets:
     stage: test
     image: node:22-slim
     rules:
       - changes:
           - apps/target/n8n/**/*
     script:
       - cd apps/target/n8n/tests && npm install
       - node validate-datasets.js
   ```

3. **Taskfile**: the N8N Taskfile is included in the monorepo root Taskfile:

   ```yaml
   # chapsmind/Taskfile.yaml (addition)
   includes:
     n8n:
       taskfile: apps/target/n8n/Taskfile.yaml
       dir: apps/target/n8n
   ```

4. **Credentials**: encrypted N8N credentials (`N8N_ENCRYPTION_KEY`) are shared via GitLab CI variables or SOPS.

5. **Environment variables**: `N8N_BASE_URL`, `N8N_API_KEY`, `N8N_API_KEY_FILE` are defined in the compose `.env`, scoped to the `target` profile.

6. **Databases**: N8N uses **two dedicated PostgreSQL databases** on the same PG instance as `target_db` (cf. Decision 5):
   - **`n8n_db`**: internal N8N data (workflows, credentials, executions) + **LLM memory storage for conversations** via the `memoryPostgresChat` node (tables auto-managed by LangChain, indexed by `conversationId`, 10-message context window)
   - **`agent_memory`**: database prepared for future agent memory use (not currently used)

   **Caution during migration**: `n8n_db` contains the AI context history for conversations. This is not a simple cache — the last 10 messages of each conversation are stored there and serve as LLM memory. Losing this data would degrade AI response quality for ongoing conversations.

   The dual storage is intentional:
   - `n8n_db` → LLM memory (context window for N8N workflows)
   - `target_db` → `conversation`, `message`, `message_content` tables (complete history for the API and user display)

   The `initdb.d/n8n.sh` and `initdb.d/agent_memory.sh` scripts are in `apps/target/n8n/docker/`.

7. **RabbitMQ**: N8N queues (`agent_commands`, `agent_responses`) coexist with other module queues in the shared RabbitMQ. Queue names are already uniquely prefixed (cf. Decision 6 — `target_*` convention for async queues).

8. **N8nApiClient.php**: the PHP code in `apps/target/src/Infrastructure/N8n/` remains unchanged. `N8N_BASE_URL` points to `http://n8n:5678` (internal Docker service). The relative path to workflows is `n8n/workflows/` (same app).

9. **Cross-validation**: the PHP validator (`apps/target/tests/N8N/`) reads workflows from `apps/target/n8n/workflows/` — simple relative path, everything is in the same `apps/target/` directory.

### Consequences

- **Positive**: N8N ecosystem migrated in full, zero loss of functionality, tests and validation preserved, complete documentation
- **Negative**: volume of migrated files (171 files), some relative paths to verify after migration
- **Neutral**: N8N remains a service exclusive to the Target module, deployed only with the `target` profile

### Confirmation

- All 22 workflows are correctly imported into the monorepo compose N8N instance
- `task n8n:validate` passes without error (8 validators, 0 errors)
- `task n8n:test -- add-actor-workflow-evaluation.json` runs tests and produces a report
- Export scripts (`task n8n:export-workflows`) work with `apps/target/n8n/` paths
- The `N8nApiClient` PHP communicates with N8N (`getExecution`, `cancelExecution`)
- N8N documentation is accessible in `apps/target/n8n/docs/`

---

## Decision 15 : Adapt the multi-client deployment infrastructure

### Context

Basil has a sophisticated deployment infrastructure via the **`basil-infra`** repo:

**Current deployment architecture:**

- Main CI pipeline (basil) creates a **deployment package** (ZIP) containing compose files, Traefik configs, N8N workflows, scripts
- The ZIP is uploaded to the **GitLab Package Registry**
- The `basil-infra` repo downloads the package and applies it per client
- **Multi-client**: each client has its own configuration (`clients/<name>.env`, `clients/<name>/secrets.env`)
- **Secrets**: managed by **SOPS + Age** (13 auto-generated secrets per client: APP_SECRET, POSTGRES_PASSWORD, KEYCLOAK_DB_PASSWORD, N8N_DB_PASSWORD, RABBITMQ_PASSWORD, ELASTIC_PASSWORD, etc.)
- **Deployment**: SSH to client server via `scripts/deploy.sh`
- **Rollback**: version rollback support

**Monitoring:**

- `basil-monitoring` repo with Prometheus (20+ alert rules), Loki (30-day retention), Grafana, Alertmanager

ChapsMind has no `basil-infra` equivalent. Deployment is done by pushing Docker images to the registry and pulling in preprod.

### Options considered

#### Option A: Integrate Target into the existing `basil-infra` pipeline

**Description**: continue using `basil-infra` for Target deployment, even in the ChapsMind context.

**Pros:**

- Proven infrastructure (multi-client, SOPS, rollback)
- No new repo to create
- Monitoring already configured (Prometheus, Grafana)

**Cons:**

- `basil-infra` is coupled to the Basil structure (Basil compose, not ChapsMind)
- Basil clients are not the same as ChapsMind organizations
- Dual deployment infrastructure

#### Option B: Create a `chapsmind-infra` repo inspired by `basil-infra` (Retained)

**Description**: create a deployment repo for ChapsMind that adopts the patterns from `basil-infra` (multi-client, SOPS, packages) but adapted to the ChapsMind architecture.

**Pros:**

- Clean deployment architecture for ChapsMind
- Adopts proven patterns from `basil-infra` (SOPS, multi-client, rollback)
- The deployment package includes all modules (Global Service + Screen + Target)
- Target secrets added to the SOPS schema (INTERNAL_JWT_SECRET, TARGET_MERCURE_JWT_SECRET, ELASTIC_PASSWORD_TARGET, etc.)

**Cons:**

- New repo to create and maintain
- Migration of deployment scripts
- Temporary duplication with `basil-infra` during transition

#### Option C: GitOps deployment (ArgoCD/Flux)

**Description**: migrate to GitOps deployment with ArgoCD or FluxCD.

**Pros:**

- State of the art for deployments
- Automatic audit trail

**Cons:**

- Requires Kubernetes (not in place)
- Radical infrastructure change
- Premature for the current context

### Decision

> **Option B retained**: create `chapsmind-infra` inspired by `basil-infra`.

Planned structure:

```
chapsmind-infra/
├── .gitlab-ci.yml           # Deploy pipeline per client
├── .sops.yaml               # SOPS/Age config
├── clients/
│   ├── _common.env          # Shared variables (registry, SMTP, Let's Encrypt)
│   ├── client1.env          # SSH host, domain (SOPS encrypted)
│   ├── client1/
│   │   └── secrets.env      # Auto-generated secrets (SOPS encrypted)
│   └── client2.env
├── scripts/
│   ├── deploy.sh            # SSH deployment script
│   ├── generate-secrets.sh  # Auto secret generation
│   └── rollback.sh          # Rollback script
├── templates/
│   ├── compose.yaml         # Compose template with all modules
│   └── .env.template        # Environment variables
└── monitoring/
    ├── prometheus/           # Alert rules (from basil-monitoring)
    ├── loki/                 # Log config
    └── grafana/              # Dashboards
```

**Target secrets to add:**

```bash
# In generate-secrets.sh
INTERNAL_JWT_SECRET=$(openssl rand -hex 32)
TARGET_APP_SECRET=$(openssl rand -hex 16)
TARGET_POSTGRES_PASSWORD=$(openssl rand -hex 16)
TARGET_MERCURE_JWT_SECRET=$(openssl rand -hex 32)
TARGET_ELASTIC_PASSWORD=$(openssl rand -hex 16)
TARGET_VALKEY_PASSWORD=$(openssl rand -hex 16)
TARGET_N8N_ENCRYPTION_KEY=$(openssl rand -hex 16)
TARGET_WEBHOOK_TOKEN=$(openssl rand -hex 32)
```

**[INVESTIGATE: Define with the team whether `basil-infra` is migrated to `chapsmind-infra` or if both coexist during transition]**

**[INVESTIGATE: Define the monitoring strategy — extend `basil-monitoring` or create unified ChapsMind monitoring]**

### Consequences

- **Positive**: unified ChapsMind deployment, proven patterns (SOPS, multi-client, rollback), Target secrets secured
- **Negative**: new repo to create, temporary duplication with `basil-infra`
- **Neutral**: monitoring can be unified progressively

### Confirmation

- The `chapsmind-infra` pipeline deploys successfully to a test environment
- Secrets are correctly generated and encrypted with SOPS
- Rollback works
- [INVESTIGATE: Validate that Grafana dashboards cover Target metrics (PHP-FPM, Doctrine, ES, Valkey)]

---

## Decision 16 : Adopt a 5-phase migration plan

### Context

The Basil backend migration to ChapsMind cannot be done in a single step. Mutual dependencies (Global Service not yet functional, module check not yet implemented, Internal JWT not yet created) impose an execution order.

The **Strangler Fig pattern** is already adopted by ChapsMind for the Python monolith migration to Global Service + Screen Service (6 phases defined in the API Gateway spec). The Target migration must fit within this framework.

### Migration plan

#### Phase 0: Global Service operational (BLOCKER)

**Objective**: the Global Service must be functional as a minimum viable HTTP proxy.

**Prerequisites (outside Target scope, depends on ChapsMind team)**:

- [ ] The Global Service starts and responds on port 8001
- [ ] The Global Service validates Keycloak JWTs
- [ ] Routing `/api/screen/*` → backend:8000 works
- [ ] Internal JWT is issued for proxied requests

**Exit criteria**: `curl -H "Authorization: Bearer $JWT" http://global-service:8001/api/screen/companies` returns Screen data.

**Risk**: this is the **main blocker** for the entire Target migration. If the Global Service is not ready, subsequent phases cannot begin for routing. However, phases 1 and 2 (code preparation) can proceed in parallel.

#### Phase 1: Import Target code into the monorepo, CI/CD and runners

**Objective**: the Basil API code is integrated into the ChapsMind monorepo (`apps/target/`), with complete CI/CD via `include:local` and operational PHP CI image.

**Prerequisites**: the ChapsMind monorepo is operational (ADR-0011 migrated).

**Actions**:

- [ ] Import code from `basil/api/` into `apps/target/` via `git subtree add` with history
- [ ] Add `apps/target/` to CODEOWNERS (`@chapsmind/team-target`)
- [ ] Create `apps/target/.gitlab-ci.yml` with the `include:local` pattern (cf. Decision 2)
- [ ] Add the `include` line in the monorepo root `.gitlab-ci.yml`
- [ ] Create the `php-target` CI image in the `ci-images` repo (FrankenPHP 8.4 + extensions + CI tools)
- [ ] Adapt CI jobs:
  - Keep: ECS, PHPStan 9, PHPUnit, Trivy, SAST, Secret Detection, OpenAPI export, N8N validation
  - Adapt: Docker registry, staging URLs, `rules:changes` to `apps/target/**/*`
  - Remove: PWA jobs, Docs MkDocs jobs, Renovate (to be configured at monorepo level)
- [ ] Configure GitLab CI services for integration tests (PG 17, ES 9.1, Valkey 8)
- [ ] Validate that ChapsMind runners support GitLab CI `services:` (PG, ES, Valkey)
- [ ] Publish the Target Docker image to the registry

**Exit criteria**: the CI pipeline passes (`target:lint`, `target:test`, `target:security`, `target:build` jobs are green), the Docker image is published. An MR touching only `apps/front/` triggers no Target jobs.

**Parallelizable with**: Phase 0 (no dependency).

#### Phase 2: Docker services and `InternalJwtAuthenticator`

**Objective**: `target` starts in the ChapsMind compose and accepts requests authenticated by Internal JWT.

**Actions**:

- [ ] Add Docker services: `target`, `target-elasticsearch`, `target-valkey`, `target-n8n`, `target-caddy`
- [ ] Create the `init-target-db.sql` script
- [ ] Configure Docker Compose profiles
- [ ] Implement `InternalJwtAuthenticator` in Symfony
- [ ] Adjust `security.yaml` (`internal` firewall in addition to `webhook`)
- [ ] Configure environment variables (`INTERNAL_JWT_SECRET`, `DATABASE_URL`, etc.)
- [ ] Execute Doctrine migrations on `target_db`
- [ ] Execute Elasticsearch migrations

**Exit criteria**: `docker compose --profile target up` starts all services, `target` responds on port 8002 with a valid Internal JWT.

**Parallelizable with**: Phase 0 (most tasks are independent of the Global Service). The InternalJwtAuthenticator can be tested with a locally forged JWT.

#### Phase 3: Global Service → Target API integration + Nginx SSE

**Objective**: routing `/api/target/*` and SSE/Mercure streaming are functional end-to-end.

**Prerequisites**: Phase 0 (Global Service operational) + Phase 2 (target ready).

**Actions**:

- [ ] Add the proxy route `/api/target/*` in the Global Service
- [ ] Configure `TARGET_SERVICE_URL=http://target:8002` in the Global Service
- [ ] Implement the module check for `ModuleName.TARGET`
- [ ] Configure SSE/Mercure routing in the Global Service:
  - Authentication exception for `/.well-known/mercure` (self-sufficient Mercure JWT)
  - Transparent proxy to `target-caddy:80` with streaming (`proxy_buffering off`)
- [ ] Configure OpenAPI documentation (verify base path after `/target` stripping)
- [ ] End-to-end integration tests:
  - API: frontend → Nginx → Global Service → target → response
  - SSE: frontend → Nginx → target-caddy → EventSource receives events
  - Docs: `https://chapsmind.local/api/target/docs` shows Swagger UI

**Exit criteria**:

- `curl -H "Authorization: Bearer $JWT" https://chapsmind.local/api/target/watch_files` returns data
- Organizations without the Target module receive HTTP 403
- `EventSource('https://chapsmind.local/.well-known/mercure?topic=...')` receives SSE events
- Swagger UI accessible and functional

#### Phase 4: Deployment infrastructure, data migration and cutover

**Objective**: the multi-client deployment infrastructure is ready, Basil production data is migrated to `target_db` in the ChapsMind cluster.

**Actions (infrastructure)**:

- [ ] Create the `chapsmind-infra` repo (or adapt `basil-infra`)
- [ ] Configure SOPS/Age with Target secrets (INTERNAL_JWT_SECRET, TARGET_MERCURE_JWT_SECRET, etc.)
- [ ] Adapt deployment scripts for the ChapsMind compose (profiles, Target services)
- [ ] Configure monitoring: Grafana dashboards (PHP-FPM, Doctrine queries, ES, Valkey), Prometheus alerts
- [ ] Deploy to a pre-production environment for validation

**Actions (data)**:

- [ ] PostgreSQL `target_db` data migration script (pg_dump/pg_restore with Keycloak user remapping)
- [ ] `n8n_db` database migration (internal N8N data + LLM conversation memory — do not lose AI context history)
- [ ] `agent_memory` database migration (if in use at migration time)
- [ ] Elasticsearch index migration (full reindexation or snapshot/restore)
- [ ] N8N workflow migration (JSON import via `n8n-import` or `task n8n:export-workflows`)
- [ ] Valkey data import (cache — reconstruction via warm-up is sufficient)
- [ ] Functional parity validation (source vs target data comparison)
- [ ] DNS/routing configuration for cutover
- [ ] Documented rollback plan

**Exit criteria**: the Target application runs on ChapsMind infrastructure with production data, without functional regression. Multi-client deployment is operational.

**[INVESTIGATE: Define the cutover strategy with the ops team — blue/green, canary, or direct migration]**
**[INVESTIGATE: Define whether basil-infra is migrated to chapsmind-infra or if both coexist]**
**[INVESTIGATE: Validate Grafana dashboards for PHP-specific metrics (FPM workers, Doctrine, Mercure)]**

### Decision

> **This 5-phase plan is adopted**, with the following properties:

- Phases 0, 1, 2 can proceed in **parallel**
- Phase 3 requires phases 0 and 2
- Phase 4 requires phase 3
- The main blocker is **Phase 0** (Global Service)

### Consequences

- **Positive**: progressive migration, no big-bang, rollback possible at each phase
- **Negative**: extended migration duration, coexistence period for both stacks
- **Neutral**: each phase has clear and verifiable exit criteria

### Confirmation

- A migration tracking dashboard is kept up to date
- Each phase is validated by integration tests before moving to the next
- A rollback plan is documented for each phase

---

## Decisions summary

| #   | Domain    | Decision                  | Retained option                                                      | Blocker |
| --- | --------- | ------------------------- | -------------------------------------------------------------------- | ------- |
| 1   | Code      | Code structure            | `apps/target/` in the ChapsMind monorepo (ADR-0011)                  | No      |
| 2   | Code      | Monorepo CI/CD            | `include:local` + `rules:changes` (ADR-0011 pattern)                 | No      |
| 3   | Routing   | API endpoints             | HTTP proxy via Global Service                                        | Phase 0 |
| 4   | Auth      | Internal authentication   | InternalJwtAuthenticator (HMAC-SHA256), no more Keycloak comm        | No      |
| 5   | Data      | Database                  | Dedicated `target_db`, same PG instance                              | No      |
| 6   | Infra     | Ancillary services        | Dedicated instances (ES, Valkey, N8N), shared RabbitMQ               | No      |
| 7   | Dev       | Dev isolation             | Docker Compose profiles                                              | No      |
| 8   | Infra     | N8N communication         | Direct (bypass gateway), fully internal webhooks                     | No      |
| 9   | Auth      | Access control            | Module check in Global Service                                       | Phase 0 |
| 10  | Streaming | SSE/Mercure               | Global Service with auth exception → target-caddy (Target-exclusive) | Phase 0 |
| 11  | CI/CD     | Pipeline                  | Full Basil pipeline adapted (PHPStan 9, Trivy, tests)                | No      |
| 12  | CI/CD     | Runners                   | `php-target` CI image in `ci-images` repo                            | No      |
| 13  | Docs      | API documentation         | OpenAPI proxied via Global Service                                   | Phase 0 |
| 14  | Docs      | Technical documentation   | Per-module docs + lightweight centralization (dedicated ADR coming)  | No      |
| 15  | Deploy    | Production infrastructure | `chapsmind-infra` inspired by `basil-infra` (SOPS, multi-client)     | No      |
| 16  | Migration | Overall plan              | 5 phases, Strangler Fig                                              | Phase 0 |
| 17  | N8N       | N8N ecosystem             | Complete migration under `apps/target/n8n/`                          | No      |

### External dependencies (outside the scope of this ADR)

| #   | Subject                                       | Associated ADR                      | Impact on this migration                                                                                                                                                        | Responsible   |
| --- | --------------------------------------------- | ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------- |
| 1   | **Multi-tenant**                              | ADR-2025-002 (to be revised)        | Blocking for multi-client production. `organisation_id` discriminant everywhere, DB/OpenSearch isolation, performance. Must be addressed **before** ChapsMind go-to-production. | @jjo          |
| 2   | **Elasticsearch → OpenSearch migration**      | Dedicated ADR to create             | Prerequisite for multi-tenant (OpenSearch routing). Must be addressed **before** multi-tenant.                                                                                  | @mpazat       |
| 3   | **Target permissions aligned with ChapsMind** | To be defined                       | The Target permissions system must be aligned with ChapsMind's.                                                                                                                 | To be defined |
| 4   | **Centralized technical documentation**       | Dedicated ADR to create (all teams) | Centralization strategy for documentation across all modules.                                                                                                                   | @alu          |
| 5   | **PostgreSQL 16 → 17**                        | Operational action                  | The ChapsMind env is on PG 16, Target requires PG 17. Unify to PG 17 without data loss.                                                                                         | @adnane       |

### Open `[INVESTIGATE]` items

| #   | Item to investigate                                                                            | Decision(s) | Responsible     |
| --- | ---------------------------------------------------------------------------------------------- | ----------- | --------------- |
| 1   | Cutover strategy (blue/green, canary, direct migration)                                        | 16          | Ops team        |
| 2   | `basil-infra` / `chapsmind-infra` coexistence or migration                                     | 15          | Ops team        |
| 3   | Grafana dashboards for PHP metrics (FPM, Doctrine, Mercure)                                    | 15, 16      | Monitoring team |
| 4   | ChapsMind runner compatibility with CI services (PG, ES, Valkey)                               | 12          | CI team         |
| 5   | Unified ChapsMind monitoring strategy                                                          | 15          | Monitoring team |
| 6   | **Target rate limiting**: keep, adapt or remove? (existing OpenAPI factories)                  | —           | Backend team    |
| 7   | **Rename `backend` to `screen`** in the ChapsMind workspace (Docker profiles, CI, docs)        | —           | ChapsMind team  |
| 8   | **Detailed Basil → `target` code migration plan**: inventory of what is kept, adapted, removed | —           | Target team     |
| 9   | **PostgreSQL 16 → 17 migration**: migration plan without data loss for the ChapsMind env       | 5           | Ops team        |

---

## References

**ChapsMind ADRs and specs:**

<!-- Links to files outside the docs/ scope — textual references -->

- ADR-0009: Global Service Architecture — `ChapsMind/chapsmind-workspace/docs/architecture/adr/0009-global-service-architecture.md`
- ADR-0011: Monorepo vs Git Submodules — `ChapsMind/chapsmind-workspace/docs/architecture/adr/0011-monorepo-vs-submodules.md`
- Global Service API Gateway Spec — `ChapsMind/chapsmind-workspace/agent-os/specs/2026-01-14-global-service-api-gateway/spec.md`
- Internal JWT Auth Spec — `ChapsMind/chapsmind-workspace/agent-os/specs/2026-01-14-global-service-api-gateway/internal-jwt-auth.md`
- ChapsMind Product Roadmap — `ChapsMind/chapsmind-workspace/agent-os/product/roadmap.md`
- Organization Module Model — `ChapsMind/chapsmind-workspace/back/app/models/organization.py`

**Basil ADRs and configs:**

- [ADR-2026-007: Target frontend migration → Vue 3](./2026-007-migration-target-to-chapsmind.md)
- [ADR-2025-001: Mercure Scalable Topics and Tokens](./2025-001-mercure-scalable-topics-and-tokens.md)
- Basil API Security Config — `api/config/packages/security.yaml`
- Basil CI/CD Pipeline — `.gitlab-ci.yml`
- Basil API Dockerfile — `api/Dockerfile`

**Infrastructure and CI:**

- ChapsMind Infra Docker Compose — `ChapsMind/chapsmind-workspace/infra/docker-compose.yml`
- ChapsMind Nginx Config — `ChapsMind/chapsmind-workspace/infra/files/nginx.conf`
- ChapsMind CI Images — `ChapsMind/ci-images/.gitlab-ci.yml`
- Basil Infra (multi-client deployment) — `target/basil-infra/.gitlab-ci.yml`
- Basil Monitoring (Prometheus/Grafana/Loki) — `target/basil-monitoring/.gitlab-ci.yml`

## Dependencies and ADRs to create

The following topics were identified during the 2026-02-25 brainstorming as **out of scope** for this ADR but **blocking or structuring** for production go-live:

1. **ADR-2025-002: Multi-Tenant Architecture** (existing, needs full revision)
   - `organisation_id` discriminant on all entities
   - DB strategy (bucket concept)
   - OpenSearch strategy (one index per client?)
   - Zero risk of cross-client data exposure
   - Performance impact
   - See: `docs/adr/2025-002-multi-tenant-architecture.md`

2. **ADR to create: Elasticsearch → OpenSearch migration**
   - Prerequisite for multi-tenant (mandatory OpenSearch routing)
   - Must be addressed before multi-tenant
   - OVH managed OpenSearch migration

3. **ADR to create: Target permissions aligned with ChapsMind**
   - Alignment of the Target permissions system with ChapsMind

4. **ADR to create: ChapsMind centralized technical documentation**
   - Involves all teams (Target, Screen, Platform)
   - Approach: per-module docs, lightweight centralization for display

5. **Operational action: PostgreSQL 16 → 17 migration**
   - The ChapsMind env is on PG 16, Target requires PG 17
   - Unify to PG 17 without data loss
