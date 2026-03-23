"""
Organization endpoints for Keycloak Organizations integration.

This module provides minimal organization context endpoints. User and organization
management is handled directly in Keycloak, not in the application database.
"""

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.database import get_db
from app.models.company import Company
from app.models.folder import Folder, FolderItem, FolderShare
from app.schemas.organization import ActivityResponse
from app.services.folder import FolderService

logger = get_logger(__name__)

router = APIRouter(tags=["organization"])


@router.get("/activities", response_model=list[ActivityResponse])
async def get_organization_activities(
    db: Session = Depends(get_db), org_context: OrganizationContext = Depends(get_user_organization)
) -> list[ActivityResponse]:
    """
    Get recent creation activities in the organization (companies and folders created by other users).

    Shows last 10 companies and folders created by other users in the same organization,
    filtered by folder-based access control:
    - Only shows companies from folders the user has access to
    - Only shows folders that the user has been shared with
    """
    logger.info(
        "Get organization activities",
        extra={"user": org_context.username, "organization_id": org_context.organization_id},
    )

    # Get current user info
    current_username = org_context.username
    current_user_id = org_context.user_id

    # Get accessible company IDs for this user (from folders they have access to)
    accessible_company_ids = FolderService.get_accessible_company_ids(
        db, current_user_id, org_context.organization_id, username=current_username
    )

    activities = []

    # SECURITY: Query companies that:
    # 1. Belong to user's organization
    # 2. Were created by other users (not current user)
    # 3. Are not deleted
    # 4. Are in folders the user has access to
    if accessible_company_ids:
        companies = (
            db.query(Company)
            .filter(
                Company.organization_id == org_context.organization_id,
                Company.owner_username != current_username,
                ~Company.is_deleted,
                Company.id.in_(accessible_company_ids),
            )
            .order_by(Company.created_at.desc())
            .limit(10)
            .all()
        )

        # Get folder IDs for these companies
        company_id_strings = [str(c.id) for c in companies]
        folder_items = (
            db.query(FolderItem.item_id, FolderItem.folder_id)
            .filter(FolderItem.item_id.in_(company_id_strings), FolderItem.item_type == "company")
            .order_by(FolderItem.added_at.desc())  # Most recent folder first
            .all()
        )
        # Map company_id -> folder_id
        company_folder_map = {}
        for fi in folder_items:
            if fi.item_id not in company_folder_map:
                company_folder_map[fi.item_id] = str(fi.folder_id)

        for company in companies:
            activities.append(
                ActivityResponse(
                    type="company",
                    name=company.name,
                    owner=company.owner_username or "Unknown",
                    created_at=company.created_at,
                    id=str(company.id),
                    folder_id=company_folder_map.get(str(company.id)),
                )
            )

    # SECURITY: Query folders that:
    # 1. Belong to user's organization
    # 2. Were created by other users (not current user)
    # 3. Are not deleted
    # 4. Have been shared with the current user (user is in folder_shares)
    folders = (
        db.query(Folder)
        .join(FolderShare, FolderShare.folder_id == Folder.id)
        .filter(
            Folder.organization_id == org_context.organization_id,
            Folder.owner_id != current_user_id,  # Not owned by current user
            ~Folder.is_deleted,
            FolderShare.user_id == current_user_id,  # Shared with current user
        )
        .order_by(Folder.created_at.desc())
        .limit(10)
        .all()
    )

    for folder in folders:
        activities.append(
            ActivityResponse(
                type="folder",
                name=folder.name,
                owner=folder.owner or "Unknown",
                created_at=folder.created_at,
                id=str(folder.id),
            )
        )

    # Sort by creation time (most recent first)
    activities.sort(key=lambda x: x.created_at, reverse=True)

    # Return top 10 most recent
    return activities[:10]
