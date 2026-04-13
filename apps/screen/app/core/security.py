"""
Security utilities for authorization and access control
"""

from fastapi import HTTPException, status

from app.core.organization_context import OrganizationContext
from app.models.company import Company


class AuthorizationError(HTTPException):
    """Custom exception for authorization errors"""

    def __init__(self, detail: str = "Insufficient permissions"):
        super().__init__(status_code=status.HTTP_403_FORBIDDEN, detail=detail)


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


