# Code Review - Global Service API Gateway Implementation

**Date**: 2026-01-29
**Reviewer**: Claude
**MRs Reviewed**:

- MR 1: `chapsmind-global-service` - Service Foundation & Organization Context
- MR 7: `infra` - Complete global-service setup
- MR 116: `screen-poc` - Gateway internal trust support
- MR 144: `screen-front` - Point local dev to gateway

**Scope**: Python code quality, async patterns, performance, type safety, best practices
**Exclus**: Authentification inter-services (voir `internal-jwt-auth.md`)

---

## Table des Matières

1. [Résumé Exécutif](#résumé-exécutif)
2. [MR 1: chapsmind-global-service](#mr-1-chapsmind-global-service)
3. [MR 7: infra](#mr-7-infra)
4. [MR 116: screen-poc](#mr-116-screen-poc)
5. [MR 144: screen-front](#mr-144-screen-front)
6. [Résumé des Issues](#résumé-des-issues)
7. [Patches Recommandés](#patches-recommandés)

---

## Résumé Exécutif

### Statistiques

| Sévérité     | Count | Description                        |
|--------------|-------|------------------------------------|
| 🔴 Critique  | 5     | Bloquants - à corriger avant merge |
| 🟠 Important | 6     | Devraient être corrigés            |
| 🟡 Mineur    | 8     | Améliorations recommandées         |

### Top 3 Issues Critiques

1. **Blocking sleep dans code async** (`keycloak.py`) - Bloque toutes les requêtes
2. **Race condition** (`proxy/client.py`) - Fuite de connexions possible
3. **Event loop créé à chaque appel gRPC** (`grpc.py`) - Performance dégradée

---

## MR 1: chapsmind-global-service

### 📁 `app/core/config.py`

#### 🔴 Issue #1: Logs exécutés à l'import du module

**Lignes**: 629-635

```python
# Code actuel
logger = logging.getLogger(__name__)
logger.info(f"🔧 CONFIG DEBUG - BACKEND_BASE_URL loaded as: {settings.BACKEND_BASE_URL}")
logger.info(f"🔧 CONFIG DEBUG - ENVIRONMENT: {getattr(settings, 'ENVIRONMENT', 'not set')}")
```

**Problème**: Ces logs s'exécutent à l'import, avant que le logging soit configuré. Ils peuvent:

- Ne pas apparaître
- Polluer stdout avec un format non-standard
- Causer des erreurs si `settings` n'est pas encore initialisé

**Fix recommandé**:

```python
# Supprimer les logs du niveau module
# OU les déplacer dans une fonction appelée au startup

def log_startup_config() -> None:
    """Log configuration values. Call after logging is configured."""
    logger = logging.getLogger(__name__)
    logger.debug(f"BACKEND_BASE_URL: {settings.BACKEND_BASE_URL}")
    logger.debug(f"KEYCLOAK_SERVER_URL: {settings.KEYCLOAK_SERVER_URL}")
```

---

#### 🟡 Issue #2: Pas de validation des settings critiques

**Lignes**: 592-635

```python
# Code actuel
INTERNAL_JWT_SECRET: str = ""  # Peut être vide en production!
DATABASE_URL: str = "postgresql://..."  # Peut être incorrect
```

**Fix recommandé**:

```python
from pydantic import field_validator, model_validator

class Settings(BaseSettings):
    INTERNAL_JWT_SECRET: str = ""
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/global_db"

    @field_validator("INTERNAL_JWT_SECRET")
    @classmethod
    def validate_jwt_secret(cls, v: str) -> str:
        if v and len(v) < 32:
            raise ValueError("INTERNAL_JWT_SECRET must be at least 32 characters")
        return v

    @model_validator(mode="after")
    def validate_production_settings(self) -> "Settings":
        """Ensure critical settings are configured in production."""
        if self.LOG_LEVEL == "INFO":  # Proxy for production
            if not self.INTERNAL_JWT_SECRET:
                import warnings
                warnings.warn("INTERNAL_JWT_SECRET not set - internal auth disabled")
        return self
```

---

### 📁 `app/core/keycloak.py`

#### 🔴 Issue #3: Blocking sleep dans code async

**Lignes**: 1191, 1225, 1264, 1296, 1332, 1365

```python
# Code actuel
time.sleep(backoff)  # BLOCKING - bloque le thread entier!
backoff *= 2
```

**Problème**: `time.sleep()` est bloquant. Dans un serveur ASGI (FastAPI/Uvicorn):

- Bloque le worker thread
- Empêche le traitement de TOUTES les autres requêtes
- Peut causer des timeouts côté client

**Impact**: En cas de problème Keycloak au démarrage, le serveur est complètement bloqué.

**Fix recommandé** (Option A - Init synchrone dans thread pool):

```python
import asyncio
from concurrent.futures import ThreadPoolExecutor

_init_executor = ThreadPoolExecutor(max_workers=1, thread_name_prefix="keycloak-init")

def _initialize_keycloak_sync() -> FastAPIKeycloak:
    """Synchronous initialization with retry (runs in thread pool)."""
    backoff = 2.0
    for attempt in range(1, 6):
        try:
            return FastAPIKeycloak(...)
        except Exception:
            time.sleep(backoff)  # OK dans un thread dédié
            backoff *= 2
    raise ConnectionError("Failed to connect to Keycloak")

async def initialize_keycloak_async() -> FastAPIKeycloak:
    """Async wrapper that runs sync init in thread pool."""
    loop = asyncio.get_event_loop()
    return await loop.run_in_executor(_init_executor, _initialize_keycloak_sync)
```

**Fix recommandé** (Option B - Lazy init au premier appel):

```python
_idp_instance: FastAPIKeycloak | None = None
_idp_lock = asyncio.Lock()

async def get_idp() -> FastAPIKeycloak:
    global _idp_instance
    if _idp_instance is None:
        async with _idp_lock:
            if _idp_instance is None:
                # Init dans thread pool pour ne pas bloquer
                loop = asyncio.get_event_loop()
                _idp_instance = await loop.run_in_executor(
                    None, _initialize_keycloak_sync
                )
    return _idp_instance
```

---

#### 🟡 Issue #4: Exception trop large

**Lignes**: 1301-1304

```python
# Code actuel
except Exception as e:
    logger.error(f"Unexpected error validating JWT: {e}")
    return None
```

**Problème**: Capture toutes les exceptions, y compris:

- `KeyboardInterrupt` (Ctrl+C)
- `SystemExit`
- `MemoryError`
- Bugs dans le code

**Fix recommandé**:

```python
except jwt.ExpiredSignatureError:
    logger.debug("JWT token expired")
    return None
except jwt.JWTError as e:
    logger.debug(f"JWT validation failed: {e}")
    return None
except (ValueError, KeyError) as e:
    logger.warning(f"Malformed JWT payload: {e}")
    return None
except Exception:
    logger.exception("Unexpected error validating JWT")  # Avec traceback
    raise  # Re-raise pour ne pas masquer les vrais bugs
```

---

#### 🟡 Issue #5: Monkey-patching fragile

**Ligne**: 1147

```python
# Code actuel
idp_instance.user_model = OIDCUser
```

**Problème**: Dépend de l'implémentation interne de `fastapi-keycloak`. Peut casser avec une mise à jour.

**Fix recommandé**: Documenter la dépendance et ajouter un test:

```python
# Dans les tests
def test_user_model_attribute_exists():
    """Ensure fastapi-keycloak still supports user_model override."""
    from fastapi_keycloak import FastAPIKeycloak
    assert hasattr(FastAPIKeycloak, 'user_model'), \
        "fastapi-keycloak API changed - update OIDCUser integration"
```

---

### 📁 `app/proxy/client.py`

#### 🔴 Issue #6: Race condition à l'initialisation du client

**Lignes**: 1835-1858

```python
# Code actuel
_client: httpx.AsyncClient | None = None

async def get_proxy_client() -> httpx.AsyncClient:
    global _client
    if _client is None:  # Thread A vérifie: None
        _client = httpx.AsyncClient(...)  # Thread B aussi: crée un second client!
    return _client
```

**Problème**: Deux requêtes concurrentes au démarrage peuvent créer deux clients. L'un sera orphelin (fuite de
connexions).

**Fix recommandé**:

```python
import asyncio

_client: httpx.AsyncClient | None = None
_client_lock = asyncio.Lock()

async def get_proxy_client() -> httpx.AsyncClient:
    """Get or create the shared httpx client with thread-safe initialization."""
    global _client

    # Fast path: client exists and is open
    if _client is not None and not _client.is_closed:
        return _client

    # Slow path: need to create client
    async with _client_lock:
        # Double-check after acquiring lock
        if _client is None or _client.is_closed:
            _client = httpx.AsyncClient(
                base_url=get_backend_base_url(),
                timeout=httpx.Timeout(
                    connect=10.0,
                    read=60.0,
                    write=10.0,
                    pool=10.0,
                ),
                limits=httpx.Limits(
                    max_keepalive_connections=20,
                    max_connections=100,
                    keepalive_expiry=30.0,
                ),
                follow_redirects=False,
            )
            logger.info(f"Proxy client initialized: {get_backend_base_url()}")

    return _client
```

---

#### 🟡 Issue #7: Pas de vérification de l'état du client

**Problème**: Le client peut être fermé (par erreur ou timeout) mais `_client` n'est pas `None`.

**Fix**: Ajouté dans le fix précédent avec `_client.is_closed`.

---

### 📁 `app/proxy/routes.py`

#### 🟡 Issue #8: Utiliser frozenset pour les constantes

**Lignes**: 1923-1947

```python
# Code actuel
EXCLUDED_REQUEST_HEADERS = {
    "host", "connection", ...
}
```

**Fix recommandé**:

```python
# Utiliser frozenset pour l'immutabilité et la documentation d'intention
EXCLUDED_REQUEST_HEADERS: frozenset[str] = frozenset({
    "host",
    "connection",
    "keep-alive",
    "proxy-authenticate",
    "proxy-authorization",
    "te",
    "trailers",
    "transfer-encoding",
    "upgrade",
    "content-length",
})

EXCLUDED_RESPONSE_HEADERS: frozenset[str] = frozenset({
    "connection",
    "keep-alive",
    "proxy-authenticate",
    "proxy-authorization",
    "te",
    "trailers",
    "transfer-encoding",
    "upgrade",
    "content-encoding",
    "content-length",
})
```

---

### 📁 `app/core/grpc.py`

#### 🔴 Issue #9: Event loop créé à chaque appel

**Lignes**: 815-826

```python
# Code actuel
def run_async(coro):
    loop = asyncio.new_event_loop()
    asyncio.set_event_loop(loop)
    try:
        return loop.run_until_complete(coro)
    finally:
        loop.close()
```

**Problèmes**:

1. Création d'event loop très coûteuse (~1-5ms)
2. `set_event_loop()` affecte l'état global du thread
3. Peut causer des conflits avec d'autres coroutines

**Fix recommandé**:

```python
import asyncio
from concurrent.futures import ThreadPoolExecutor

# Thread pool dédié pour l'async dans gRPC
_grpc_async_executor = ThreadPoolExecutor(
    max_workers=4,
    thread_name_prefix="grpc-async"
)

def run_async(coro):
    """
    Run async coroutine from sync gRPC context.

    Uses a dedicated thread pool to avoid blocking the gRPC thread
    and to properly manage event loops.
    """
    def run_in_new_loop():
        return asyncio.run(coro)

    future = _grpc_async_executor.submit(run_in_new_loop)
    return future.result(timeout=30.0)
```

**Alternative avec cache de loop par thread**:

```python
import threading

_thread_loops: dict[int, asyncio.AbstractEventLoop] = {}
_loops_lock = threading.Lock()

def run_async(coro):
    """Run async with cached event loop per thread."""
    thread_id = threading.get_ident()

    with _loops_lock:
        if thread_id not in _thread_loops:
            loop = asyncio.new_event_loop()
            _thread_loops[thread_id] = loop
        else:
            loop = _thread_loops[thread_id]

    return loop.run_until_complete(coro)
```

---

### 📁 `app/core/client_auth.py`

#### 🔴 Issue #10: Type hint incorrect

**Ligne**: 559

```python
# Code actuel
async def get_client_info(token: str) -> Dict[str, any]:  # 'any' minuscule!
```

**Problème**: `any` (minuscule) n'est pas un type valide. C'est une fonction builtin. Le type checker ignorera ce hint.

**Fix recommandé**:

```python
from typing import Any

async def get_client_info(token: str) -> dict[str, Any]:
    """Get client information from validated token."""
    result = await introspect_token(token)

    return {
        "client_id": result.get("client_id"),
        "scope": result.get("scope", "").split(),
        "active": result.get("active", False),
        "token_type": result.get("token_type", "Bearer"),
        "exp": result.get("exp"),
    }
```

---

#### 🟡 Issue #11: response.json() n'est pas async dans httpx

**Ligne**: 528

```python
# Code actuel - potentiellement incorrect selon la version httpx
result = await response.json()
```

**Fix recommandé**:

```python
# httpx Response.json() est synchrone
result = response.json()
```

---

### 📁 `app/grpc_services/organization.py`

#### 🟡 Issue #12: Code mort après context.abort()

**Lignes**: 1742-1743, 1753-1754, 1767-1768

```python
# Code actuel
context.abort(grpc.StatusCode.INVALID_ARGUMENT, "organization_id is required")
return None  # DEAD CODE - abort() lève une exception
```

**Fix recommandé**:

```python
# Supprimer les return None après abort()
context.abort(grpc.StatusCode.INVALID_ARGUMENT, "organization_id is required")
# Pas de return nécessaire
```

---

#### 🟠 Issue #13: Valeurs hardcodées

**Lignes**: 1756-1760

```python
# Code actuel
return organization_pb2.GetOrganizationContextResponse(
    organization_id=request.organization_id,
    name="Demo Org",  # HARDCODED!
    enabled_modules=["Screen", "Target", "Explore"],  # HARDCODED!
)
```

**Problème**: Pas de lecture depuis la base de données ou le service.

**Fix recommandé**:

```python
# Injecter le service
def __init__(self, organization_service: OrganizationService):
    self._org_service = organization_service

def GetOrganizationContext(self, request, context):
    org = self._org_service.get_by_id(request.organization_id)
    if not org:
        context.abort(grpc.StatusCode.NOT_FOUND, "Organization not found")

    return organization_pb2.GetOrganizationContextResponse(
        organization_id=org.id,
        name=org.name,
        enabled_modules=org.enabled_modules,
    )
```

---

#### 🟡 Issue #14: Pas de type hints sur les méthodes gRPC

**Lignes**: 1724, 1770

```python
# Code actuel
def GetOrganizationContext(self, request, context):
def IsModuleEnabled(self, request, context):
```

**Fix recommandé**:

```python
import grpc
from app.grpc_generated import organization_pb2, organization_pb2_grpc

class OrganizationService(organization_pb2_grpc.OrganizationServiceServicer):

    def GetOrganizationContext(
        self,
        request: organization_pb2.GetOrganizationContextRequest,
        context: grpc.ServicerContext,
    ) -> organization_pb2.GetOrganizationContextResponse:
        """Get organization context for the given organization ID."""
        ...

    def IsModuleEnabled(
        self,
        request: organization_pb2.IsModuleEnabledRequest,
        context: grpc.ServicerContext,
    ) -> organization_pb2.IsModuleEnabledResponse:
        """Check if a module is enabled for the organization."""
        ...
```

---

### 📁 `app/core/logging_config.py`

#### 🟡 Issue #15: Pas de support JSON logging

**Problème**: En production (Kubernetes), les logs JSON sont préférables pour l'indexation (ELK, Loki).

**Fix recommandé**:

```python
import json
import sys
from typing import Literal

class JsonFormatter(logging.Formatter):
    """JSON formatter for structured logging."""

    def format(self, record: logging.LogRecord) -> str:
        log_data = {
            "timestamp": self.formatTime(record),
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage(),
        }

        # Add extra fields if present
        if hasattr(record, "extra"):
            log_data.update(record.extra)

        # Add exception info if present
        if record.exc_info:
            log_data["exception"] = self.formatException(record.exc_info)

        return json.dumps(log_data)


def setup_logging(
    level: str = "INFO",
    format: Literal["text", "json"] = "text"
) -> None:
    """Configure application logging.

    Args:
        level: Logging level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
        format: Output format - 'text' for development, 'json' for production
    """
    root_logger = logging.getLogger()
    root_logger.setLevel(getattr(logging, level.upper()))

    handler = logging.StreamHandler(sys.stdout)

    if format == "json":
        handler.setFormatter(JsonFormatter())
    else:
        handler.setFormatter(logging.Formatter(
            "%(asctime)s - %(name)s - %(levelname)s - %(message)s"
        ))

    root_logger.addHandler(handler)

    # Silence noisy libraries
    for logger_name in ("urllib3", "sqlalchemy.engine", "httpx", "httpcore"):
        logging.getLogger(logger_name).setLevel(logging.WARNING)
```

---

### 📁 `alembic/env.py`

#### 🟡 Issue #16: Pas de support multi-schema

**Problème**: La spec prévoit `global_schema` et `screen_schema`, mais Alembic n'est pas configuré pour.

**Fix recommandé**:

```python
def run_migrations_online() -> None:
    # ...
    with connectable.connect() as connection:
        context.configure(
            connection=connection,
            target_metadata=target_metadata,
            # Support multi-schema
            version_table_schema="global_schema",
            include_schemas=True,
            # Compare server defaults for better autogenerate
            compare_server_default=True,
        )

        with context.begin_transaction():
            # Set search_path for this connection
            connection.execute(text("SET search_path TO global_schema, public"))
            context.run_migrations()
```

---

## MR 7: infra

### 📁 `docker-compose.yml`

#### 🔴 Issue #17: Variables d'environnement vides

**Lignes**: 107-108

```yaml
# Code actuel
environment:
  KEYCLOAK_CLIENT_SECRET:
  KEYCLOAK_ADMIN_CLIENT_SECRET:
```

**Problème**: Ces valeurs seront vides, causant des erreurs d'authentification.

**Fix recommandé**:

```yaml
environment:
  # Fail fast if not set
  KEYCLOAK_CLIENT_SECRET: ${KEYCLOAK_CLIENT_SECRET:?KEYCLOAK_CLIENT_SECRET is required}
  KEYCLOAK_ADMIN_CLIENT_SECRET: ${KEYCLOAK_ADMIN_CLIENT_SECRET:?KEYCLOAK_ADMIN_CLIENT_SECRET is required}

  # OU avec valeur par défaut pour dev
  KEYCLOAK_CLIENT_SECRET: ${KEYCLOAK_CLIENT_SECRET:-dev-secret-change-in-prod}
```

---

#### 🟠 Issue #18: Pas de healthcheck pour global-service

```yaml
# Code actuel
global-service:
  image: registry.git.mediaspeech.com/mint/chapsmind-global-service:preprod
  # Pas de healthcheck!
```

**Fix recommandé**:

```yaml
global-service:
  image: registry.git.mediaspeech.com/mint/chapsmind-global-service:preprod
  healthcheck:
    test: [ "CMD", "curl", "-f", "http://localhost:8001/api/health" ]
    interval: 30s
    timeout: 10s
    retries: 3
    start_period: 60s  # Temps pour init Keycloak
```

---

#### 🟡 Issue #19: Pas de resource limits

**Fix recommandé**:

```yaml
global-service:
  # ...
  deploy:
    resources:
      limits:
        cpus: '2'
        memory: 1G
      reservations:
        cpus: '0.5'
        memory: 512M
```

---

### 📁 `docker-compose.local.yml`

#### ✅ Correct

- Hot reload configuré avec volumes
- Ports exposés correctement
- Dépendances bien définies

---

### 📁 `files/nginx.conf`

#### ✅ Correct

- SSE support avec `proxy_buffering off`
- CORS preflight géré
- Headers forwarding corrects

---

## MR 116: screen-poc

### 📁 `app/core/middleware.py`

#### 🟠 Issue #20: Skip paths trop larges

**Lignes**: 213-220

```python
# Code actuel
SKIP_PATHS = [
    "/api/chapse/chat",
    "/api/organizations/",  # Tout le module!
    "/api/companies/",      # Tout le module!
]
```

**Problème**: Désactive la validation JSON pour des modules entiers au lieu d'endpoints spécifiques.

**Fix recommandé**:

```python
import re
from typing import Pattern

# Patterns spécifiques au lieu de préfixes larges
SKIP_VALIDATION_PATTERNS: tuple[Pattern[str], ...] = (
    re.compile(r"^/api/chapse/chat$"),                    # SSE endpoint
    re.compile(r"^/api/organizations/[^/]+/tokens/?$"),   # Token endpoints
    re.compile(r"^/api/organizations/[^/]+/modules/?"),   # Module endpoints
    re.compile(r"^/api/companies/search$"),               # Search endpoint
)

def should_skip_json_validation(path: str) -> bool:
    """Check if JSON validation should be skipped for this path."""
    return any(pattern.match(path) for pattern in SKIP_VALIDATION_PATTERNS)

class JSONValidationMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        if request.method == "OPTIONS":
            return await call_next(request)

        if should_skip_json_validation(request.url.path):
            return await call_next(request)

        # ... rest of validation
```

---

### 📁 `app/api/endpoints/modules.py`

#### 🟡 Issue #21: Logique de toggle verbeuse

**Lignes**: 134-160

```python
# Code actuel
async def toggle_module(
    organization_id: str,
    module: ModuleName,
    body: ModuleUpdateRequest | None = None,
    ...
):
    current_module = token_manager.get_or_create_module(organization_id, module)

    if body is not None and body.enabled is not None:
        new_enabled = body.enabled
    else:
        new_enabled = not current_module.enabled
```

**Fix recommandé** (plus Pythonic):

```python
async def toggle_module(
    organization_id: str,
    module: ModuleName,
    body: ModuleUpdateRequest | None = None,
    ...
):
    current_module = token_manager.get_or_create_module(organization_id, module)

    # Utiliser l'opérateur walrus pour plus de clarté
    new_enabled = (
        body.enabled
        if body is not None and body.enabled is not None
        else not current_module.enabled
    )

    # OU avec getattr
    explicit_value = getattr(body, 'enabled', None) if body else None
    new_enabled = explicit_value if explicit_value is not None else not current_module.enabled
```

---

### 📁 Multiple files - Constantes dupliquées

#### 🟠 Issue #22: DRY violation

Les constantes sont définies dans 3 fichiers:

- `app/core/keycloak.py`
- `app/core/internal_auth.py`
- `app/core/organization.py`

**Fix recommandé**: Créer `app/core/constants.py`:

```python
"""
Shared constants for the application.

This module centralizes constants used across multiple modules
to avoid duplication and ensure consistency.
"""

# Internal authentication headers
# NOTE: These will be replaced by JWT-based auth (see internal-jwt-auth.md)
INTERNAL_REQUEST_HEADER = "X-Internal-Request"
INTERNAL_REQUEST_SECRET = "gateway-internal-v1"

# User context headers from gateway
USER_ID_HEADER = "X-User-Id"
USER_NAME_HEADER = "X-User-Name"
USER_ROLES_HEADER = "X-User-Roles"
USER_ORG_HEADER = "X-User-Organization"

# Authentication prefixes
BEARER_PREFIX = "Bearer "
INTERNAL_PREFIX = "Internal "
```

Puis importer:

```python
from app.core.constants import INTERNAL_REQUEST_HEADER, INTERNAL_REQUEST_SECRET
```

---

## MR 144: screen-front

### 📁 `.env`

#### 🟡 Issue #23: Pas de newline à la fin du fichier

```bash
# Code actuel
VITE_LOCAL_BACKEND_API=http://localhost:8001/api  # Pas de newline
```

**Problème**: Certains outils Unix peuvent mal interpréter la dernière ligne.

**Fix recommandé**: Ajouter une ligne vide à la fin du fichier.

---

#### ✅ Changements corrects

- Port changé de 8000 à 8001 pour pointer vers le gateway
- Commentaires explicatifs ajoutés

---

## Résumé des Issues

### 🔴 Critiques (Blockers)

| #  | MR | Fichier              | Issue                            | Impact                                |
|----|----|----------------------|----------------------------------|---------------------------------------|
| 3  | 1  | `keycloak.py`        | `time.sleep()` blocking          | Serveur bloqué pendant retry Keycloak |
| 6  | 1  | `proxy/client.py`    | Race condition init client       | Fuite de connexions                   |
| 9  | 1  | `grpc.py`            | Event loop créé à chaque appel   | Performance gRPC dégradée             |
| 10 | 1  | `client_auth.py`     | Type hint `any` au lieu de `Any` | Erreur de typage                      |
| 17 | 7  | `docker-compose.yml` | Secrets vides                    | Auth failure en prod                  |

### 🟠 Importants

| #  | MR  | Fichier              | Issue                          |
|----|-----|----------------------|--------------------------------|
| 1  | 1   | `config.py`          | Logs exécutés à l'import       |
| 13 | 1   | `grpc_services/*.py` | Valeurs hardcodées             |
| 18 | 7   | `docker-compose.yml` | Pas de healthcheck             |
| 20 | 116 | `middleware.py`      | Skip paths trop larges         |
| 22 | 116 | Multiple             | Constantes dupliquées          |
| -  | 116 | `internal_auth.py`   | À supprimer (remplacé par JWT) |

### 🟡 Mineurs

| #  | MR | Fichier              | Issue                             |
|----|----|----------------------|-----------------------------------|
| 2  | 1  | `config.py`          | Pas de validation des settings    |
| 4  | 1  | `keycloak.py`        | Exception trop large              |
| 5  | 1  | `keycloak.py`        | Monkey-patching fragile           |
| 8  | 1  | `proxy/routes.py`    | Utiliser `frozenset`              |
| 11 | 1  | `client_auth.py`     | `response.json()` await incorrect |
| 12 | 1  | `grpc_services/*.py` | Code mort après `abort()`         |
| 14 | 1  | `grpc_services/*.py` | Pas de type hints                 |
| 15 | 1  | `logging_config.py`  | Pas de JSON logging               |

---

## Patches Recommandés

### Patch 1: Fix blocking sleep (MR 1 - CRITIQUE)

```python
# app/core/keycloak.py

# Ajouter au début du fichier
from concurrent.futures import ThreadPoolExecutor

_keycloak_init_executor = ThreadPoolExecutor(max_workers=1, thread_name_prefix="kc-init")

# Modifier _initialize_keycloak_with_retry pour être appelé dans le executor
# Le time.sleep() est OK dans un thread dédié

# Modifier get_idp() pour utiliser l'executor
async def get_idp_async() -> FastAPIKeycloak:
    """Get Keycloak IDP with async-safe initialization."""
    global _idp_instance
    if _idp_instance is None:
        loop = asyncio.get_event_loop()
        _idp_instance = await loop.run_in_executor(
            _keycloak_init_executor,
            _initialize_keycloak_with_retry
        )
    return _idp_instance
```

### Patch 2: Fix race condition (MR 1 - CRITIQUE)

```python
# app/proxy/client.py

import asyncio

_client: httpx.AsyncClient | None = None
_client_lock = asyncio.Lock()

async def get_proxy_client() -> httpx.AsyncClient:
    global _client
    if _client is not None and not _client.is_closed:
        return _client

    async with _client_lock:
        if _client is None or _client.is_closed:
            _client = httpx.AsyncClient(...)
            logger.info(f"Proxy client initialized")
    return _client
```

### Patch 3: Fix type hint (MR 1 - CRITIQUE)

```python
# app/core/client_auth.py

from typing import Any  # Ajouter l'import

async def get_client_info(token: str) -> dict[str, Any]:  # Corriger le type
```

### Patch 4: Fix docker secrets (MR 7 - CRITIQUE)

```yaml
# docker-compose.yml

environment:
  KEYCLOAK_CLIENT_SECRET: ${KEYCLOAK_CLIENT_SECRET:-}
  KEYCLOAK_ADMIN_CLIENT_SECRET: ${KEYCLOAK_ADMIN_CLIENT_SECRET:-}
```

### Patch 5: Add healthcheck (MR 7 - IMPORTANT)

```yaml
# docker-compose.yml

global-service:
  healthcheck:
    test: [ "CMD", "curl", "-f", "http://localhost:8001/api/health" ]
    interval: 30s
    timeout: 10s
    retries: 3
    start_period: 60s
```

---

## Checklist de Validation

### Avant Merge MR 1

- [ ] Fix `time.sleep()` blocking dans `keycloak.py`
- [ ] Fix race condition dans `proxy/client.py`
- [ ] Fix type hint `any` → `Any` dans `client_auth.py`
- [ ] Supprimer logs au niveau module dans `config.py`
- [ ] Supprimer code mort après `context.abort()` dans gRPC services
- [ ] Ajouter type hints aux méthodes gRPC

### Avant Merge MR 7

- [ ] Fix secrets vides dans `docker-compose.yml`
- [ ] Ajouter healthcheck pour global-service
- [ ] Ajouter resource limits (optionnel mais recommandé)

### Avant Merge MR 116

- [ ] Réduire les skip paths dans `middleware.py`
- [ ] Créer `constants.py` pour centraliser les constantes
- [ ] Supprimer `internal_auth.py` (sera remplacé par JWT)

### Avant Merge MR 144

- [ ] Ajouter newline à la fin de `.env`

---

## Notes Additionnelles

### Tests Recommandés

```python
# tests/core/test_proxy_client.py

import pytest
import asyncio
from app.proxy.client import get_proxy_client, close_proxy_client

@pytest.mark.asyncio
async def test_concurrent_client_init():
    """Ensure only one client is created under concurrent access."""
    await close_proxy_client()  # Reset

    # Launch 10 concurrent calls
    clients = await asyncio.gather(*[get_proxy_client() for _ in range(10)])

    # All should be the same instance
    assert all(c is clients[0] for c in clients)
```

### Métriques à Ajouter (Future)

```python
# Prometheus metrics for monitoring
from prometheus_client import Counter, Histogram

proxy_requests_total = Counter(
    'gateway_proxy_requests_total',
    'Total proxy requests',
    ['method', 'path', 'status']
)

proxy_request_duration = Histogram(
    'gateway_proxy_request_duration_seconds',
    'Proxy request duration',
    ['method', 'path']
)
```