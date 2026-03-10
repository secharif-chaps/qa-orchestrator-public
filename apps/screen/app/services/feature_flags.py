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

from app.core.encryption import decrypt, encrypt
from app.core.logging_config import get_logger
from app.models.organization import FeatureFlag, OrganizationFeatureFlag

logger = get_logger(__name__)


def obfuscate_api_key(api_key: str | None) -> str | None:
    """Obfuscate an API key for safe display.

    Adapts masking based on key length:
    - None or empty: returns None
    - 1-4 chars: fully masked (****)
    - 5-8 chars: shows first 1 + last 1 (a...z)
    - 9-16 chars: shows first 2 + last 2 (ab...yz)
    - 17+ chars: shows first 4 + last 4 (abcd...wxyz)

    Args:
        api_key: The API key to obfuscate

    Returns:
        Obfuscated API key string, or None if input is None/empty
    """
    if not api_key:
        return None

    length = len(api_key)

    if length <= 4:
        # Very short: fully masked
        return "*" * length

    if length <= 8:
        # Short: show 1 + 1
        return f"{api_key[0]}...{api_key[-1]}"

    if length <= 16:
        # Medium: show 2 + 2
        return f"{api_key[:2]}...{api_key[-2:]}"

    # Long: show 4 + 4
    return f"{api_key[:4]}...{api_key[-4:]}"


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

    API keys are automatically decrypted before being returned.

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
        config = feature.config.copy()
        # Decrypt encrypted credential fields (api_key, api_secret)
        for field in ("api_key", "api_secret"):
            if config.get(field):
                try:
                    config[field] = decrypt(config[field])
                except Exception as e:
                    logger.error(
                        f"Failed to decrypt {field}",
                        extra={
                            "organization_id": organization_id,
                            "flag": flag.value,
                            "error": str(e),
                        },
                    )
                    config[field] = None
        return config
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
    If config is provided, it is merged with existing config to preserve api_key.
    API keys are automatically encrypted before storage.

    Args:
        db: Database session
        organization_id: Keycloak organization UUID
        flag: The feature flag to enable
        enabled_by: User ID who enabled the feature (for audit)
        config: Optional configuration to merge with existing config

    Returns:
        The created or updated OrganizationFeatureFlag record
    """
    feature = db.query(OrganizationFeatureFlag).filter(
        OrganizationFeatureFlag.organization_id == organization_id,
        OrganizationFeatureFlag.flag == flag,
    ).first()

    # Encrypt credential fields if present in config
    if config:
        config = config.copy()
        for field in ("api_key", "api_secret"):
            if config.get(field):
                config[field] = encrypt(config[field])

    if feature:
        feature.enabled = True
        feature.enabled_at = datetime.now(timezone.utc)
        if config is not None:
            # Merge new config with existing config to preserve api_key
            existing_config = feature.config or {}
            merged_config = {**existing_config, **config}
            feature.config = merged_config
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


def update_feature_config(
    db: Session,
    organization_id: str,
    flag: FeatureFlag,
    config: dict[str, str | None],
    updated_by: str,
) -> OrganizationFeatureFlag:
    """Update feature flag config without changing enabled state.

    Auto-enables flag if config contains valid api_key (>= 9 chars).
    Auto-disables flag if config api_key is removed/empty/too short.
    API keys are automatically encrypted before storage.

    Args:
        db: Database session
        organization_id: Keycloak organization UUID
        flag: The feature flag to update
        config: New configuration dict
        updated_by: User ID who updated the config (for audit)

    Returns:
        The created or updated OrganizationFeatureFlag record
    """
    feature = db.query(OrganizationFeatureFlag).filter(
        OrganizationFeatureFlag.organization_id == organization_id,
        OrganizationFeatureFlag.flag == flag,
    ).first()

    # Determine if we should auto-enable/disable based on api_key
    api_key = config.get("api_key")
    has_api_key = api_key is not None and len(api_key.strip()) > 0

    # Encrypt or clear credential fields (api_key, api_secret)
    config = config.copy()
    if has_api_key:
        config["api_key"] = encrypt(api_key)
    elif api_key is not None:
        config["api_key"] = ""

    api_secret = config.get("api_secret")
    if api_secret is not None:
        if len(api_secret.strip()) > 0:
            config["api_secret"] = encrypt(api_secret)
        else:
            config["api_secret"] = ""

    if feature:
        # Merge new config with existing config
        existing_config = feature.config or {}
        merged_config = {**existing_config, **config}
        feature.config = merged_config

        # Auto-enable if api_key is valid, auto-disable if invalid/removed
        if has_api_key and not feature.enabled:
            feature.enabled = True
            feature.enabled_at = datetime.now(timezone.utc)
        elif not has_api_key and feature.enabled:
            feature.enabled = False
            feature.enabled_at = None

        logger.info(
            "Feature flag config updated",
            extra={
                "organization_id": organization_id,
                "flag": flag.value,
                "updated_by": updated_by,
                "enabled": feature.enabled,
                "has_api_key": has_api_key,
            },
        )
    else:
        # Create new record
        feature = OrganizationFeatureFlag(
            organization_id=organization_id,
            flag=flag,
            enabled=has_api_key,
            enabled_at=datetime.now(timezone.utc) if has_api_key else None,
            config=config,
        )
        db.add(feature)
        logger.info(
            "Feature flag created with config",
            extra={
                "organization_id": organization_id,
                "flag": flag.value,
                "updated_by": updated_by,
                "enabled": feature.enabled,
                "has_api_key": has_api_key,
            },
        )

    db.commit()
    db.refresh(feature)
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
