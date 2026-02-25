"""Permission tier definitions and role mappings.

This module defines the permission tier system for team management,
abstracting away individual Keycloak roles into a simple 3-tier model.
"""

from enum import Enum


class PermissionTier(str, Enum):
    """Permission tiers for team members."""

    READER = "reader"
    WRITER = "writer"
    MANAGER = "manager"


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
}


def get_roles_for_tier(tier: PermissionTier) -> list[str]:
    """Get all roles for a permission tier."""
    return TIER_ROLE_MAPPING[tier].copy()


def get_tier_from_roles(roles: list[str]) -> PermissionTier:
    """Determine permission tier from user's roles.

    Returns highest matching tier based on role presence.
    Special case: Users with 'admin.organizations' role automatically get MANAGER tier.
    """
    role_set = set(roles)

    if "admin.organizations" in role_set or "organization.manage" in role_set:
        return PermissionTier.MANAGER
    elif "organization.write" in role_set:
        return PermissionTier.WRITER
    else:
        return PermissionTier.READER
