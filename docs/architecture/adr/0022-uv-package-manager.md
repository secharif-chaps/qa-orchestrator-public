# ADR-0022: Migrate from Poetry to uv for Python Services

## Status

**Proposed** — 2026-03-20

**Date:** 2026-03-20

**Decision Makers:**

**Tags:** backend, tooling, python, dependencies, devex

---

## Context

ChapsMind has two active Python services:

- **`apps/screen/`** — FastAPI backend (Python 3.11) — automated company data collection
- **`apps/global-service/`** — FastAPI API Gateway (Python 3.12) — routing, shared services, gRPC

Both services have used **Poetry** as their dependency manager since inception. Poetry was a solid choice at the time, but several pain points have emerged:

- **Slow Docker builds**: Poetry is installed via a curl script (~30s) then resolves dependencies in Python (~60-120s per build)
- **Dockerfile complexity**: 4-step setup (`curl install` → `symlink` → `config virtualenvs.create false` → `install`)
- **CI image coupling**: the `python-ci:latest` image must embed Poetry for `test` stages
- **Proprietary lock file**: `poetry.lock` format depends on the installed Poetry version
- **Dependency updates**: `poetry update` behavior can be surprising (non-deterministic global resolution depending on version)

The **uv** project (Astral, creators of Ruff) became stable in 2024 and offers a Rust-written alternative that is fully compatible with the standard `pyproject.toml` format (PEP 517/621).

An explicit TODO already exists in `apps/global-service/Dockerfile`:

```bash
# TODO: Consider migrating to uv for faster builds (10-100x faster than Poetry)
```

---

## Decision

We **adopt uv** as the Python dependency manager for all ChapsMind services, replacing Poetry.

Migration is performed service by service, starting with `global-service` (Python 3.12, most recent stack, fewer dependencies), then `screen`.

---

## Options Considered

### Option 1: Keep Poetry (status quo)

**Description:** No change, continue using Poetry on both services.

**Pros:**

- No migration effort required
- Known and well-understood behavior

**Cons:**

- Slow Docker builds (120-180s) vs uv builds (~15-30s)
- Dockerfile complexity maintained unnecessarily
- Poetry is slower to support new Python versions
- Diverges from emerging standards (native PEP 517/621)
- **Rejected**: maintenance cost outweighs migration cost

### Option 2: Migrate to uv (chosen)

**Description:** Replace Poetry with uv across both Python services: `pyproject.toml`, `Dockerfile`, `.gitlab-ci.yml`.

**Pros:**

- **10-100x faster dependency installation** — uv is written in Rust; resolving and installing dependencies goes from 60-120s (Poetry) to ~5-10s. Direct impact: faster Docker builds, faster CI pipelines, faster developer feedback loop
- **Dockerfile reduced from 4 steps to 2** — Poetry requires `curl install` → `symlink` → `config virtualenvs.create false` → `install`; uv replaces all of this with `COPY --from=ghcr.io/astral-sh/uv:latest /uv /bin/uv` + `uv sync --frozen --system`
- **Standard PEP 621 `pyproject.toml`** — `[project]` table is readable by pip, hatch, pdm, and any PEP 621-compatible tool; no Poetry vendor lock-in
- **Consistent Astral tooling** — Ruff is already in use on both services; uv (same team, same ecosystem) completes the stack without introducing a new vendor
- **Zero impact on developer workflow** — all operations go through Docker (`task test`, `task lint`, `task screen:shell`); developers do not install uv locally and see no change in their daily commands
- **Deterministic `uv.lock`** — portable across uv versions, unlike `poetry.lock` which depends on the installed Poetry version
- **Fully compatible with our stack**: FastAPI, SQLAlchemy, gRPC, Celery, Alembic

**Cons:**

- Migration effort: update `pyproject.toml`, `Dockerfile`, CI on 2 services
- CI image `python-ci:latest` must be rebuilt to include uv

**Identified risks and mitigations:**

- `python-jose[cryptography]` — Poetry extras syntax (`{extras = ["cryptography"]}`) → uv (`"python-jose[cryptography]>=x.x"`): different syntax to verify on each dependency
- **Exact-pinned dependencies** — In Poetry, `fastapi-keycloak = "1.1.1"` is an exact constraint (`==1.1.1`). In uv (PEP 621), a bare version without operator means `>=1.1.1` — must be written explicitly as `"fastapi-keycloak==1.1.1"`. Verify all pinned dependencies when converting `pyproject.toml`.
- **Caret (`^`) constraints lose their upper bound** — Poetry's `^8.2.0` means `>=8.2.0,<9.0.0`; the naive uv translation `>=8.2.0` drops the major-version cap. The PEP 440 compatible release operator `~=` is the idiomatic equivalent: `~=8.2` means `>=8.2,<9`. **TODO (future story)**: audit all `^`-pinned dependencies during migration and replace with `~=` to preserve the original intent. Suggested strategy: `~=` for production deps (FastAPI, SQLAlchemy…), `>=` acceptable for dev-only tools (pytest, ruff) where semver is well respected.
- Alembic runs inside Docker: unaffected, uv installs into the system environment just like Poetry with `virtualenvs.create false`
- `screen` is on Python 3.11 — no Python upgrade needed for this migration

