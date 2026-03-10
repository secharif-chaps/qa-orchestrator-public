"""Development utilities endpoints.

These endpoints are only useful in local development environment.
They provide access to development-specific information like tunnel URLs.

IMPORTANT: These endpoints only work when TUNNEL_URL_FILE is configured.
In production (where TUNNEL_URL_FILE is not set), these endpoints return 404.
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

from app.core.config import get_callback_base_url, settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

router = APIRouter(prefix="/dev", tags=["dev"])


def _is_dev_mode() -> bool:
    """Check if running in development mode with tunnel support.

    Returns True if TUNNEL_URL_FILE is configured (local dev environment).
    Returns False in production where this setting is not set.
    """
    return bool(settings.TUNNEL_URL_FILE)


class TunnelURLResponse(BaseModel):
    """Response model for tunnel URL endpoint."""

    url: str
    is_tunnel: bool
    source: str


@router.get("/tunnel-url", response_model=TunnelURLResponse)
def get_tunnel_url() -> TunnelURLResponse:
    """Get the current callback base URL (tunnel URL in dev, backend URL in prod).

    This endpoint is used by the frontend to get the correct callback URL
    for Dify workflows when running in local development.

    Returns:
        TunnelURLResponse with the URL and metadata about its source

    Raises:
        HTTPException 404: If not running in development mode (TUNNEL_URL_FILE not set)
    """
    # Only available in dev mode (when TUNNEL_URL_FILE is configured)
    if not _is_dev_mode():
        raise HTTPException(
            status_code=404,
            detail="Dev endpoints not available in production"
        )

    callback_url = get_callback_base_url()
    is_tunnel = callback_url != settings.BACKEND_BASE_URL

    source = "tunnel" if is_tunnel else "config"
    if is_tunnel:
        logger.info(f"Returning tunnel URL: {callback_url}")
    else:
        logger.debug(f"Returning config URL: {callback_url}")

    return TunnelURLResponse(
        url=callback_url,
        is_tunnel=is_tunnel,
        source=source,
    )
