"""
Correlation ID middleware for end-to-end request tracing.

Generates or propagates X-Correlation-ID on all requests traversing the gateway.
The correlation ID is:
- Read from the incoming request header if present
- Generated as a UUID v4 if not present
- Stored in request.state.correlation_id for use by proxy and other middleware
- Added to the response headers
"""

import uuid

from starlette.middleware.base import BaseHTTPMiddleware, RequestResponseEndpoint
from starlette.requests import Request
from starlette.responses import Response

CORRELATION_HEADER = "X-Correlation-ID"


def _is_valid_uuid(value: str) -> bool:
    """Check that a string is a valid UUID (any version)."""
    try:
        uuid.UUID(value)
        return True
    except (ValueError, AttributeError):
        return False


def get_or_create_correlation_id(request: Request) -> str:
    """Extract correlation ID from request headers or generate a new one.

    Only accepts valid UUIDs from the client. Any other value is ignored
    and a fresh UUID v4 is generated instead.
    """
    client_id = request.headers.get(CORRELATION_HEADER, "")
    if _is_valid_uuid(client_id):
        return client_id
    return str(uuid.uuid4())


class CorrelationIdMiddleware(BaseHTTPMiddleware):
    """Middleware that ensures every request has a correlation ID.

    - Reads X-Correlation-ID from incoming request or generates a UUID v4
    - Stores it in request.state.correlation_id
    - Adds it to the response headers
    """

    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        correlation_id = get_or_create_correlation_id(request)
        request.state.correlation_id = correlation_id

        response = await call_next(request)
        response.headers[CORRELATION_HEADER] = correlation_id

        return response