---

## Consequences

### Positive

- **5-10x faster Docker builds** — Poetry install: 120-180s; uv equivalent: ~15-30s. Compounded over all CI runs and developer rebuilds, this is the primary driver of the migration
- **Dockerfile from 8 lines to 3** — less complexity, less surface area for misconfiguration
- **No Poetry vendor lock-in** — standard PEP 621 `[project]` table works with pip, hatch, pdm, and any future tool; switching away from uv would require no `pyproject.toml` changes
- **Unified Astral tooling** — Ruff + uv; one team, one ecosystem, consistent release cadence
- **Transparent to developers** — the daily workflow (`task test`, `task lint`, `task screen:shell`) is unchanged; uv runs inside Docker only
- **Deterministic `uv.lock`** — guaranteed reproducibility regardless of environment

### Negative

- **`pyproject.toml` migration** required on 2 services (convert `[tool.poetry.*]` → `[project]`)
- **Rebuild CI image `python-ci:latest`** to include uv instead of Poetry
- **`poetry.lock` removal** — git history of the lock file is lost (acceptable)

### Neutral

- `pyproject.toml` retains all non-Poetry sections (`[tool.ruff]`, `[tool.pytest.ini_options]`) without modification
- Docker environment variables and Alembic commands are unchanged
- The `task screen:shell` / `task screen:test` workflow remains identical

---

## Implementation Notes

### `pyproject.toml` migration

```toml
# BEFORE (Poetry)
[tool.poetry]
name = "global-service"
version = "0.1.0"
package-mode = false

[tool.poetry.dependencies]
python = "^3.12"
fastapi = "^0.128.0"
python-jose = {extras = ["cryptography"], version = "^3.3.0"}

[tool.poetry.group.dev.dependencies]
pytest = "^8.2.0"

[build-system]
requires = ["poetry-core"]
build-backend = "poetry.core.masonry.api"
```

```toml
# AFTER (uv)
[project]
name = "global-service"
version = "0.1.0"
description = "Chapsmind Global Service"
requires-python = ">=3.12"
dependencies = [
    "fastapi~=0.128",                        # ^0.128.0 → ~=0.128 (>=0.128,<1)
    "python-jose[cryptography]~=3.3",        # ^3.3.0  → ~=3.3  (>=3.3,<4)
    # ...
]

[dependency-groups]
dev = [
    "pytest>=8.2.0",  # ^8.2.0 → >= acceptable for dev tools (semver well respected)
    # ...
]

[tool.uv]
package = false
```

> **No `[build-system]`**: our services are containerized Docker applications, not distributable PyPI packages (`pip install chapsmind-screen` never happens). Poetry required `[build-system]` (with `poetry-core`) even for apps. uv skips it when `[tool.uv] package = false` is set — the section is intentionally omitted. Removing `hatchling` and its transitive dependencies (`packaging`, `pathspec`, `pluggy`, `trove-classifiers`, ~15-20 MB) reduces the image attack surface and the number of Trivy findings.

Watch out: Poetry extras syntax `{extras = ["..."], version = "..."}` becomes `"package[extra]>=version"`.

### Dockerfile migration

```dockerfile
# BEFORE (Poetry — 8 lines)
RUN curl -sSL https://install.python-poetry.org | POETRY_HOME=/opt/poetry python3 - && \
    ln -s /opt/poetry/bin/poetry /usr/local/bin/poetry && \
    poetry --version
COPY pyproject.toml poetry.lock ./
RUN poetry config virtualenvs.create false
RUN poetry install --no-interaction --no-ansi --no-root
```

```dockerfile
# AFTER (uv — 3 lines)
COPY --from=ghcr.io/astral-sh/uv:latest /uv /bin/uv
COPY pyproject.toml uv.lock ./
RUN uv sync --frozen --no-cache --system
```

> **Note**: `--system` is the equivalent of `poetry config virtualenvs.create false` — without this flag, uv creates a virtualenv by default, which breaks imports inside the container.

### CI/CD migration (`.gitlab-ci.yml`)

