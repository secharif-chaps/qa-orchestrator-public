from typing import List
from uuid import UUID
from fastapi import APIRouter, Depends, HTTPException, status, Query
from fastapi_keycloak import OIDCUser
from sqlalchemy.orm import Session

from app.database import get_db
# NOTE: No User model - user references handled via username strings only
from app.core.keycloak import idp
from app.schemas.folder import (
    FolderCreate,
    FolderUpdate,
    FolderResponse,
    FolderWithItemsResponse,
    FolderItemAdd,
    FolderItemResponse
)
from app.services.folder import FolderService
from app.core.organization import get_user_organization, OrganizationContext


router = APIRouter()


@router.post("/", response_model=FolderResponse)
def create_folder(
    folder: FolderCreate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Create a new folder in the organization.

    Requires organization.write role for access.
    """
    import logging
    logger = logging.getLogger(__name__)

    try:
        logger.info(f"📁 POST /folders - START - User: {org_context.username}, Folder: {folder.name}")

        logger.debug(f"📁 Creating folder with owner_id={org_context.user_id}, owner_username={org_context.username}, organization_id={org_context.organization_id}")
        folder_obj = FolderService.create_folder(
            db=db,
            organization_id=org_context.organization_id,
            owner_id=org_context.user_id,
            owner_username=org_context.username,
            folder_data=folder
        )
        logger.info(f"✅ Folder created successfully - ID: {folder_obj.id}, Name: {folder_obj.name}")

        # Build response with is_favorite (new folders are never favorited)
        folder_dict = {
            "id": folder_obj.id,
            "organization_id": folder_obj.organization_id,
            "owner": folder_obj.owner or "Unknown",
            "name": folder_obj.name,
            "color": folder_obj.color,
            "icon": folder_obj.icon,
            "tags": folder_obj.tags,
            "is_favorite": False,  # New folders are never favorited
            "is_deleted": folder_obj.is_deleted,
            "created_at": folder_obj.created_at,
            "updated_at": folder_obj.updated_at,
            "items": []
        }

        logger.info("📁 About to return folder object")
        return folder_dict

    except Exception as e:
        logger.error(f"❌ Error in create_folder: {type(e).__name__}: {str(e)}")
        logger.error("❌ Full exception details:", exc_info=True)
        raise


@router.get("/", response_model=List[FolderResponse])
def list_folders(
    archived: bool = Query(False),
    favorites: bool = Query(False),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """List all folders in the organization with user-specific favorite status.

    Requires organization.read role for access.
    The is_favorite field is computed per-user from the user_folder_favorites table.
    """
    import logging
    logger = logging.getLogger(__name__)

    logger.info(f"📁 GET /folders - START - User: {org_context.username}, Organization: {org_context.organization_id}")
    logger.debug(f"📁 Query params - archived: {archived}, favorites: {favorites}")

    # Get user's favorite folder IDs for computing is_favorite per-user
    user_favorite_ids = FolderService.get_user_favorite_folder_ids(
        db, org_context.user_id, org_context.organization_id
    )

    folders = FolderService.list_folders(
        db=db,
        organization_id=org_context.organization_id,
        user_id=org_context.user_id,
        archived=archived,
        favorites_only=favorites
    )

    logger.info(f"📁 Found {len(folders)} folders for organization {org_context.organization_id}")

    # Build the response with items for each folder
    response_folders = []
    for folder in folders:
        logger.debug(f"📁 Processing folder {folder.id} - {folder.name}")
        folder_items = FolderService._get_folder_items_summary(db, folder.id)

        folder_dict = {
            "id": folder.id,
            "organization_id": folder.organization_id,
            "owner": folder.owner or "Unknown",
            "name": folder.name,
            "color": folder.color,
            "icon": folder.icon,
            "tags": folder.tags,
            "is_favorite": folder.id in user_favorite_ids,  # Computed per-user!
            "is_deleted": folder.is_deleted,
            "created_at": folder.created_at,
            "updated_at": folder.updated_at,
            "items": folder_items
        }
        response_folders.append(folder_dict)

    logger.info(f"✅ Returning {len(response_folders)} folders")
    return response_folders


@router.get("/{folder_id}", response_model=FolderWithItemsResponse)
def get_folder(
    folder_id: UUID,
    archived: bool = Query(False),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Get a folder with its items and user-specific favorite status.

    Requires organization.read role for access.
    The is_favorite field is computed per-user from the user_folder_favorites table.
    """
    import logging
    logger = logging.getLogger(__name__)

    try:
        logger.info(f"📁 GET /folders/{folder_id} - START - User: {org_context.username}")

        logger.debug(f"📁 Getting folder with items - folder_id={folder_id}, organization_id={org_context.organization_id}")
        folder_data = FolderService.get_folder_with_items(
            db=db,
            folder_id=folder_id,
            organization_id=org_context.organization_id,
            item_archived_filter=archived
        )

        if not folder_data:
            logger.warning(f"❌ Folder {folder_id} not found in organization {org_context.organization_id}")
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Folder not found"
            )

        # Add user-specific favorite status
        folder_data['is_favorite'] = FolderService.is_favorite(
            db, folder_id, org_context.user_id
        )

        logger.debug(f"📁 Folder data retrieved: {type(folder_data)}")
        logger.debug(f"📁 Folder data keys: {folder_data.keys() if isinstance(folder_data, dict) else 'Not a dict'}")
        logger.info(f"✅ Returning folder data for {folder_id}")
        return folder_data

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"❌ Error in get_folder: {type(e).__name__}: {str(e)}")
        logger.error("❌ Full exception details:", exc_info=True)
        raise


