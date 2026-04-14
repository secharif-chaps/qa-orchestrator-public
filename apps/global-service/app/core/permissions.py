"""Permission tier definitions and role mappings.

This module defines the permission tier system for team management,
abstracting away individual Keycloak roles into a simple 3-tier model.
"""

from enum import StrEnum


class PermissionTier(StrEnum):
    """Permission tiers for team members."""

    NO_ACCESS = "no_access"
    READER = "reader"
    WRITER = "writer"
    MANAGER = "manager"
    ADMIN = "admin"


# Role mappings for each tier (cumulative)
TIER_ROLE_MAPPING: dict[PermissionTier, list[str]] = {
    PermissionTier.NO_ACCESS: [],
    PermissionTier.READER: [
        "organization.read",
    ],
    PermissionTier.WRITER: [
        "organization.read",
        "organization.write",
        "company.create",
    ],
    PermissionTier.MANAGER: [
        "organization.read",
        "organization.write",
        "organization.manage",
        "company.create",
    ],
    PermissionTier.ADMIN: [
        "organization.read",
        "organization.write",
        "organization.manage",
        "company.create",
        "admin.organizations",
    ],
}


def get_roles_for_tier(tier: PermissionTier) -> list[str]:
    """Get all roles for a permission tier."""
    return TIER_ROLE_MAPPING[tier].copy()


LEGACY_ROLES = {"company.view", "company.delete", "screen.create", "target.create"}


def get_tier_from_roles(roles: list[str]) -> PermissionTier:
    """Determine permission tier from user's roles.

    Returns highest matching tier based on role presence.
    Special case: Users with 'admin.organizations' role get ADMIN tier.
    Legacy safety: Users with old roles (company.view, company.delete, etc.)
    but missing organization.read are treated as READER, not NO_ACCESS.
    """
    role_set = set(roles)

    # Check from highest to lowest tier
    if "admin.organizations" in role_set:
        return PermissionTier.ADMIN
    elif "organization.manage" in role_set:
        return PermissionTier.MANAGER
    elif "organization.write" in role_set:
        return PermissionTier.WRITER
    elif "organization.read" in role_set:
        return PermissionTier.READER
    elif role_set & LEGACY_ROLES:
        # Users with legacy roles should be at least READER
        return PermissionTier.READER
    else:
        return PermissionTier.NO_ACCESS
