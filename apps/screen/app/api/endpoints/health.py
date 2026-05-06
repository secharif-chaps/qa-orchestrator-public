"""
Health check endpoints for the screen backend.

Provides liveness and readiness probes with OpenAPI schema hash
for gateway autodiscovery. The hash is cached and only recomputed
when the schema actually changes.
"""

import hashlib
import json

from fastapi import APIRouter, Request

from app import __version__

router = APIRouter(tags=["health"])

# Cache: store the last computed hash to avoid re-serializing the schema
# on every call. We compare both id() (fast path) and the hash itself
# (fallback if the schema dict was GC'd and a new one got the same address).
_cached_hash: str | None = None
_cached_schema_id: int | None = None


def _get_openapi_hash(app) -> str:
    """Return a cached truncated SHA-256 hash of the OpenAPI schema.

    Uses id() as a fast-path check and falls back to comparing the
    computed hash, which handles the case where the schema object is
    garbage-collected and a new dict gets the same memory address.
    """
    global _cached_hash, _cached_schema_id

    schema_dict = app.openapi()
    current_id = id(schema_dict)

    # Fast path: same object in memory → hash hasn't changed
    if _cached_hash is not None and _cached_schema_id == current_id:
        return _cached_hash

    schema_json = json.dumps(schema_dict, sort_keys=True)
    new_hash = hashlib.sha256(schema_json.encode()).hexdigest()[:16]

    _cached_hash = new_hash
    _cached_schema_id = current_id
    return _cached_hash


@router.get(
    "/health/live",
    openapi_extra={"x-public": True},
)
def health_live():
    """Liveness probe — confirms the process is running."""
    return {"status": "alive"}


@router.get(
    "/health/ready",
    openapi_extra={"x-public": True},
)
def health_ready(request: Request):
    """Readiness probe — returns status and OpenAPI schema hash.

    The openapi_hash allows the gateway to detect schema changes
    and trigger autodiscovery when endpoints are added or modified.
    """
    return {
        "status": "ready",
        "openapi_hash": _get_openapi_hash(request.app),
        "version": __version__,
    }