@router.put("/{folder_id}", response_model=FolderResponse)
def update_folder(
    folder_id: UUID,
    folder_update: FolderUpdate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Update a folder.

    Requires organization.write role for access.
    """

    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    updated_folder = FolderService.update_folder(
        db=db,
        folder=folder,
        folder_update=folder_update
    )

    # Build the response with items for consistency
    folder_items = FolderService._get_folder_items_summary(db, updated_folder.id)

    folder_dict = {
        "id": updated_folder.id,
        "organization_id": updated_folder.organization_id,
        "owner": updated_folder.owner or "Unknown",
        "name": updated_folder.name,
        "color": updated_folder.color,
        "icon": updated_folder.icon,
        "tags": updated_folder.tags,
        "is_favorite": FolderService.is_favorite(db, folder_id, org_context.user_id),
        "is_deleted": updated_folder.is_deleted,
        "created_at": updated_folder.created_at,
        "updated_at": updated_folder.updated_at,
        "items": folder_items
    }

    return folder_dict


@router.patch("/{folder_id}", response_model=FolderResponse)
def patch_folder(
    folder_id: UUID,
    folder_update: FolderUpdate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Partially update a folder.

    Requires organization.write role for access.
    Note: Favorites are managed via POST/DELETE /{folder_id}/favorite endpoints.
    """

    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    updated_folder = FolderService.update_folder(
        db=db,
        folder=folder,
        folder_update=folder_update
    )

    # Build the response with items for consistency
    folder_items = FolderService._get_folder_items_summary(db, updated_folder.id)

    folder_dict = {
        "id": updated_folder.id,
        "organization_id": updated_folder.organization_id,
        "owner": updated_folder.owner or "Unknown",
        "name": updated_folder.name,
        "color": updated_folder.color,
        "icon": updated_folder.icon,
        "tags": updated_folder.tags,
        "is_favorite": FolderService.is_favorite(db, folder_id, org_context.user_id),
        "is_deleted": updated_folder.is_deleted,
        "created_at": updated_folder.created_at,
        "updated_at": updated_folder.updated_at,
        "items": folder_items
    }

    return folder_dict


@router.delete("/{folder_id}", response_model=FolderResponse)
def delete_folder(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Soft delete a folder.

    Requires organization.write role for access.
    """

    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    deleted_folder = FolderService.soft_delete_folder(db=db, folder=folder)

    # Build the response with items for consistency
    folder_items = FolderService._get_folder_items_summary(db, deleted_folder.id)

    folder_dict = {
        "id": deleted_folder.id,
        "organization_id": deleted_folder.organization_id,
        "owner": deleted_folder.owner or "Unknown",
        "name": deleted_folder.name,
        "color": deleted_folder.color,
        "icon": deleted_folder.icon,
        "tags": deleted_folder.tags,
        "is_favorite": FolderService.is_favorite(db, folder_id, org_context.user_id),
        "is_deleted": deleted_folder.is_deleted,
        "created_at": deleted_folder.created_at,
        "updated_at": deleted_folder.updated_at,
        "items": folder_items
    }

    return folder_dict


@router.post("/{folder_id}/restore", response_model=FolderResponse)
def restore_folder(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Restore a soft-deleted folder.

    Requires organization.write role for access.
    """

    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id,
        include_deleted=True
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    if not folder.is_deleted:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Folder is not deleted"
        )

    restored_folder = FolderService.restore_folder(db=db, folder=folder)

    # Build the response with items for consistency
    folder_items = FolderService._get_folder_items_summary(db, restored_folder.id)

    folder_dict = {
        "id": restored_folder.id,
        "organization_id": restored_folder.organization_id,
        "owner": restored_folder.owner or "Unknown",
        "name": restored_folder.name,
        "color": restored_folder.color,
        "icon": restored_folder.icon,
        "tags": restored_folder.tags,
        "is_favorite": FolderService.is_favorite(db, folder_id, org_context.user_id),
        "is_deleted": restored_folder.is_deleted,
        "created_at": restored_folder.created_at,
        "updated_at": restored_folder.updated_at,
        "items": folder_items
    }

    return folder_dict


# ==================== User Favorites Endpoints ====================


@router.post("/{folder_id}/favorite", status_code=status.HTTP_201_CREATED)
def add_folder_favorite(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Add a folder to the current user's favorites.

    Requires organization.read role for access.
    """
    import logging
    logger = logging.getLogger(__name__)

    logger.info(f"⭐ POST /folders/{folder_id}/favorite - User: {org_context.username}")

    # Verify folder exists and belongs to organization
    folder = FolderService.get_folder(db, folder_id, org_context.organization_id)
    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    added = FolderService.add_favorite(db, folder_id, org_context.user_id)
    if not added:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Folder already in favorites"
        )

    logger.info(f"✅ Folder {folder_id} added to favorites for user {org_context.username}")
    return {"message": "Folder added to favorites", "is_favorite": True}


@router.delete("/{folder_id}/favorite", status_code=status.HTTP_200_OK)
def remove_folder_favorite(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Remove a folder from the current user's favorites.

    Requires organization.read role for access.
    """
    import logging
    logger = logging.getLogger(__name__)

    logger.info(f"⭐ DELETE /folders/{folder_id}/favorite - User: {org_context.username}")

    # Verify folder exists and belongs to organization
    folder = FolderService.get_folder(db, folder_id, org_context.organization_id)
    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    removed = FolderService.remove_favorite(db, folder_id, org_context.user_id)
    if not removed:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Folder not in favorites"
        )

    logger.info(f"✅ Folder {folder_id} removed from favorites for user {org_context.username}")
    return {"message": "Folder removed from favorites", "is_favorite": False}


@router.post("/{folder_id}/items", response_model=FolderItemResponse)
def add_item_to_folder(
    folder_id: UUID,
    item: FolderItemAdd,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Add an item to a folder.

    Requires organization.write role for access.
    """
    import logging
    logger = logging.getLogger(__name__)

    try:
        logger.debug(f"Adding item to folder - folder_id: {folder_id}, item_id: {item.item_id}, item_type: {item.item_type}")
        logger.debug(f"User context - user_id: {user.sub}, username: {org_context.username}")
        
        folder = FolderService.get_folder(
            db=db,
            folder_id=folder_id,
            organization_id=org_context.organization_id
        )
        
        if not folder:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Folder not found"
            )
        
        folder_item = FolderService.add_item_to_folder(
            db=db,
            folder_id=folder_id,
            item_id=item.item_id,
            item_type=item.item_type,
            owner=org_context.username,
            position=item.position
        )
        
        logger.debug(f"Successfully added item to folder - folder_item_id: {folder_item.id}")
        return folder_item
    except Exception as e:
        logger.error(f"Error adding item to folder: {str(e)}", exc_info=True)
        raise


@router.delete("/{folder_id}/items/{item_id}")
def remove_item_from_folder(
    folder_id: UUID,
    item_id: UUID,
    item_type: str = Query(..., pattern="^(company|contact|document)$"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Remove an item from a folder.

    Requires organization.write role for access.
    """
    
    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )
    
    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )
    
    removed = FolderService.remove_item_from_folder(
        db=db,
        folder_id=folder_id,
        item_id=item_id,
        item_type=item_type
    )
    
    if not removed:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Item not found in folder"
        )
    
    return {"message": "Item removed from folder"}