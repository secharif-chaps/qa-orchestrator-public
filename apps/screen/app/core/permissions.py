"""Permission tier definitions and role mappings.

This module defines the permission tier system for team management,
abstracting away individual Keycloak roles into a simple 3-tier model.
"""

from enum import StrEnum


class PermissionTier(StrEnum):
    """Permission tiers for team members.

    Provides a simplified abstraction over Keycloak realm roles,
    making it easier for users to understand and manage permissions.
    """

    READER = "reader"
    WRITER = "writer"
    MANAGER = "manager"
    ADMIN = "admin"


# Role mappings for each tier (cumulative)
TIER_ROLE_MAPPING: dict[PermissionTier, list[str]] = {
    PermissionTier.READER: [
        "organization.read",
    ],
    PermissionTier.WRITER: [
        "organization.read",
        "organization.write",
        "company.view",
        "company.create",
        "company.delete",
    ],
    PermissionTier.MANAGER: [
        "organization.read",
        "organization.write",
        "organization.manage",
        "company.view",
        "company.create",
        "company.delete",
    ],
    PermissionTier.ADMIN: [
        "organization.read",
        "organization.write",
        "organization.manage",
        "company.view",
        "company.create",
        "company.delete",
        "admin.organizations",
    ],
}


def get_roles_for_tier(tier: PermissionTier) -> list[str]:
    """Get all roles for a permission tier.

    Args:
        tier: Permission tier

    Returns:
        List of Keycloak realm role names for the tier

    Example:
        >>> get_roles_for_tier(PermissionTier.MANAGER)
        ['organization.read', 'organization.write', 'organization.manage', 'company.view', 'company.create', 'company.delete']
    """
    return TIER_ROLE_MAPPING[tier].copy()


def get_tier_from_roles(roles: list[str]) -> PermissionTier:
    """Determine permission tier from user's roles.

    Returns highest matching tier based on role presence.
    This allows users to have additional custom roles without breaking tier detection.

    Special case: Users with 'admin.organizations' role get ADMIN tier.

    Args:
        roles: List of Keycloak realm role names

    Returns:
        Highest permission tier user qualifies for

    Example:
        >>> get_tier_from_roles(['organization.read', 'organization.write', 'company.view'])
        PermissionTier.WRITER
        >>> get_tier_from_roles(['admin.organizations'])
        PermissionTier.ADMIN
    """
    role_set = set(roles)

    # Check from highest to lowest tier
    if "admin.organizations" in role_set:
        return PermissionTier.ADMIN
    elif "organization.manage" in role_set:
        return PermissionTier.MANAGER
    elif "organization.write" in role_set:
        return PermissionTier.WRITER
    else:
        return PermissionTier.READER
