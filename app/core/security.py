"""
Security utilities for authorization and access control
"""

from typing import Optional
from fastapi import HTTPException, status, Depends
from sqlalchemy.orm import Session
from app.schemas.user import TokenData
from app.models.company import Company
from app.core.workspace import WorkspaceContext
from app.database import get_db
from app.services.permission import PermissionService


class AuthorizationError(HTTPException):
    """Custom exception for authorization errors"""
    def __init__(self, detail: str = "Insufficient permissions"):
        super().__init__(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=detail
        )


def verify_company_ownership(company: Optional[Company], current_user: TokenData) -> Company:
    """
    Verify that the current user owns the specified company
    
    Args:
        company: Company object to check ownership for
        current_user: Current authenticated user
        
    Returns:
        Company object if user owns it
        
    Raises:
        HTTPException: If company doesn't exist or user doesn't own it
    """
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Company not found"
        )
    
    if company.owner_username != current_user.username:
        raise AuthorizationError("You don't have permission to access this company")
    
    return company


def verify_company_workspace_access(company: Optional[Company], workspace_context: WorkspaceContext) -> Company:
    """
    Verify that the company belongs to the user's workspace
    
    Args:
        company: Company object to check
        workspace_context: Current user's workspace context
        
    Returns:
        Company object if it belongs to the workspace
        
    Raises:
        HTTPException: If company doesn't exist or doesn't belong to workspace
    """
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Company not found"
        )
    
    if company.workspace_id != workspace_context.workspace_id:
        raise AuthorizationError("Company not found in your workspace")
    
    return company


def verify_company_modify_permission(workspace_context: WorkspaceContext, permission: str) -> WorkspaceContext:
    """
    Verify that the user has permission to modify companies in their workspace
    
    Args:
        workspace_context: Current user's workspace context
        permission: Required permission (e.g., 'company.update', 'company.delete')
        
    Returns:
        WorkspaceContext if user has permission
        
    Raises:
        AuthorizationError: If user doesn't have permission
    """
    if not workspace_context.user.roles or permission not in workspace_context.user.roles:
        raise AuthorizationError(f"Permission denied: {permission} required")
    
    return workspace_context


def verify_admin_access(current_user: TokenData) -> TokenData:
    """
    Verify that the current user has admin privileges
    
    Args:
        current_user: Current authenticated user
        
    Returns:
        TokenData if user is admin
        
    Raises:
        AuthorizationError: If user is not admin
    """
    if not current_user.roles or "admin" not in current_user.roles:
        raise AuthorizationError("Admin access required")
    
    return current_user


def verify_workspace_admin_access(current_user: TokenData) -> TokenData:
    """
    Verify that the current user has workspace admin privileges
    
    Args:
        current_user: Current authenticated user
        
    Returns:
        TokenData if user has admin.workspaces role
        
    Raises:
        AuthorizationError: If user doesn't have admin.workspaces role
    """
    if not current_user.roles or "admin.workspaces" not in current_user.roles:
        raise AuthorizationError("Workspace admin access required (admin.workspaces role)")
    
    return current_user


def verify_workflow_admin_access(current_user: TokenData) -> TokenData:
    """
    Verify that the current user has workflow admin privileges
    
    Args:
        current_user: Current authenticated user
        
    Returns:
        TokenData if user has admin.workflows role
        
    Raises:
        AuthorizationError: If user doesn't have admin.workflows role
    """
    if not current_user.roles or "admin.workflows" not in current_user.roles:
        raise AuthorizationError("Workflow admin access required (admin.workflows role)")
    
    return current_user


def verify_cost_admin_access(current_user: TokenData) -> TokenData:
    """
    Verify that the current user has cost analysis admin privileges
    
    Args:
        current_user: Current authenticated user
        
    Returns:
        TokenData if user has admin.costs role
        
    Raises:
        AuthorizationError: If user doesn't have admin.costs role
    """
    if not current_user.roles or "admin.costs" not in current_user.roles:
        raise AuthorizationError("Cost admin access required (admin.costs role)")
    
    return current_user


def verify_workspace_permission(current_user: TokenData, workspace_id: int, permission: str, db: Session | None = None) -> TokenData:
    """
    Verify that the current user has a specific permission for a workspace.

    Uses JWT-only permission check for security. Database parameter is ignored
    to maintain backward compatibility with existing code.

    Args:
        current_user: Current authenticated user
        workspace_id: ID of the workspace to check permission for
        permission: Required permission (e.g., 'workspace.manage', 'workspace.users.read')
        db: Database session (DEPRECATED - ignored for security)

    Returns:
        TokenData if user has permission

    Raises:
        AuthorizationError: If user doesn't have the required permission
    """
    # JWT-only permission check (no database fallback to prevent race conditions)
    jwt_has_permission = False

    if current_user.roles:
        # Check for exact permission match
        if permission in current_user.roles:
            jwt_has_permission = True
        # Check for admin permissions (global)
        elif permission.startswith('workspace.') and 'admin.workspaces' in current_user.roles:
            jwt_has_permission = True
        # Legacy role mapping
        elif permission == "workspace.write" and "workspace.write" in current_user.roles:
            jwt_has_permission = True

    if jwt_has_permission:
        return current_user

    raise AuthorizationError(f"Permission denied: {permission} required for workspace {workspace_id}")


def verify_workspace_permission_with_db(current_user: TokenData, workspace_id: int, permission: str, db: Session) -> TokenData:
    """
    Wrapper for verify_workspace_permission that ensures database session is provided
    """
    return verify_workspace_permission(current_user, workspace_id, permission, db)


def verify_user_or_admin_access(resource_owner: str, current_user: TokenData) -> TokenData:
    """
    Verify that the current user is either the resource owner or an admin

    Args:
        resource_owner: Username of the resource owner
        current_user: Current authenticated user

    Returns:
        TokenData if user has access

    Raises:
        AuthorizationError: If user doesn't have access
    """
    if current_user.username != resource_owner:
        if not current_user.roles or "admin" not in current_user.roles:
            raise AuthorizationError("You don't have permission to access this resource")

    return current_user