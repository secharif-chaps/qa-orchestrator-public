"""
Security utilities for authorization and access control
"""

from typing import Optional
from fastapi import HTTPException, status
from app.schemas.user import TokenData
from app.models.company import Company
from app.core.workspace import WorkspaceContext


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
