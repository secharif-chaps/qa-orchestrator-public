"""
Shared proxy utilities — header filtering, body detection, streaming detection.

Used by the generic proxy (proxy/routes.py) to avoid duplication and coupling.
"""

from __future__ import annotations

import httpx
from fastapi import Request

# Headers to exclude from proxying (hop-by-hop, RFC 2616 + security headers)
EXCLUDED_REQUEST_HEADERS: frozenset[str] = frozenset({
    # Hop-by-hop headers (RFC 2616) — must not be forwarded by proxies
    "host",
    "connection",
    "keep-alive",
    "proxy-authenticate",
    "proxy-authorization",
    "te",
    "trailers",
    "transfer-encoding",
    "upgrade",
    "content-length",  # httpx will recalculate this
    "accept-encoding",  # Strip: Caddy/FrankenPHP compresses but gateway streams body as-is
    # Security: prevent client from spoofing forwarding headers
    "x-forwarded-for",
    "x-forwarded-host",
    "x-forwarded-proto",
    "x-forwarded-port",
    "x-real-ip",
    "forwarded",  # RFC 7239
    # Security: prevent proxy chain info leakage
    "via",
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
    "content-encoding",  # Let FastAPI handle compression
    "content-length",  # Will be recalculated
})

# Content types that require streaming with a dedicated long-lived client
STREAMING_CONTENT_TYPES: frozenset[str] = frozenset({
    "text/event-stream",        # Server-Sent Events (SSE)
    "application/x-ndjson",     # Newline Delimited JSON
    "application/stream+json",  # JSON streaming
})


def filter_request_headers(headers: dict) -> dict:
    """Return a copy of *headers* with hop-by-hop and security headers removed."""
    return {key: value for key, value in headers.items() if key.lower() not in EXCLUDED_REQUEST_HEADERS}


def filter_response_headers(headers: httpx.Headers) -> dict:
    """Return a plain dict of *headers* with hop-by-hop headers removed."""
    return {key: value for key, value in headers.items() if key.lower() not in EXCLUDED_RESPONSE_HEADERS}


def has_request_body(request: Request) -> bool:
    """Return True if the request carries a body, without reading it into memory."""
    content_length = request.headers.get("content-length")
    transfer_encoding = request.headers.get("transfer-encoding")
    if content_length:
        try:
            return int(content_length) > 0
        except ValueError:
            return False
    return transfer_encoding == "chunked"


def is_streaming_request(request: Request) -> bool:
    """Return True if the client expects a streaming response (SSE / NDJSON / JSON streaming)."""
    accept = request.headers.get("accept", "")
    accept_lower = accept.lower()
    return any(ct in accept_lower for ct in STREAMING_CONTENT_TYPES)
