"""
Workspace User Management Service

Combines Keycloak user management with workspace member tracking
"""

from typing import List, Optional, Dict, Any
from sqlalchemy.orm import Session
from fastapi import HTTPException, status
import logging
from datetime import datetime

from app.services.keycloak_admin import keycloak_admin_service
from app.models.workspace import Workspace, WorkspaceMember, WorkspaceMemberStatus
from app.schemas.workspace_user import (
    WorkspaceUserCreate,
    WorkspaceUserUpdate,
    WorkspaceUserResponse,
    WorkspaceUserListResponse,
    WorkspaceUserCreateResponse,
    UserStatus,
    UserQueryParams
)

logger = logging.getLogger(__name__)


class WorkspaceUserService:
    """Service for managing users within workspaces"""
    
    def __init__(self, db: Session):
        self.db = db
        self.keycloak_admin = keycloak_admin_service
    
    def _keycloak_user_to_response(self, keycloak_user: Dict[str, Any], workspace_member: Optional[WorkspaceMember] = None) -> WorkspaceUserResponse:
        """Convert Keycloak user data to response format"""
        
        # Determine user status
        status = UserStatus.ACTIVE
        if not keycloak_user.get("enabled", True):
            status = UserStatus.INACTIVE
        elif not keycloak_user.get("emailVerified", False):
            status = UserStatus.PENDING
        
        # Format creation timestamp
        created_timestamp = keycloak_user.get("createdTimestamp")
        created_at = ""
        if created_timestamp:
            created_at = datetime.fromtimestamp(created_timestamp / 1000).isoformat()
        
        return WorkspaceUserResponse(
            id=keycloak_user["id"],
            username=keycloak_user.get("username", ""),
            email=keycloak_user.get("email", ""),
            firstName=keycloak_user.get("firstName"),
            lastName=keycloak_user.get("lastName"),
            enabled=keycloak_user.get("enabled", True),
            emailVerified=keycloak_user.get("emailVerified", False),
            createdAt=created_at,
            lastLogin=None,  # TODO: Extract from user sessions if available
            status=status
        )
    
    async def create_user(self, workspace_id: int, user_data: WorkspaceUserCreate) -> WorkspaceUserCreateResponse:
        """Create a new user in Keycloak and add to workspace"""
        
        # Validate workspace ID
        if workspace_id <= 0:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Invalid workspace ID"
            )
        
        # Verify workspace exists
        workspace = self.db.query(Workspace).filter(Workspace.id == workspace_id).first()
        if not workspace:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Workspace not found"
            )
        
        # Check if user already exists in this workspace
        existing_member = self.db.query(WorkspaceMember).filter(
            WorkspaceMember.workspace_id == workspace_id,
            WorkspaceMember.email == user_data.email
        ).first()
        
        if existing_member:
            raise HTTPException(
                status_code=status.HTTP_409_CONFLICT,
                detail="User is already a member of this workspace"
            )
        
        try:
            # Create user in Keycloak
            keycloak_user = await self.keycloak_admin.create_user({
                "username": user_data.username,
                "email": user_data.email,
                "firstName": user_data.firstName,
                "lastName": user_data.lastName,
                "temporaryPassword": user_data.temporaryPassword
            })
            
            # Add to workspace members
            member = WorkspaceMember(
                workspace_id=workspace_id,
                user_id=keycloak_user["id"],
                username=user_data.username,
                email=user_data.email,
                status=WorkspaceMemberStatus.ACTIVE
            )
            
            self.db.add(member)
            self.db.commit()
            self.db.refresh(member)
            
            # Convert to response format
            response = self._keycloak_user_to_response(keycloak_user, member)
            
            # Add temporary password to response (only returned on creation)
            create_response = WorkspaceUserCreateResponse(**response.dict())
            create_response.temporaryPassword = keycloak_user.get("temporaryPassword")
            
            return create_response
            
        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error creating workspace user: {e}")
            # Cleanup: try to remove from workspace if Keycloak user was created
            if 'keycloak_user' in locals():
                try:
                    await self.keycloak_admin.delete_user(keycloak_user["id"])
                except:
                    pass
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to create user"
            )
    
    async def get_users(self, workspace_id: int, query: UserQueryParams) -> WorkspaceUserListResponse:
        """Get paginated list of workspace users"""
        
        # Verify workspace exists
        workspace = self.db.query(Workspace).filter(Workspace.id == workspace_id).first()
        if not workspace:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Workspace not found"
            )
        
        # Get workspace members
        members_query = self.db.query(WorkspaceMember).filter(
            WorkspaceMember.workspace_id == workspace_id
        )
        
        # Apply search filter if provided
        if query.search:
            search_term = f"%{query.search}%"
            members_query = members_query.filter(
                (WorkspaceMember.username.ilike(search_term)) |
                (WorkspaceMember.email.ilike(search_term))
            )
        
        # Get total count
        total = members_query.count()
        
        # Apply pagination (convert 1-based page to 0-based offset)
        offset = max(0, (query.page - 1) * query.limit)
        members = members_query.offset(offset).limit(query.limit).all()
        
        # Get user details from Keycloak for each member
        users = []
        for member in members:
            try:
                keycloak_user = await self.keycloak_admin.get_user(member.user_id)
                if keycloak_user:
                    user_response = self._keycloak_user_to_response(keycloak_user, member)
                    
                    # Apply status filter if provided
                    if query.status and user_response.status != query.status:
                        continue
                    
                    users.append(user_response)
                else:
                    # User exists in workspace but not in Keycloak - mark as inactive
                    fallback_user = WorkspaceUserResponse(
                        id=member.user_id,
                        username=member.username,
                        email=member.email,
                        firstName="",
                        lastName="",
                        enabled=False,
                        emailVerified=False,
                        createdAt=member.created_at.isoformat(),
                        lastLogin=None,
                        status=UserStatus.INACTIVE
                    )
                    users.append(fallback_user)
            except Exception as e:
                logger.warning(f"Failed to get Keycloak user {member.user_id}: {e}")
                continue
        
        return WorkspaceUserListResponse(
            users=users,
            total=total,
            page=query.page,
            limit=query.limit
        )
    
    async def get_user(self, workspace_id: int, user_id: str) -> Optional[WorkspaceUserResponse]:
        """Get specific user details"""
        
        # Verify workspace exists
        workspace = self.db.query(Workspace).filter(Workspace.id == workspace_id).first()
        if not workspace:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Workspace not found"
            )
        
        # Check if user is member of workspace
        member = self.db.query(WorkspaceMember).filter(
            WorkspaceMember.workspace_id == workspace_id,
            WorkspaceMember.user_id == user_id
        ).first()
        
        if not member:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found in workspace"
            )
        
        # Get user details from Keycloak
        keycloak_user = await self.keycloak_admin.get_user(user_id)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found in Keycloak"
            )
        
        return self._keycloak_user_to_response(keycloak_user, member)
    
    async def update_user(self, workspace_id: int, user_id: str, user_data: WorkspaceUserUpdate) -> WorkspaceUserResponse:
        """Update user details"""
        
        # Verify workspace exists
        workspace = self.db.query(Workspace).filter(Workspace.id == workspace_id).first()
        if not workspace:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Workspace not found"
            )
        
        # Check if user is member of workspace
        member = self.db.query(WorkspaceMember).filter(
            WorkspaceMember.workspace_id == workspace_id,
            WorkspaceMember.user_id == user_id
        ).first()
        
        if not member:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found in workspace"
            )
        
        # Update user in Keycloak
        update_data = user_data.dict(exclude_unset=True)
        success = await self.keycloak_admin.update_user(user_id, update_data)
        
        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to update user in Keycloak"
            )
        
        # Update workspace member record
        if user_data.username:
            member.username = user_data.username
        if user_data.email:
            member.email = user_data.email
        
        self.db.commit()
        self.db.refresh(member)
        
        # Get updated user details
        keycloak_user = await self.keycloak_admin.get_user(user_id)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to retrieve updated user details"
            )
        
        return self._keycloak_user_to_response(keycloak_user, member)
    
    async def toggle_user_status(self, workspace_id: int, user_id: str, enabled: bool) -> WorkspaceUserResponse:
        """Enable or disable user account"""
        
        # Verify workspace exists
        workspace = self.db.query(Workspace).filter(Workspace.id == workspace_id).first()
        if not workspace:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Workspace not found"
            )
        
        # Check if user is member of workspace
        member = self.db.query(WorkspaceMember).filter(
            WorkspaceMember.workspace_id == workspace_id,
            WorkspaceMember.user_id == user_id
        ).first()
        
        if not member:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found in workspace"
            )
        
        # Update user status in Keycloak
        success = await self.keycloak_admin.update_user(user_id, {"enabled": enabled})
        
        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to update user status"
            )
        
        # Update workspace member status
        member.status = WorkspaceMemberStatus.ACTIVE if enabled else WorkspaceMemberStatus.REVOKED
        self.db.commit()
        self.db.refresh(member)
        
        # Get updated user details
        keycloak_user = await self.keycloak_admin.get_user(user_id)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to retrieve updated user details"
            )
        
        return self._keycloak_user_to_response(keycloak_user, member)
    
    async def delete_user(self, workspace_id: int, user_id: str) -> bool:
        """Remove user from workspace and optionally from Keycloak"""
        
        # Verify workspace exists
        workspace = self.db.query(Workspace).filter(Workspace.id == workspace_id).first()
        if not workspace:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Workspace not found"
            )
        
        # Check if user is member of workspace
        member = self.db.query(WorkspaceMember).filter(
            WorkspaceMember.workspace_id == workspace_id,
            WorkspaceMember.user_id == user_id
        ).first()
        
        if not member:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found in workspace"
            )
        
        try:
            # Remove from workspace (soft delete by setting status to REVOKED)
            member.status = WorkspaceMemberStatus.REVOKED
            self.db.commit()
            
            # Optionally delete from Keycloak (commented out for safety)
            # await self.keycloak_admin.delete_user(user_id)
            
            return True
            
        except Exception as e:
            logger.error(f"Error deleting workspace user: {e}")
            self.db.rollback()
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to remove user from workspace"
            )
    
    async def send_password_reset(self, workspace_id: int, user_id: str) -> bool:
        """Send password reset email to user"""
        
        # Verify workspace exists
        workspace = self.db.query(Workspace).filter(Workspace.id == workspace_id).first()
        if not workspace:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Workspace not found"
            )
        
        # Check if user is member of workspace
        member = self.db.query(WorkspaceMember).filter(
            WorkspaceMember.workspace_id == workspace_id,
            WorkspaceMember.user_id == user_id
        ).first()
        
        if not member:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found in workspace"
            )
        
        # Send password reset email via Keycloak
        success = await self.keycloak_admin.send_password_reset_email(user_id)
        
        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to send password reset email"
            )
        
        return True