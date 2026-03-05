"""Feature flag configuration endpoints for organization feature flags.

This module provides endpoints for managing organization feature flags.
Feature flags are add-on capabilities that enhance core modules but are
OFF by default.

Endpoints:
- GET /organizations/{id}/feature-flags - Get all feature flags
- PATCH /organizations/{id}/feature-flags/{flag} - Toggle feature flag
"""

from typing import Any

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi_keycloak import OIDCUser
from pydantic import BaseModel, HttpUrl, field_validator
from sqlalchemy.orm import Session

from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.database import get_db
from app.models.organization import FeatureFlag
from app.services.feature_flags import (
    disable_feature,
    enable_feature,
    get_organization_features,
)

logger = get_logger(__name__)

router = APIRouter(prefix="/organizations", tags=["feature-flags"])


# Response schemas
class FeatureFlagResponse(BaseModel):
    """Feature flag configuration response."""

    flag: str
    enabled: bool
    enabled_at: str | None
    config: dict | None
    created_at: str | None
    updated_at: str | None


class FeatureFlagsResponse(BaseModel):
    """List of feature flags response."""

    feature_flags: list[FeatureFlagResponse]


class FeatureFlagToggleRequest(BaseModel):
    """Request to toggle a feature flag.

    Attributes:
        enabled: Whether to enable or disable the feature flag
        config: Optional configuration for the feature flag (e.g., {"url": "https://..."})
                When config contains a "url" field, it must be a valid HTTPS URL.
    """

    enabled: bool
    config: dict[str, Any] | None = None

    @field_validator("config")
    @classmethod
    def validate_config_url(cls, v: dict[str, Any] | None) -> dict[str, Any] | None:
        """Validate that if config contains a 'url' field, it must be a valid HTTPS URL.

        Args:
            v: The config dict to validate

        Returns:
            The validated config dict

        Raises:
            ValueError: If the URL is not a valid HTTPS URL
        """
        if v is None:
            return v

        if "url" in v:
            url = v["url"]
            if not url:
                raise ValueError("URL cannot be empty")

            # Validate URL format using Pydantic's HttpUrl
            try:
                parsed_url = HttpUrl(url)
            except Exception:
                raise ValueError(
                    f"Invalid URL format: {url}. Must be a valid URL."
                )

            # Ensure HTTPS scheme
            if parsed_url.scheme != "https":
                raise ValueError(
                    f"URL must use HTTPS scheme. Got: {parsed_url.scheme}://"
                )

        return v


class FeatureFlagToggleResponse(BaseModel):
    """Response after toggling a feature flag."""

    flag: str
    enabled: bool
    enabled_at: str | None


@router.get("/{organization_id}/feature-flags", response_model=FeatureFlagsResponse)
async def get_organization_feature_flags(
    organization_id: str,
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get all feature flags for an organization.

    Returns feature flag enabled/disabled state for all flags.
    Feature flags are OFF by default - only enabled flags are stored.

    Organization members can view their own organization.
    Users with admin.organizations role can view any organization.

    Args:
        organization_id: Keycloak organization UUID

    Returns:
        FeatureFlagsResponse with list of feature flags
    """
    # Check if user has admin.organizations role or belongs to the organization
    is_org_admin = (
        hasattr(user, "roles") and user.roles and "admin.organizations" in user.roles
    )
    is_org_member = org_context.organization_id == organization_id

    if not (is_org_admin or is_org_member):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied to this organization",
        )

    # Get stored feature flags
    stored_flags = get_organization_features(db, organization_id)
    stored_flag_map = {f.flag: f for f in stored_flags}

    # Build response with all possible flags
    feature_flags = []
    for flag in FeatureFlag:
        stored = stored_flag_map.get(flag)
        if stored:
            feature_flags.append(
                FeatureFlagResponse(
                    flag=flag.value,
                    enabled=stored.enabled,
                    enabled_at=stored.enabled_at.isoformat() if stored.enabled_at else None,
                    config=stored.config,
                    created_at=stored.created_at.isoformat() if stored.created_at else None,
                    updated_at=stored.updated_at.isoformat() if stored.updated_at else None,
                )
            )
        else:
            # Flag not stored = disabled by default
            feature_flags.append(
                FeatureFlagResponse(
                    flag=flag.value,
                    enabled=False,
                    enabled_at=None,
                    config=None,
                    created_at=None,
                    updated_at=None,
                )
            )

    return FeatureFlagsResponse(feature_flags=feature_flags)


@router.patch(
    "/{organization_id}/feature-flags/{flag}",
    response_model=FeatureFlagToggleResponse,
)
async def toggle_feature_flag(
    organization_id: str,
    flag: FeatureFlag,
    request: FeatureFlagToggleRequest,
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Toggle a feature flag for an organization.

    Enables or disables the specified feature flag. Optionally accepts a config
    object to store feature-specific configuration (e.g., external URL for discover).

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        flag: Feature flag to toggle
        request: Toggle request with enabled state and optional config

    Returns:
        FeatureFlagToggleResponse with updated flag state
    """
    logger.info(
        "Toggling feature flag",
        extra={
            "organization_id": organization_id,
            "flag": flag.value,
            "enabled": request.enabled,
            "has_config": request.config is not None,
            "user": user.preferred_username,
        },
    )

    if request.enabled:
        result = enable_feature(
            db, organization_id, flag, user.sub, config=request.config
        )
    else:
        result = disable_feature(db, organization_id, flag, user.sub)

    return FeatureFlagToggleResponse(
        flag=flag.value,
        enabled=result.enabled if result else False,
        enabled_at=result.enabled_at.isoformat() if result and result.enabled_at else None,
    )
