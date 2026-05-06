"""Health check endpoints for the Stream service.

Provides liveness and readiness probes with OpenAPI schema hash
for gateway autodiscovery.
"""

import hashlib
import json

from fastapi import APIRouter, Request

from app import __version__

router = APIRouter(tags=["health"])

_cached_hash: str | None = None


def _get_openapi_hash(app) -> str:
    """Return a cached truncated SHA-256 hash of the OpenAPI schema.

    Caches on first call since the schema is stable after startup.
    """
    global _cached_hash

    if _cached_hash is not None:
        return _cached_hash

    schema_dict = app.openapi()
    schema_json = json.dumps(schema_dict, sort_keys=True)
    _cached_hash = hashlib.sha256(schema_json.encode()).hexdigest()[:16]
    return _cached_hash


@router.get("/health/live")
def health_live():
    """Liveness probe — confirms the process is running."""
    return {"status": "alive"}


@router.get("/health/ready")
def health_ready(request: Request):
    """Readiness probe — returns status and OpenAPI schema hash."""
    return {
        "status": "ready",
        "openapi_hash": _get_openapi_hash(request.app),
        "version": __version__,
    }
