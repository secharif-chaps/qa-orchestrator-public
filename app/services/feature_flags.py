"""Feature flag service for organization capabilities.

This module provides functions for checking and managing feature flags
per organization. Feature flags are add-on capabilities that enhance
core modules but are OFF by default.

Example usage:
    from app.services.feature_flags import has_feature, enable_feature

    # Check if translation is enabled for an organization
    if has_feature(db, org_id, FeatureFlag.TRANSLATION):
        # Do translation work
        pass

    # Enable translation for an organization
    enable_feature(db, org_id, FeatureFlag.TRANSLATION, enabled_by="admin-user-id")
"""

from datetime import datetime, timezone
from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.models.organization import FeatureFlag, OrganizationFeatureFlag

logger = get_logger(__name__)


def has_feature(db: Session, organization_id: str, flag: FeatureFlag) -> bool:
    """Check if a feature flag is enabled for an organization.

    Feature flags are OFF by default. This function returns True only if
    an explicit record exists with enabled=True.

    Args:
        db: Database session
        organization_id: Keycloak organization UUID
        flag: The feature flag to check

    Returns:
        True if the feature is enabled, False otherwise
    """
    feature = db.query(OrganizationFeatureFlag).filter(
        OrganizationFeatureFlag.organization_id == organization_id,
        OrganizationFeatureFlag.flag == flag,
        OrganizationFeatureFlag.enabled == True,  # noqa: E712
    ).first()

    return feature is not None


def get_feature_config(
    db: Session, organization_id: str, flag: FeatureFlag
) -> dict | None:
    """Get the configuration for a feature flag.

    Args:
        db: Database session
        organization_id: Keycloak organization UUID
        flag: The feature flag to get config for

    Returns:
        The config dict if feature exists and has config, None otherwise
    """
    feature = db.query(OrganizationFeatureFlag).filter(
        OrganizationFeatureFlag.organization_id == organization_id,
        OrganizationFeatureFlag.flag == flag,
    ).first()

    if feature and feature.config:
        return feature.config
    return None


def enable_feature(
    db: Session,
    organization_id: str,
    flag: FeatureFlag,
    enabled_by: str,
    config: dict | None = None,
) -> OrganizationFeatureFlag:
    """Enable a feature flag for an organization.

    Creates or updates the feature flag record with enabled=True.

    Args:
        db: Database session
        organization_id: Keycloak organization UUID
        flag: The feature flag to enable
        enabled_by: User ID who enabled the feature (for audit)
        config: Optional configuration for the feature

    Returns:
        The created or updated OrganizationFeatureFlag record
    """
    feature = db.query(OrganizationFeatureFlag).filter(
        OrganizationFeatureFlag.organization_id == organization_id,
        OrganizationFeatureFlag.flag == flag,
    ).first()

    if feature:
        feature.enabled = True
        feature.enabled_at = datetime.now(timezone.utc)
        if config is not None:
            feature.config = config
        logger.info(
            "Feature flag enabled",
            extra={
                "organization_id": organization_id,
                "flag": flag.value,
                "enabled_by": enabled_by,
            },
        )
    else:
        feature = OrganizationFeatureFlag(
            organization_id=organization_id,
            flag=flag,
            enabled=True,
            enabled_at=datetime.now(timezone.utc),
            config=config,
        )
        db.add(feature)
        logger.info(
            "Feature flag created and enabled",
            extra={
                "organization_id": organization_id,
                "flag": flag.value,
                "enabled_by": enabled_by,
            },
        )

    db.commit()
    db.refresh(feature)
    return feature


def disable_feature(
    db: Session,
    organization_id: str,
    flag: FeatureFlag,
    disabled_by: str,
) -> OrganizationFeatureFlag | None:
    """Disable a feature flag for an organization.

    Updates the feature flag record with enabled=False.

    Args:
        db: Database session
        organization_id: Keycloak organization UUID
        flag: The feature flag to disable
        disabled_by: User ID who disabled the feature (for audit)

    Returns:
        The updated OrganizationFeatureFlag record, or None if not found
    """
    feature = db.query(OrganizationFeatureFlag).filter(
        OrganizationFeatureFlag.organization_id == organization_id,
        OrganizationFeatureFlag.flag == flag,
    ).first()

    if feature:
        feature.enabled = False
        feature.enabled_at = None
        db.commit()
        db.refresh(feature)
        logger.info(
            "Feature flag disabled",
            extra={
                "organization_id": organization_id,
                "flag": flag.value,
                "disabled_by": disabled_by,
            },
        )

    return feature


def get_organization_features(
    db: Session, organization_id: str
) -> list[OrganizationFeatureFlag]:
    """Get all feature flags for an organization.

    Args:
        db: Database session
        organization_id: Keycloak organization UUID

    Returns:
        List of OrganizationFeatureFlag records for the organization
    """
    return db.query(OrganizationFeatureFlag).filter(
        OrganizationFeatureFlag.organization_id == organization_id,
    ).all()


def get_enabled_features(db: Session, organization_id: str) -> list[FeatureFlag]:
    """Get list of enabled feature flags for an organization.

    Args:
        db: Database session
        organization_id: Keycloak organization UUID

    Returns:
        List of FeatureFlag enums that are enabled for the organization
    """
    features = db.query(OrganizationFeatureFlag.flag).filter(
        OrganizationFeatureFlag.organization_id == organization_id,
        OrganizationFeatureFlag.enabled == True,  # noqa: E712
    ).all()

    return [f[0] for f in features]
