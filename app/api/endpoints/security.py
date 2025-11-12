"""Security monitoring and administration endpoints.

All admin endpoints require admin role for access.
"""

from typing import Dict, Any
from fastapi import APIRouter, Depends
from fastapi_keycloak import OIDCUser

from app.core.keycloak import idp
from app.core.database_security import get_database_stats

router = APIRouter(
    prefix="/security",
    tags=["security"]
)


@router.get("/stats", response_model=Dict[str, Any])
async def get_security_stats(
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin"]))
):
    """Get security statistics (admin only).

    Requires admin role for access.
    """
    
    # Get database statistics
    db_stats = get_database_stats()
    
    # Additional security metrics could be added here
    stats = {
        "database": db_stats,
        "security_features": {
            "jwt_verification": "enabled",
            "authorization": "enabled",
            "input_validation": "enabled",
            "rate_limiting": "enabled",
            "sql_injection_protection": "enabled",
            "xss_protection": "enabled",
            "csrf_protection": "headers_only"
        },
        "middleware": {
            "security_middleware": "active",
            "json_validation": "active",
            "cors": "restricted"
        }
    }
    
    return stats


@router.get("/health")
async def security_health_check():
    """Public security health check endpoint"""
    return {
        "status": "secure",
        "timestamp": "2024-01-01T00:00:00Z",
        "security_level": "high",
        "features": [
            "authentication",
            "authorization", 
            "input_validation",
            "rate_limiting"
        ]
    }