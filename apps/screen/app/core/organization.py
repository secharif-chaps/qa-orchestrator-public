"""
Organization context and utilities for Keycloak Organizations integration.

This module provides utilities to extract organization information from JWT tokens
and manage organization-based access control.
"""

from typing import TYPE_CHECKING, Any

import jwt
from fastapi import Depends, HTTPException, Request, status
from fastapi_keycloak import OIDCUser
from pydantic import BaseModel

from app.core.internal_jwt import is_internal_request
from app.core.keycloak import idp
from app.core.logging_config import get_logger

if TYPE_CHECKING:
    from app.models.organization import FeatureFlag

logger = get_logger(__name__)


class OrganizationContext(BaseModel):
    """Organization context extracted from JWT token.

    Attributes:
        organization_id: Keycloak organization UUID
        organization_name: Keycloak organization name
        user_id: User UUID (from JWT sub claim)
        username: User's preferred username
    """

    organization_id: str
    organization_name: str
    user_id: str
    username: str


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

    Example:
        >>> payload = {"organization": [{"Acme": {"id": "123-456"}}, "Acme"]}
        >>> extract_organization_from_token(payload)
        ('123-456', 'Acme')
    """
    organization_claim = token_payload.get("organization")

    if not organization_claim:
        logger.warning("No organization claim found in token")
        return None

    # organization_claim is an array with 2 elements
    if not isinstance(organization_claim, list) or len(organization_claim) != 2:
        logger.warning("Invalid organization claim format", extra={"organization_claim": organization_claim})
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
            "Organization claim missing dict or string element", extra={"organization_claim": organization_claim}
        )
        return None

    # Extract the organization name (key) and nested id
    # org_dict should be like: {"Chapsvision": {"id": "19226951-..."}}
    if len(org_dict) != 1:
        logger.warning("Organization dict has unexpected number of keys", extra={"org_dict": org_dict})
        return None

    # Get the first (and only) key-value pair
    org_name_from_dict = list(org_dict.keys())[0]
    org_data = org_dict[org_name_from_dict]

    if not isinstance(org_data, dict) or "id" not in org_data:
        logger.warning("Organization data missing id field", extra={"org_data": org_data})
        return None

    org_id = org_data["id"]

    # Validate that both name representations match
    if org_name_from_dict != org_name:
        logger.warning(
            "Organization name mismatch", extra={"name_from_dict": org_name_from_dict, "name_from_array": org_name}
        )

    logger.debug("Extracted organization from token", extra={"organization_id": org_id, "organization_name": org_name})

    return (org_id, org_name)


def _extract_org_from_user(user: OIDCUser) -> tuple[str, str] | None:
    """Extract organization info from OIDCUser.organization claim.

    For internal JWT requests, the organization is already extracted from the
    internal JWT payload and stored in user.organization.

    The organization format follows Keycloak's structure:
    ["OrgName", {"OrgName": {"id": "uuid"}}]
    """
    if not user.organization:
        return None

    # Use the existing extract_organization_from_token function
    # by wrapping the organization in a dict
    return extract_organization_from_token({"organization": user.organization})


def get_user_organization(request: Request, user: OIDCUser = Depends(idp.get_current_user())) -> OrganizationContext:
    """FastAPI dependency to extract organization context from JWT token.

    This dependency should be used in API endpoints that require organization-scoped
    access. It extracts the organization UUID and name from the JWT token and
    validates that the user belongs to an organization.

    For internal requests from the gateway, organization info is extracted from
    the X-User-Organization header instead of decoding the JWT.

    Args:
        user: Current authenticated user from Keycloak (injected by FastAPI)

    Returns:
        OrganizationContext with organization ID, name, user ID, and username

    Raises:
        HTTPException: 403 Forbidden if user has no organization assignment

    Example:
        ```python
        @router.get("/companies")
        def list_companies(
            org_context: OrganizationContext = Depends(get_user_organization)
        ):
            # org_context.organization_id contains the UUID
            # org_context.user_id contains the user UUID
            return get_companies_by_org(org_context.organization_id)
        ```
    """
    # For internal requests (Authorization: Internal {token}), the OIDCUser
    # was created from the internal JWT payload and already has organization info
    if is_internal_request(request):
        org_info = _extract_org_from_user(user)
        if org_info:
            org_id, org_name = org_info
            return OrganizationContext(
                organization_id=org_id,
                organization_name=org_name,
                user_id=user.sub,
                username=user.preferred_username or "",
            )
        # If no org in user, fall through to try JWT (shouldn't happen for internal requests)
        logger.warning("Internal request user missing organization info")

    # For external requests (Bearer token), get organization claim by decoding the raw JWT token
    # FastAPI-Keycloak doesn't parse custom claims like 'organization' into OIDCUser
    # so we need to decode the JWT ourselves

    # Extract the raw JWT token from the Authorization header
    auth_header = request.headers.get("Authorization", "")
    if not auth_header.startswith("Bearer "):
        logger.error("Missing or invalid Authorization header")
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid authorization header")

    raw_token = auth_header.replace("Bearer ", "")

    # Decode the JWT without verification (it's already been verified by fastapi-keycloak)
    # We just need to extract the claims
    try:
        decoded_token = jwt.decode(raw_token, options={"verify_signature": False})
    except Exception as e:
        logger.error(f"Failed to decode JWT token: {e}")
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Failed to decode token")

    # Build token data dict with organization claim from the decoded JWT
    token_data = {"organization": decoded_token.get("organization")} if "organization" in decoded_token else {}

    # Extract organization from token
    org_info = extract_organization_from_token(token_data)

    if not org_info:
        logger.error(
            "User has no organization assignment", extra={"user": user.preferred_username, "user_id": user.sub}
        )
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="User must be assigned to an organization")

    org_id, org_name = org_info

    return OrganizationContext(
        organization_id=org_id, organization_name=org_name, user_id=user.sub, username=user.preferred_username
    )


def require_feature(flag: "FeatureFlag"):
    """Dependency factory to require a feature flag to be enabled.

    Returns a FastAPI dependency that checks if the specified feature is enabled
    for the user's organization. Raises 403 if the feature is not enabled.

    Args:
        flag: The FeatureFlag enum value to check

    Returns:
        A callable dependency that returns None if feature is enabled

    Raises:
        HTTPException: 403 Forbidden if feature not enabled for organization

    Example:
        @router.get("/translate")
        async def translate(
            _feature: None = Depends(require_feature(FeatureFlag.TRANSLATION)),
            org_context: OrganizationContext = Depends(get_user_organization),
        ):
            # Feature is guaranteed to be enabled if we reach here
            pass
    """
    from sqlalchemy.orm import Session

    from app.database import get_db

    async def check_feature(
        org_context: OrganizationContext = Depends(get_user_organization), db: Session = Depends(get_db)
    ) -> None:
        from app.services.feature_flags import has_feature

        if not has_feature(db, org_context.organization_id, flag):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Feature '{flag.value}' is not enabled for this organization",
            )

    return check_feature
