"""Pydantic response schemas for security endpoints."""

from typing import Any

from pydantic import BaseModel, Field


class SecurityFeatures(BaseModel):
    """Declared security features.

    These are static, declarative values describing architectural protections
    in place — not dynamically detected runtime state.
    """

    jwt_verification: str = "enabled"
    authorization: str = "enabled"
    input_validation: str = "enabled"
    rate_limiting: str = "enabled"
    sql_injection_protection: str = "enabled"
    xss_protection: str = "enabled"
    csrf_protection: str = "headers_only"


class MiddlewareStatus(BaseModel):
    """Declared middleware status.

    These are static, declarative values describing middleware configuration
    — not dynamically detected runtime state.
    """

    security_middleware: str = "active"
    json_validation: str = "active"
    cors: str = "restricted"


class SecurityStatsResponse(BaseModel):
    """Response for security stats endpoint."""

    database: dict[str, Any] = Field(..., description="Database security statistics")
    security_features: SecurityFeatures
    middleware: MiddlewareStatus


class HealthCheckResponse(BaseModel):
    """Response for security health check endpoint."""

    status: str = Field(..., description="Security status")
    timestamp: str = Field(..., description="Health check timestamp")
    security_level: str = Field(..., description="Current security level")
    features: list[str] = Field(default_factory=list, description="Active security features")
