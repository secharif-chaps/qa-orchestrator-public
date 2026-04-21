"""Security monitoring and administration endpoints.

All admin endpoints require admin role for access.
"""

from datetime import UTC, datetime

from fastapi import APIRouter, Depends

from app.core.auth import AuthenticatedUser, get_current_user
from app.core.database_security import get_database_stats
from app.schemas.security import (
    HealthCheckResponse,
    MiddlewareStatus,
    SecurityFeatures,
    SecurityStatsResponse,
)

router = APIRouter(prefix="/security", tags=["security"])


@router.get(
    "/stats",
    response_model=SecurityStatsResponse,
    openapi_extra={"x-permissions": ["admin"]},
)
async def get_security_stats(user: AuthenticatedUser = Depends(get_current_user(required_roles=["admin"]))):
    """Get security statistics (admin only).

    Requires admin role for access.
    """

    # Get database statistics
    db_stats = get_database_stats()

    return SecurityStatsResponse(
        database=db_stats,
        security_features=SecurityFeatures(),
        middleware=MiddlewareStatus(),
    )


@router.get(
    "/health",
    response_model=HealthCheckResponse,
    openapi_extra={"x-public": True},
)
async def security_health_check():
    """Public security health check endpoint"""
    return HealthCheckResponse(
        status="secure",
        timestamp=datetime.now(UTC).isoformat(),
        security_level="high",
        features=["authentication", "authorization", "input_validation", "rate_limiting"],
    )
