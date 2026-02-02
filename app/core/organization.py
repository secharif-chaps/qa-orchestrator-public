# app/core/organization.py
"""
Organization context and utilities for Keycloak Organizations integration.

This module provides utilities to extract organization information from JWT tokens
and manage organization-based access control.

Copied and adapted from mint-server/app/core/organization.py
"""

from typing import Any
from fastapi import Depends, HTTPException, status, Request
from fastapi_keycloak import OIDCUser
from pydantic import BaseModel

from app.core.keycloak import idp
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


def extract_organization_from_token(token_payload: dict[str, Any]) -> tuple[str, str] | None:
    """Extract organization ID and name from JWT token payload.
    
    Keycloak Organizations feature adds organization data in varying formats:
    ```json
    // Format 1:
    "organization": [{"OrgName": {"id": "uuid"}}, "OrgName"]
    
    // Format 2 (reversed):
    "organization": ["OrgName", {"OrgName": {"id": "uuid"}}]
    ```
    
    This function handles both formats and extracts the organization name and UUID.
    
    Args:
        token_payload: Decoded JWT token payload dictionary
        
    Returns:
        Tuple of (organization_id, organization_name) if found, None otherwise
    """
    organization_claim = token_payload.get("organization")
    
    if not organization_claim:
        logger.warning("No organization claim found in token")
        return None
    
    # organization_claim is an array with 2 elements
    if not isinstance(organization_claim, list) or len(organization_claim) != 2:
        logger.warning(
            "Invalid organization claim format",
            extra={"organization_claim": organization_claim}
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
            extra={"organization_claim": organization_claim}
        )
        return None
    
    # Extract the organization name (key) and nested id
    if len(org_dict) != 1:
        logger.warning(
            "Organization dict has unexpected number of keys",
            extra={"org_dict": org_dict}
        )
        return None
    
    # Get the first (and only) key-value pair
    org_name_from_dict = list(org_dict.keys())[0]
    org_data = org_dict[org_name_from_dict]
    
    if not isinstance(org_data, dict) or "id" not in org_data:
        logger.warning(
            "Organization data missing id field",
            extra={"org_data": org_data}
        )
        return None
    
    org_id = org_data["id"]
    
    # Validate that both name representations match
    if org_name_from_dict != org_name:
        logger.warning(
            "Organization name mismatch",
            extra={
                "name_from_dict": org_name_from_dict,
                "name_from_array": org_name
            }
        )
    
    logger.debug(
        "Extracted organization from token",
        extra={
            "organization_id": org_id,
            "organization_name": org_name
        }
    )
    
    return (org_id, org_name)


def extract_enabled_modules(token_payload: dict[str, Any]) -> list[str]:
    """Extract enabled modules from token if present.
    
    Args:
        token_payload: Decoded JWT token payload
        
    Returns:
        List of enabled module names, empty list if not present
    """
    modules = token_payload.get("enabled_modules", [])
    return modules if isinstance(modules, list) else []


def _get_current_user_dependency():
    """Wrapper to lazily get the Keycloak user dependency."""
    return idp.get_current_user()


def get_user_organization(
    request: Request,
    user: OIDCUser = Depends(_get_current_user_dependency)
) -> OrganizationContext:
    """FastAPI dependency to extract organization context from authenticated user.

    This dependency should be used in API endpoints that require organization-scoped
    access. It extracts the organization UUID and name from the already-validated
    OIDCUser object (validated by fastapi-keycloak via the dependency chain).

    SECURITY NOTE: We use user.organization directly from the validated OIDCUser
    instead of re-decoding the JWT token. This is safer because:
    1. The token has already been cryptographically verified by fastapi-keycloak
    2. The claims are extracted during that validation and stored in user object
    3. No risk of accidentally using this with an unvalidated token

    Args:
        request: FastAPI Request object (unused but kept for API compatibility)
        user: Current authenticated user from Keycloak (injected by FastAPI)

    Returns:
        OrganizationContext with organization ID, name, user ID, and username

    Raises:
        HTTPException: 403 Forbidden if user has no organization assignment
    """
    # Use the organization claim from the already-validated OIDCUser object
    # The token was validated by fastapi-keycloak in the dependency chain
    # (idp.get_current_user() verifies the JWT signature before returning the user)
    if not user.organization:
        logger.error(
            "User has no organization assignment",
            extra={
                "user": user.preferred_username,
                "user_id": user.sub
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="User must be assigned to an organization"
        )

    # Extract organization from the user's organization claim
    org_info = extract_organization_from_token({"organization": user.organization})

    if not org_info:
        logger.error(
            "Failed to parse organization claim",
            extra={
                "user": user.preferred_username,
                "user_id": user.sub,
                "organization_claim": user.organization
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="User must be assigned to an organization"
        )

    org_id, org_name = org_info

    return OrganizationContext(
        organization_id=org_id,
        organization_name=org_name,
        user_id=user.sub,
        username=user.preferred_username,
        enabled_modules=[]  # Can be extended if needed from user claims
    )