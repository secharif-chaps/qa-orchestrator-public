# app/core/organization.py
"""
Organization context and utilities for Keycloak Organizations integration.

This module provides utilities to extract organization information from JWT tokens
and manage organization-based access control.

Copied and adapted from mint-server/app/core/organization.py
"""

from typing import Any
from fastapi import Depends, HTTPException, status, Request
from pydantic import BaseModel

from app.core.keycloak import idp, OIDCUser
from app.core.logging_config import get_logger

logger = get_logger(__name__)


class OrganizationContext(BaseModel):
    """Organization context extracted from JWT token.

    Attributes:
        organization_id: Keycloak organization UUID
        organization_name: Keycloak organization name
        user_id: User UUID (from JWT sub claim)
        username: User's preferred username
        enabled_modules: List of enabled modules for this organization (optional)
    """

    organization_id: str
    organization_name: str
    user_id: str
    username: str
    enabled_modules: list[str] = []  # Added for global service


def _parse_organization_claim(organization_claim: Any) -> tuple[str, str] | None:
    """Parse organization claim from Keycloak into (org_id, org_name).

    Internal helper function that handles Keycloak's organization claim format.

    Keycloak Organizations feature adds organization data in varying formats:
    ```json
    // Format 1:
    "organization": [{"OrgName": {"id": "uuid"}}, "OrgName"]

    // Format 2 (reversed):
    "organization": ["OrgName", {"OrgName": {"id": "uuid"}}]
    ```

    Args:
        organization_claim: The organization claim value from the token

    Returns:
        Tuple of (organization_id, organization_name) if valid, None otherwise
    """
    if not organization_claim:
        logger.warning("No organization claim found in token")
        return None

    # organization_claim is an array with 2 elements
    if not isinstance(organization_claim, list) or len(organization_claim) != 2:
        logger.warning(
            "Invalid organization claim format",
            extra={"organization_claim": organization_claim},
        )
        return None

    # Find which element is the dict and which is the string
    org_dict = None
    org_name = None

    for element in organization_claim:
        if isinstance(element, dict):
            org_dict = element
        elif isinstance(element, str):
            org_name = element

    if not org_dict or not org_name:
        logger.warning(
            "Organization claim missing dict or string element",
            extra={"organization_claim": organization_claim},
        )
        return None

    # Extract the organization name (key) and nested id
    if len(org_dict) != 1:
        logger.warning(
            "Organization dict has unexpected number of keys",
            extra={"org_dict": org_dict},
        )
        return None

    # Get the first (and only) key-value pair
    org_name_from_dict = list(org_dict.keys())[0]
    org_data = org_dict[org_name_from_dict]

    if not isinstance(org_data, dict) or "id" not in org_data:
        logger.warning(
            "Organization data missing id field", extra={"org_data": org_data}
        )
        return None

    org_id = org_data["id"]

    # Validate that both name representations match
    if org_name_from_dict != org_name:
        logger.warning(
            "Organization name mismatch",
            extra={"name_from_dict": org_name_from_dict, "name_from_array": org_name},
        )

    logger.debug(
        "Extracted organization from token",
        extra={"organization_id": org_id, "organization_name": org_name},
    )

    return (org_id, org_name)


def extract_organization_from_validated_user(user: OIDCUser) -> tuple[str, str] | None:
    """Extract organization ID and name from a validated OIDCUser.

    This function accepts an OIDCUser object directly, which provides a security
    guarantee: OIDCUser can only be created by fastapi-keycloak after successful
    JWT validation. This ensures the organization claim comes from a validated token.

    Args:
        user: Validated OIDCUser from fastapi-keycloak (token already verified)

    Returns:
        Tuple of (organization_id, organization_name) if found, None otherwise
    """
    # fastapi-keycloak puts custom claims in extra_fields when using
    # idp.get_current_user(extra_fields=["organization"]).
    # For direct OIDCUser construction (e.g., tests), the field may be
    # stored as a direct attribute due to pydantic's extra="allow" config.
    organization_claim = (
        user.extra_fields.get("organization") if user.extra_fields else None
    )
    if organization_claim is None:
        organization_claim = getattr(user, "organization", None)
    return _parse_organization_claim(organization_claim)


# Create the Keycloak user dependency with extra fields at module level.
# The actual Keycloak connection happens lazily when a request is made (via _LazyIdp).
# For tests, this dependency should be overridden in conftest.py using FastAPI's
# app.dependency_overrides mechanism.
_keycloak_user_dependency = idp.get_current_user(
    extra_fields=["organization", "enabled_modules"]
)


def get_user_organization(
    request: Request, user: OIDCUser = Depends(_keycloak_user_dependency)
) -> OrganizationContext:
    """FastAPI dependency to extract organization context from authenticated user.

    This dependency should be used in API endpoints that require organization-scoped
    access. It extracts the organization UUID and name from the already-validated
    OIDCUser object (validated by fastapi-keycloak via the dependency chain).

    SECURITY NOTE: We use user.extra_fields from the validated OIDCUser
    instead of re-decoding the JWT token. This is safer because:
    1. The token has already been cryptographically verified by fastapi-keycloak
    2. The claims are extracted during that validation and stored in extra_fields
    3. No risk of accidentally using this with an unvalidated token

    Args:
        request: FastAPI Request object (unused but kept for API compatibility)
        user: Current authenticated user from Keycloak (injected by FastAPI)

    Returns:
        OrganizationContext with organization ID, name, user ID, and username

    Raises:
        HTTPException: 403 Forbidden if user has no organization assignment
    """
    # Get organization from extra_fields (fastapi-keycloak puts custom claims there)
    organization_claim = user.extra_fields.get("organization")

    if not organization_claim:
        logger.error(
            "User has no organization assignment",
            extra={"user": user.preferred_username, "user_id": user.sub},
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="User must be assigned to an organization",
        )

    # Extract organization from the validated user object
    org_info = extract_organization_from_validated_user(user)

    if not org_info:
        logger.error(
            "Failed to parse organization claim",
            extra={
                "user": user.preferred_username,
                "user_id": user.sub,
                "organization_claim": organization_claim,
            },
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="User must be assigned to an organization",
        )

    org_id, org_name = org_info

    # Get enabled_modules from extra_fields
    enabled_modules = user.extra_fields.get("enabled_modules") or []

    return OrganizationContext(
        organization_id=org_id,
        organization_name=org_name,
        user_id=user.sub,
        username=user.preferred_username,
        enabled_modules=enabled_modules,
    )
