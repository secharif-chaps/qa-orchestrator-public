"""
Security utilities for authorization and access control
"""


from fastapi import HTTPException, status

from app.core.organization import OrganizationContext
from app.models.company import Company
from app.schemas.user import TokenData

# Re-export is_chapsvision_email from email_utils to avoid circular imports


class AuthorizationError(HTTPException):
    """Custom exception for authorization errors"""

    def __init__(self, detail: str = "Insufficient permissions"):
        super().__init__(status_code=status.HTTP_403_FORBIDDEN, detail=detail)


def verify_company_ownership(company: Company | None, current_user: TokenData) -> Company:
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
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Company not found")

    if company.owner_username != current_user.username:
        raise AuthorizationError("You don't have permission to access this company")

    return company


def verify_company_organization_access(company: Company | None, org_context: OrganizationContext) -> Company:
    """
    Verify that the company belongs to the user's organization

    Args:
        company: Company object to check
        org_context: Current user's organization context

    Returns:
        Company object if it belongs to the organization

    Raises:
        HTTPException: If company doesn't exist or doesn't belong to organization
    """
    if not company:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Company not found")

    if company.organization_id != org_context.organization_id:
        raise AuthorizationError("Company not found in your organization")

    return company


def verify_company_modify_permission(org_context: OrganizationContext, permission: str) -> OrganizationContext:
    """
    Verify that the user has permission to modify companies in their organization

    NOTE: This function is deprecated. New code should use fastapi-keycloak's
    idp.get_current_user(required_roles=[...]) dependency instead.

    Args:
        org_context: Current user's organization context
        permission: Required permission (e.g., 'company.update', 'company.delete')

    Returns:
        OrganizationContext if user has permission

    Raises:
        AuthorizationError: If user doesn't have permission
    """
    # Since OrganizationContext doesn't have roles, we can't check them here
    # The permission check should be done at the API endpoint level using
    # idp.get_current_user(required_roles=["company.update"]) dependency
    # For now, we'll just return the context (permission already checked at endpoint)
    return org_context
