"""
Organization endpoints for Keycloak Organizations integration.

This module provides minimal organization context endpoints. User and organization
management is handled directly in Keycloak, not in the application database.
"""
from typing import List
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.database import get_db
from app.core.organization import get_user_organization, OrganizationContext
from app.models.company import Company
from app.models.folder import Folder
from app.schemas.organization import OrganizationResponse, ActivityResponse
from app.core.logging_config import get_logger

logger = get_logger(__name__)

router = APIRouter(tags=["organization"])


@router.get("/current", response_model=OrganizationResponse)
async def get_current_organization(
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
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


@router.get("/activities", response_model=List[ActivityResponse])
async def get_organization_activities(
    db: Session = Depends(get_db),
    org_context: OrganizationContext = Depends(get_user_organization)
) -> List[ActivityResponse]:
    """
    Get recent creation activities in the organization (companies and folders created by other users).

    Shows last 10 companies and folders created by other users in the same organization.
    """
    logger.info(
        "Get organization activities",
        extra={
            "user": org_context.username,
            "organization_id": org_context.organization_id
        }
    )

    # Get current username
    current_username = org_context.username

    # SECURITY: Query last 10 companies created in user's organization (exclude current user)
    companies = (
        db.query(Company)
        .filter(
            Company.organization_id == org_context.organization_id,
            Company.owner_username != current_username,
            ~Company.is_deleted
        )
        .order_by(Company.created_at.desc())
        .limit(10)
        .all()
    )

    # SECURITY: Query last 10 folders created in user's organization (exclude current user)
    folders = (
        db.query(Folder)
        .filter(
            Folder.organization_id == org_context.organization_id,
            Folder.owner != current_username,
            ~Folder.is_deleted
        )
        .order_by(Folder.created_at.desc())
        .limit(10)
        .all()
    )

    # Combine and format activities
    activities = []

    for company in companies:
        activities.append(ActivityResponse(
            type="company",
            name=company.name,
            owner=company.owner_username or "Unknown",
            created_at=company.created_at
        ))

    for folder in folders:
        activities.append(ActivityResponse(
            type="folder",
            name=folder.name,
            owner=folder.owner or "Unknown",
            created_at=folder.created_at
        ))

    # Sort by creation time (most recent first)
    activities.sort(key=lambda x: x.created_at, reverse=True)

    # Return top 10 most recent
    return activities[:10]
