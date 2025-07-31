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


def sanitize_input(input_string: str, max_length: int = 255) -> str:
    """
    Sanitize user input to prevent injection attacks
    
    Args:
        input_string: Input to sanitize
        max_length: Maximum allowed length
        
    Returns:
        Sanitized string
        
    Raises:
        HTTPException: If input is invalid
    """
    if not input_string:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Input cannot be empty"
        )
    
    # Remove dangerous characters
    sanitized = input_string.strip()
    
    # Check length
    if len(sanitized) > max_length:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Input too long (max {max_length} characters)"
        )
    
    # Check for SQL injection patterns
    dangerous_patterns = [
        "SELECT", "INSERT", "UPDATE", "DELETE", "DROP", "CREATE", "ALTER",
        "UNION", "SCRIPT", "JAVASCRIPT", "VBSCRIPT", "ONLOAD", "ONERROR"
    ]
    
    upper_input = sanitized.upper()
    for pattern in dangerous_patterns:
        if pattern in upper_input:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Input contains potentially dangerous content"
            )
    
    return sanitized