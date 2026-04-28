"""Organization context dependency.

Organization info is carried on the Internal JWT as flat ``org_id`` /
``org_name`` fields (see ``InternalTokenPayload``). This module exposes
``get_user_organization`` which builds an ``OrganizationContext`` directly
from the authenticated user — no Keycloak wire-format parsing needed.
"""

from fastapi import Depends, HTTPException, status
from pydantic import BaseModel

from app.core.auth import AuthenticatedUser, get_current_user
from app.core.logging_config import get_logger

logger = get_logger(__name__)


class OrganizationContext(BaseModel):
    """Organization context for the current request.

    Attributes:
        org_id: Keycloak organization UUID
        org_name: Keycloak organization name
        user_id: User UUID (from JWT sub claim)
        username: User's preferred username
    """

    org_id: str
    org_name: str
    user_id: str
    username: str


def get_user_organization(
    user: AuthenticatedUser = Depends(get_current_user()),
) -> OrganizationContext:
    """FastAPI dependency returning the org context of the current user.

    Raises:
        HTTPException: 403 Forbidden if the user has no organization assignment.
    """
    if not user.org_id or not user.org_name:
        logger.error(
            "User has no organization assignment",
            extra={"user": user.preferred_username, "user_id": user.sub},
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="User must be assigned to an organization",
        )

    return OrganizationContext(
        org_id=user.org_id,
        org_name=user.org_name,
        user_id=user.sub,
        username=user.preferred_username,
    )