```yaml
# BEFORE
before_script:
  - cd apps/global-service
  - poetry config virtualenvs.create false && poetry install --no-interaction

# Lint job (BEFORE)
script:
  - poetry check && poetry check --lock

# AFTER
before_script:
  - cd apps/global-service
  - uv sync --frozen

# Lint job (AFTER)
script:
  - uv lock --check  # equivalent to poetry check --lock
```

The CI image `${CI_REGISTRY}/chapsmind/ci-images/python-ci:latest` must be rebuilt to include uv. Transitional alternative: add `pip install uv` to `before_script` until the image is updated.

### Command equivalence

| Poetry                          | uv                                   |
| ------------------------------- | ------------------------------------ |
| `poetry install`                | `uv sync`                            |
| `poetry install --no-dev`       | `uv sync --no-dev`                   |
| `poetry add requests`           | `uv add requests`                    |
| `poetry add --group dev pytest` | `uv add --dev pytest`                |
| `poetry remove requests`        | `uv remove requests`                 |
| `poetry update requests`        | `uv lock --upgrade-package requests` |
| `poetry update`                 | `uv lock --upgrade`                  |
| `poetry run python script.py`   | `uv run python script.py`            |
| `poetry lock`                   | `uv lock`                            |
| `poetry check --lock`           | `uv lock --check`                    |
| `pip-audit` (security audit)    | `uvx pip-audit`                      |

### Security audit

uv has no native `audit` command. The recommended tool is **pip-audit** (maintained by PyPA), runnable without installation via `uvx`:

```bash
# One-off audit (no permanent installation)
uvx pip-audit

# Or audit via exported lockfile (recommended in CI)
uv export --no-hashes | uvx pip-audit -r /dev/stdin
```

> **`uvx`** is a shorthand for `uv tool run`: it downloads and runs the tool in a temporary isolated environment, without polluting project dependencies. It is the Node `npx` equivalent.

For permanent CI integration, install the tool globally:

```bash
uv tool install pip-audit
pip-audit  # available in PATH
```

### Manual verification after migration

After rebuilding the Docker image, verify that uv is managing dependencies:

```bash
docker build -t global-service-uv-test apps/global-service/

docker run --rm --entrypoint sh global-service-uv-test -c "
  which poetry 2>/dev/null && echo 'poetry found' || echo 'poetry not found';
  which uv && uv --version;
  ls /app/uv.lock /app/poetry.lock 2>/dev/null || echo 'poetry.lock absent';
"
```

Expected output:

```bash
poetry not found
/bin/uv
uv x.x.x (x86_64-unknown-linux-musl)
/app/uv.lock
poetry.lock absent
```

| Check                | Expected           |
| -------------------- | ------------------ |
| `which poetry`       | `poetry not found` |
| `which uv`           | `/bin/uv`          |
| `uv.lock` present    | ✅                 |
| `poetry.lock` absent | ✅                 |

### Recommended migration order

1. **`global-service`** first (Python 3.12, most recent stack, fewer dependencies)
2. **`screen`** second (more dependencies, Python 3.11)

---

## Performance Benchmarks

Measured on a FastAPI project with 47 dependencies, cold cache (M1 MacBook, Python 3.11):

| Operation           | uv   | Poetry | Ratio       |
| ------------------- | ---- | ------ | ----------- |
| `install` (47 deps) | 1.2s | 38s    | ~32x faster |
| `add` (1 package)   | 0.3s | 18s    | ~60x faster |

These figures are consistent with Astral's official benchmarks (10-100x faster than pip-based tools) and with reported CI/CD improvements across the community.

Sources:

- [Docker vs Poetry vs uv: 3 Setup Patterns That Actually Scale — TildAlice (2026)](https://tildalice.io/docker-poetry-uv-python-setup-2026/)
- [Poetry Was Good, Uv Is Better: An MLOps Migration Story — Fmind/Medium (2025)](https://fmind.medium.com/poetry-was-good-uv-is-better-an-mlops-migration-story-f52bf0c6c703)
- [Poetry versus uv — Loopwerk (2024)](https://www.loopwerk.io/articles/2024/python-poetry-vs-uv/)
- [Official uv benchmarks — astral-sh/uv BENCHMARKS.md](https://github.com/astral-sh/uv/blob/main/BENCHMARKS.md)

---

## References

- [TAR-1027 — Study: Poetry to uv migration](https://chapsvisiondev.atlassian.net/browse/TAR-1027)
- [uv — Astral](https://github.com/astral-sh/uv)
- [uv Documentation](https://docs.astral.sh/uv/)
- [ADR-0002: FastAPI Backend](./0002-fastapi-backend.md)
