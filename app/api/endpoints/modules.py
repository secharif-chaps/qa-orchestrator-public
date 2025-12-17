"""Module configuration endpoints for organization modules.

This module provides endpoints for managing module enabled/disabled state.
Token management has been moved to the /organizations/{id}/tokens endpoints.

Remaining endpoints:
- GET /organizations/{id}/modules - Get all module configurations
- PUT /organizations/{id}/modules - Update module configurations (admin only)
- PUT /organizations/{id}/modules/{module}/toggle - Toggle module enabled state (admin only)

Removed endpoints (use /organizations/{id}/tokens instead):
- GET /organizations/{id}/modules/{module}/tokens - REMOVED
- POST /organizations/{id}/modules/{module}/tokens - REMOVED
"""

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi_keycloak import OIDCUser

from app.core.dependencies import get_token_manager
from app.core.keycloak import idp
from app.core.organization import OrganizationContext, get_user_organization
from app.models.organization import ModuleName
from app.schemas.module import (
    ModuleToggleResponse,
    ModuleUpdateRequest,
    OrganizationModuleResponse,
    OrganizationModulesResponse,
)
from app.services.token_manager import TokenManager

router = APIRouter(prefix="/organizations", tags=["modules"])


@router.get("/{organization_id}/modules", response_model=OrganizationModulesResponse)
async def get_organization_modules(
    organization_id: str,
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get all organization module configurations.

    Returns module enabled/disabled state for all modules.
    Token balance is now managed globally - use GET /organizations/{id}/tokens
    to get the organization's token balance.

    Organization members can view their own organization.
    Users with admin.organizations role can view any organization.

    Args:
        organization_id: Keycloak organization UUID

    Returns:
        OrganizationModulesResponse with list of modules and their enabled state

    Raises:
        403: If user lacks access to the organization
    """
    # Check if user has admin.organizations role or belongs to the organization
    is_org_admin = (
        hasattr(user, "roles") and user.roles and "admin.organizations" in user.roles
    )
    is_org_member = org_context.organization_id == organization_id

    if not (is_org_admin or is_org_member):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied to this organization",
        )

    modules = token_manager.get_all_organization_modules(organization_id)
    module_responses = [
        OrganizationModuleResponse(
            name=module.module_name,
            enabled=module.enabled,
            created_at=module.created_at,
            updated_at=module.updated_at,
        )
        for module in modules
    ]

    return OrganizationModulesResponse(modules=module_responses)


@router.put("/{organization_id}/modules", response_model=OrganizationModulesResponse)
async def update_organization_modules(
    organization_id: str,
    updates: dict[ModuleName, ModuleUpdateRequest],
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Update module enablement for an organization.

    Updates the enabled/disabled state for specified modules.
    Token management has been moved to POST /organizations/{id}/tokens.

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        updates: Dictionary of module name to update request

    Returns:
        OrganizationModulesResponse with updated module states
    """
    # Update each module
    for module_name, update_request in updates.items():
        token_manager.update_module_config(
            organization_id=organization_id,
            module_name=module_name,
            enabled=update_request.enabled,
        )

    # Return updated modules
    modules = token_manager.get_all_organization_modules(organization_id)
    module_responses = [
        OrganizationModuleResponse(
            name=module.module_name,
            enabled=module.enabled,
            created_at=module.created_at,
            updated_at=module.updated_at,
        )
        for module in modules
    ]

    return OrganizationModulesResponse(modules=module_responses)


@router.put(
    "/{organization_id}/modules/{module}/toggle", response_model=ModuleToggleResponse
)
async def toggle_module(
    organization_id: str,
    module: ModuleName,
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Toggle module enabled/disabled state.

    Flips the current enabled state of the specified module.

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        module: Module name to toggle

    Returns:
        ModuleToggleResponse with updated module state
    """
    current_module = token_manager.get_or_create_module(organization_id, module)

    updated_module = token_manager.update_module_config(
        organization_id=organization_id,
        module_name=module,
        enabled=not current_module.enabled,
    )

    return ModuleToggleResponse(
        module=updated_module.module_name,
        enabled=updated_module.enabled,
        created_at=updated_module.created_at,
        updated_at=updated_module.updated_at,
    )
