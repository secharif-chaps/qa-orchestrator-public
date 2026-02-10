"""Module configuration endpoints for organization modules.

This module provides endpoints for managing module enabled/disabled state.
Token management is handled separately in /organizations/{id}/tokens endpoints.

Endpoints:
- GET /organizations/{id}/modules - Get all module configurations
- PUT /organizations/{id}/modules - Update module configurations (admin only)
- PUT /organizations/{id}/modules/{module}/toggle - Toggle module (admin only)

Removed endpoints (use /organizations/{id}/tokens instead):
- GET /organizations/{id}/modules/{module}/tokens - REMOVED
- POST /organizations/{id}/modules/{module}/tokens - REMOVED
"""

from fastapi import APIRouter, Depends, status

from app.core.authorization import verify_organization_access
from app.core.dependencies import get_token_manager
from app.core.keycloak import OIDCUser, idp
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.models.organization import ModuleName
from app.schemas.module import (
    ModuleToggleResponse,
    ModuleUpdateRequest,
    OrganizationModuleResponse,
    OrganizationModulesResponse,
)
from app.services.token_manager import TokenManager

logger = get_logger(__name__)

router = APIRouter(prefix="/organizations", tags=["modules"])


@router.get("/{organization_id}/modules",
            response_model=OrganizationModulesResponse)
async def get_organization_modules(
    organization_id: str,
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get all organization module configurations.

    Returns module enabled/disabled state for all modules.
    Token balance is managed globally - use GET /organizations/{id}/tokens
    to get the organization's token balance.

    Organization members can view their own organization.
    Users with admin.organizations role can view any organization.

    Args:
        organization_id: Keycloak organization UUID

    Returns:
        OrganizationModulesResponse with list of modules and enabled state

    Raises:
        403: If user lacks access to the organization
    """
    # Verify user can access this organization (member OR admin)
    verify_organization_access(organization_id, org_context, user, "modules")

    modules = await token_manager.get_all_organization_modules(organization_id)
    module_responses = [
        OrganizationModuleResponse(
            name=module.module_name,
            enabled=module.enabled,
            created_at=module.created_at,
            updated_at=module.updated_at,
        )
        for module in modules
    ]

    logger.info(
        "Retrieved organization modules",
        extra={
            "organization_id": organization_id,
            "module_count": len(module_responses),
        }
    )

    return OrganizationModulesResponse(modules=module_responses)


@router.put("/{organization_id}/modules",
            response_model=OrganizationModulesResponse)
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
    Token management is handled separately via POST /organizations/{id}/tokens.

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        updates: Dictionary of module name to update request

    Returns:
        OrganizationModulesResponse with updated module states
    """
    logger.info(
        "Updating organization modules",
        extra={
            "organization_id": organization_id,
            "user": user.preferred_username,
            "updates": {
                str(module): req.enabled for module, req in updates.items()
            },
        }
    )

    # Update each module
    for module_name, update_request in updates.items():
        await token_manager.update_module_config(
            organization_id=organization_id,
            module_name=module_name,
            enabled=update_request.enabled,
        )

    # Return updated modules
    modules = await token_manager.get_all_organization_modules(organization_id)
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
    "/{organization_id}/modules/{module}/toggle",
    response_model=ModuleToggleResponse
)
async def toggle_module(
    organization_id: str,
    module: ModuleName,
    body: ModuleUpdateRequest | None = None,
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Toggle or set module enabled/disabled state.

    If request body contains `enabled`, sets the module to that state.
    Otherwise, flips the current enabled state (toggle behavior).

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        module: Module name to toggle
        body: Optional request body with explicit enabled state

    Returns:
        ModuleToggleResponse with updated module state
    """
    current_module = await token_manager.get_or_create_module(
        organization_id, module
    )

    # If body specifies enabled state, use it; otherwise toggle
    if body is not None and body.enabled is not None:
        new_enabled = body.enabled
    else:
        new_enabled = not current_module.enabled

    logger.info(
        "Toggling module",
        extra={
            "organization_id": organization_id,
            "module_name": str(module),
            "old_enabled": current_module.enabled,
            "new_enabled": new_enabled,
            "user": user.preferred_username,
        }
    )

    updated_module = await token_manager.update_module_config(
        organization_id=organization_id,
        module_name=module,
        enabled=new_enabled,
    )

    return ModuleToggleResponse(
        module=updated_module.module_name,
        enabled=updated_module.enabled,
        created_at=updated_module.created_at,
        updated_at=updated_module.updated_at,
    )
