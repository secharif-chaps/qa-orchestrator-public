"""
Organization endpoints for Keycloak Organizations integration.

This module provides organization context endpoints:
- /organizations/current: Returns current organization from JWT
- /organizations/current/activities: Returns recent activities (companies and folders) from other users
"""

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.database import get_global_db
from app.models.folder import Folder, FolderItem, FolderShare
from app.schemas.organization import ActivityResponse, OrganizationResponse
from app.services.backend_client import get_companies_by_ids
from app.services.folder import FolderService

logger = get_logger(__name__)

router = APIRouter(prefix="/organizations", tags=["organization"])


@router.get("/current", response_model=OrganizationResponse)
async def get_current_organization(
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """
    Get current user's organization information.

    Returns basic organization context extracted from JWT token.
    Organization management is handled in Keycloak.
    """
    logger.info(
        "Get current organization",
        extra={
            "user": org_context.username,
            "organization_id": org_context.organization_id
        }
    )

    return OrganizationResponse(
        id=org_context.organization_id,
        name=org_context.organization_name
    )


@router.get("/current/activities", response_model=list[ActivityResponse])
async def get_organization_activities(
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db),
) -> list[ActivityResponse]:
    """
    Get recent creation activities in the organization.

    Shows last 10 companies and folders created by other users in the same organization.

    The response includes:
    - Companies created by other users (filtered by folder access control)
    - Folders shared with the current user (created by other users)

    Security: Access control is enforced by folder-based permissions.
    """
    logger.info(
        "Get organization activities",
        extra={
            "user": org_context.username,
            "organization_id": org_context.organization_id
        }
    )

    try:
        current_user_id = org_context.user_id
        current_username = org_context.username

        # Get accessible company IDs for this user
        accessible_company_ids = await FolderService.get_accessible_company_ids(
            db=db,
            user_id=current_user_id,
            organization_id=org_context.organization_id,
            username=current_username,
        )

        activities: list[ActivityResponse] = []

        # Get companies from accessible folders created by other users
        if accessible_company_ids:
            company_id_strings = [str(cid) for cid in accessible_company_ids]

            # Get folder items for accessible companies, with folder info
            stmt = (
                select(FolderItem, Folder)
                .join(Folder, FolderItem.folder_id == Folder.id)
                .where(
                    FolderItem.item_id.in_(company_id_strings),
                    FolderItem.item_type == "company",
                    Folder.organization_id == org_context.organization_id,
                    Folder.is_deleted.is_(False),
                )
                .order_by(FolderItem.added_at.desc())
                .limit(10)
            )
            result = await db.execute(stmt)
            rows = result.all()

            # Collect unique company IDs and their folder mapping
            seen_company_ids: set[str] = set()
            company_folder_map: dict[str, tuple[FolderItem, Folder]] = {}
            for folder_item, folder in rows:
                if folder_item.item_id not in seen_company_ids:
                    seen_company_ids.add(folder_item.item_id)
                    company_folder_map[folder_item.item_id] = (folder_item, folder)

            # Enrich with company names from screen-service
            if company_folder_map:
                int_ids = [int(cid) for cid in company_folder_map]
                company_map = await get_companies_by_ids(
                    company_ids=int_ids,
                    user_id=current_user_id,
                    username=current_username,
                    org_id=org_context.organization_id,
                    org_name=org_context.organization_name,
                )

                for cid_str, (folder_item, folder) in company_folder_map.items():
                    company_info = company_map.get(int(cid_str))
                    if not company_info or company_info.is_deleted:
                        continue
                    # Only show companies created by other users
                    if company_info.owner_username == current_username:
                        continue
                    activities.append(ActivityResponse(
                        type="company",
                        name=company_info.name,
                        owner=company_info.owner_username or "Unknown",
                        created_at=folder_item.added_at,
                        id=cid_str,
                        folder_id=str(folder.id),
                    ))

        # Get folders shared with the current user (created by other users)
        stmt = (
            select(Folder)
            .join(FolderShare, FolderShare.folder_id == Folder.id)
            .where(
                Folder.organization_id == org_context.organization_id,
                Folder.owner_id != current_user_id,
                Folder.is_deleted.is_(False),
                FolderShare.user_id == current_user_id,
            )
            .order_by(Folder.created_at.desc())
            .limit(10)
        )
        result = await db.execute(stmt)
        folders = result.scalars().all()

        for folder in folders:
            activities.append(ActivityResponse(
                type="folder",
                name=folder.name,
                owner=folder.owner or "Unknown",
                created_at=folder.created_at,
                id=str(folder.id),
            ))

        # Sort by creation time (most recent first) and return top 10
        activities.sort(key=lambda x: x.created_at, reverse=True)

        logger.debug(
            "Retrieved organization activities",
            extra={
                "count": len(activities[:10]),
                "user": org_context.username
            }
        )

        return activities[:10]

    except Exception as e:
        logger.error(
            "Unexpected error fetching activities",
            exc_info=True,  # This will log the full traceback
            extra={"error": str(e), "error_type": type(e).__name__}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Unable to retrieve recent activities. Please try again later."
        )
