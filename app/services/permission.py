"""
Permission Service for granular permission management
"""

from typing import List, Optional
from sqlalchemy.orm import Session
from sqlalchemy import or_
from fastapi import HTTPException, status
import logging

from app.models.permission import UserWorkspacePermission
from app.models.workspace import Workspace
from app.schemas.permission import (
    UserPermissionCreate,
    UserPermissionResponse,
    UserPermissionsListResponse,
    PermissionGrantRequest,
    PermissionRevokeRequest
)
from app.services.keycloak_admin import keycloak_admin_service

logger = logging.getLogger(__name__)


class PermissionService:
    """Service for managing granular permissions"""
    
    def __init__(self, db: Session):
        self.db = db
    
    def has_permission(self, user_id: str, permission: str, workspace_id: Optional[int] = None) -> bool:
        """
        Check if user has a specific permission
        
        Args:
            user_id: Keycloak user ID
            permission: Permission to check
            workspace_id: Workspace ID (for workspace permissions)
            
        Returns:
            True if user has permission, False otherwise
        """
        query = self.db.query(UserWorkspacePermission).filter(
            UserWorkspacePermission.user_id == user_id,
            UserWorkspacePermission.permission == permission
        )
        
        # For workspace permissions, check specific workspace
        if permission.startswith('workspace.') or permission.startswith('company.'):
            if workspace_id is None:
                return False
            query = query.filter(UserWorkspacePermission.workspace_id == workspace_id)
        else:
            # For global permissions, workspace_id should be None
            query = query.filter(UserWorkspacePermission.workspace_id.is_(None))
        
        return query.first() is not None
    
    def get_user_permissions(self, user_id: str, workspace_id: Optional[int] = None) -> UserPermissionsListResponse:
        """
        Get all permissions for a user
        
        Args:
            user_id: Keycloak user ID
            workspace_id: Optional workspace filter
            
        Returns:
            UserPermissionsListResponse with permissions
        """
        query = self.db.query(UserWorkspacePermission).filter(
            UserWorkspacePermission.user_id == user_id
        )
        
        if workspace_id is not None:
            # Get permissions for specific workspace + global permissions
            query = query.filter(
                or_(
                    UserWorkspacePermission.workspace_id == workspace_id,
                    UserWorkspacePermission.workspace_id.is_(None)
                )
            )
        
        permissions = query.all()
        
        # Separate workspace and global permissions
        workspace_permissions = [p for p in permissions if p.workspace_id is not None]
        global_permissions = [p for p in permissions if p.workspace_id is None]
        
        return UserPermissionsListResponse(
            user_id=user_id,
            workspace_permissions=[UserPermissionResponse.from_orm(p) for p in workspace_permissions],
            global_permissions=[UserPermissionResponse.from_orm(p) for p in global_permissions],
            total_permissions=len(permissions)
        )
    
    def get_user_permissions_list(self, user_id: str, workspace_id: Optional[int] = None) -> List[str]:
        """
        Get list of permission strings for a user
        
        Args:
            user_id: Keycloak user ID
            workspace_id: Optional workspace filter
            
        Returns:
            List of permission strings
        """
        query = self.db.query(UserWorkspacePermission.permission).filter(
            UserWorkspacePermission.user_id == user_id
        )
        
        if workspace_id is not None:
            query = query.filter(
                or_(
                    UserWorkspacePermission.workspace_id == workspace_id,
                    UserWorkspacePermission.workspace_id.is_(None)
                )
            )
        
        return [p[0] for p in query.all()]
    
    def grant_permission(self, permission_data: UserPermissionCreate, granted_by: str) -> UserPermissionResponse:
        """
        Grant a permission to a user
        
        Args:
            permission_data: Permission to grant
            granted_by: Who is granting the permission
            
        Returns:
            UserPermissionResponse
        """
        # Check if permission already exists
        existing = self.db.query(UserWorkspacePermission).filter(
            UserWorkspacePermission.user_id == permission_data.user_id,
            UserWorkspacePermission.workspace_id == permission_data.workspace_id,
            UserWorkspacePermission.permission == permission_data.permission
        ).first()
        
        if existing:
            raise HTTPException(
                status_code=status.HTTP_409_CONFLICT,
                detail=f"User already has permission '{permission_data.permission}'"
            )
        
        # Validate workspace exists for workspace permissions
        if permission_data.workspace_id is not None:
            workspace = self.db.query(Workspace).filter(Workspace.id == permission_data.workspace_id).first()
            if not workspace:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Workspace not found"
                )
        
        # Create permission
        permission = UserWorkspacePermission(
            user_id=permission_data.user_id,
            workspace_id=permission_data.workspace_id,
            permission=permission_data.permission,
            granted_by=granted_by
        )
        
        self.db.add(permission)
        self.db.commit()
        self.db.refresh(permission)
        
        logger.info(f"Granted permission '{permission_data.permission}' to user {permission_data.user_id} by {granted_by}")
        
        return UserPermissionResponse.from_orm(permission)
    
    def revoke_permission(self, user_id: str, permission: str, workspace_id: Optional[int] = None) -> bool:
        """
        Revoke a permission from a user
        
        Args:
            user_id: Keycloak user ID
            permission: Permission to revoke
            workspace_id: Workspace ID (for workspace permissions)
            
        Returns:
            True if permission was revoked, False if not found
        """
        query = self.db.query(UserWorkspacePermission).filter(
            UserWorkspacePermission.user_id == user_id,
            UserWorkspacePermission.permission == permission
        )
        
        if workspace_id is not None:
            query = query.filter(UserWorkspacePermission.workspace_id == workspace_id)
        else:
            query = query.filter(UserWorkspacePermission.workspace_id.is_(None))
        
        permission_record = query.first()
        if permission_record:
            self.db.delete(permission_record)
            self.db.commit()
            logger.info(f"Revoked permission '{permission}' from user {user_id}")
            return True
        
        return False
    
    def grant_permissions_bulk(self, request: PermissionGrantRequest, granted_by: str) -> List[UserPermissionResponse]:
        """
        Grant multiple permissions to a user
        
        Args:
            request: Permission grant request
            granted_by: Who is granting the permissions
            
        Returns:
            List of granted permissions
        """
        granted_permissions = []
        
        for permission in request.permissions:
            try:
                permission_data = UserPermissionCreate(
                    user_id=request.user_id,
                    workspace_id=request.workspace_id,
                    permission=permission,
                    granted_by=granted_by
                )
                
                granted = self.grant_permission(permission_data, granted_by)
                granted_permissions.append(granted)
                
            except HTTPException as e:
                if e.status_code == status.HTTP_409_CONFLICT:
                    # Skip already existing permissions
                    logger.warning(f"Permission '{permission}' already exists for user {request.user_id}")
                    continue
                else:
                    raise
        
        return granted_permissions
    
    def revoke_permissions_bulk(self, request: PermissionRevokeRequest) -> int:
        """
        Revoke multiple permissions from a user
        
        Args:
            request: Permission revoke request
            
        Returns:
            Number of permissions revoked
        """
        revoked_count = 0
        
        for permission in request.permissions:
            if self.revoke_permission(request.user_id, permission, request.workspace_id):
                revoked_count += 1
        
        return revoked_count
    
    async def sync_user_workspace_permissions(self, user_id: str, workspace_id: int, new_permissions: List[str], granted_by: str, sync_keycloak: bool = True) -> None:
        """
        Sync user permissions for a specific workspace
        This replaces all existing workspace permissions with the new set
        and optionally syncs with Keycloak roles
        
        Args:
            user_id: Keycloak user ID
            workspace_id: Workspace ID
            new_permissions: List of new permissions to grant
            granted_by: Who is updating the permissions
            sync_keycloak: Whether to sync the roles with Keycloak (default: True)
        """
        try:
            # Get current permissions for this user in this workspace
            current_permissions = self.db.query(UserWorkspacePermission).filter(
                UserWorkspacePermission.user_id == user_id,
                UserWorkspacePermission.workspace_id == workspace_id
            ).all()
            
            current_permission_names = {p.permission for p in current_permissions}
            new_permission_names = set(new_permissions)
            
            # Determine which permissions to add and remove
            permissions_to_add = new_permission_names - current_permission_names
            permissions_to_remove = current_permission_names - new_permission_names
            
            # Remove obsolete permissions
            for permission_name in permissions_to_remove:
                self.revoke_permission(user_id, permission_name, workspace_id)
            
            # Add new permissions
            for permission_name in permissions_to_add:
                permission_data = UserPermissionCreate(
                    user_id=user_id,
                    workspace_id=workspace_id,
                    permission=permission_name,
                    granted_by=granted_by
                )
                try:
                    self.grant_permission(permission_data, granted_by)
                except HTTPException as e:
                    if e.status_code != status.HTTP_409_CONFLICT:
                        # Re-raise if it's not a conflict (already exists)
                        raise
            
            # Sync with Keycloak if enabled
            if sync_keycloak:
                try:
                    # Get all user permissions for this workspace to sync with Keycloak
                    all_user_permissions = self.get_user_permissions_list(user_id, workspace_id)
                    
                    logger.info(f"🔄 Setting roles {all_user_permissions} to user id {user_id}")
                    
                    # Sync roles with Keycloak
                    keycloak_sync_success = await keycloak_admin_service.sync_user_realm_roles(
                        user_id=user_id,
                        target_roles=all_user_permissions
                    )
                    
                    if not keycloak_sync_success:
                        logger.warning(f"Failed to sync Keycloak roles for user {user_id}, but database permissions were updated")
                        
                except Exception as keycloak_error:
                    logger.warning(f"Failed to sync with Keycloak for user {user_id}: {keycloak_error}. Database permissions were still updated.")
            
            logger.info(f"Synced permissions for user {user_id} in workspace {workspace_id}: +{len(permissions_to_add)}, -{len(permissions_to_remove)}")
            
        except Exception as e:
            logger.error(f"Error syncing permissions for user {user_id} in workspace {workspace_id}: {str(e)}")
            self.db.rollback()
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to sync user permissions"
            )
    
    def sync_jwt_permissions(self, user_id: str, jwt_roles: List[str]) -> None:
        """
        Sync permissions from JWT token to database
        This ensures database permissions match JWT permissions
        
        Args:
            user_id: Keycloak user ID
            jwt_roles: Roles/permissions from JWT token
        """
        # For now, just log the sync request
        # In full implementation, this would:
        # 1. Compare JWT roles with database permissions
        # 2. Add missing permissions
        # 3. Remove obsolete permissions (with caution)
        
        logger.info(f"Sync requested for user {user_id} with JWT roles: {jwt_roles}")
        
        # TODO: Implement full sync logic
        pass
    
    def can_manage_workspace_permissions(self, user_id: str, workspace_id: int) -> bool:
        """
        Check if user can manage permissions for a workspace
        
        Args:
            user_id: User to check
            workspace_id: Workspace ID
            
        Returns:
            True if user can manage permissions
        """
        # User can manage if they have:
        # 1. admin.organizations (global)
        # 2. workspace.write for this workspace
        # 3. workspace.users.manage for this workspace

        return (
            self.has_permission(user_id, "admin.organizations") or
            self.has_permission(user_id, "workspace.write", workspace_id) or
            self.has_permission(user_id, "workspace.users.manage", workspace_id)
        )