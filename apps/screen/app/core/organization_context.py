"""Organization context and feature-flag guards.

Organization info is carried on the Internal JWT as flat ``org_id`` /
``org_name`` fields (see ``InternalTokenPayload``). This module exposes
``get_user_organization`` which builds an ``OrganizationContext`` directly
from the authenticated user — no Keycloak wire-format parsing needed.
"""

from typing import TYPE_CHECKING

from fastapi import Depends, HTTPException, status
from pydantic import BaseModel

from app.core.auth import AuthenticatedUser, get_current_user
from app.core.logging_config import get_logger

if TYPE_CHECKING:
    from app.models.organization import FeatureFlag

logger = get_logger(__name__)


class OrganizationContext(BaseModel):
    """Organization context for the current request.

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
        organization_id=user.org_id,
        organization_name=user.org_name,
        user_id=user.sub,
        username=user.preferred_username,
    )


def require_feature(flag: "FeatureFlag"):
    """Dependency factory to require a feature flag to be enabled for the org.

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
        org_context: OrganizationContext = Depends(get_user_organization),
        db: Session = Depends(get_db),
    ) -> None:
        from app.services.feature_flags import has_feature

        if not has_feature(db, org_context.organization_id, flag):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Feature '{flag.value}' is not enabled for this organization",
            )

    return check_feature
