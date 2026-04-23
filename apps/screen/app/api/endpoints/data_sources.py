"""Data source configuration endpoints for organization data providers.

This module provides endpoints for managing external data source configurations
per organization, such as Pappers API keys and WorldCheck credentials.

Endpoints:
- PUT /organizations/{org_id}/data-sources/{source}/config - Update source config
- GET /organizations/{org_id}/data-sources/{source}/config - Get source config
"""

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.core.auth import AuthenticatedUser, get_current_user
from app.core.logging_config import get_logger
from app.database import get_db
from app.models.organization import FeatureFlag
from app.schemas.data_source import DataSourceConfigRequest, DataSourceConfigResponse
from app.services.feature_flags import get_feature_config, obfuscate_api_key, update_feature_config

logger = get_logger(__name__)

router = APIRouter(prefix="/organizations", tags=["data-sources"])

# Map of valid data sources to their FeatureFlag
VALID_DATA_SOURCES = {
    "pappers": FeatureFlag.PAPPERS,
    "worldcheck": FeatureFlag.WORLDCHECK,
    "epo": FeatureFlag.EPO,
}

# Data sources that require dual credentials (api_key + api_secret)
DUAL_CREDENTIAL_SOURCES = {"worldcheck", "epo"}


@router.put(
    "/{organization_id}/data-sources/{source}/config",
    response_model=DataSourceConfigResponse,
    openapi_extra={"x-permissions": ["admin.organizations"]},
)
async def update_data_source_config(
    organization_id: str,
    source: str,
    request: DataSourceConfigRequest,
    db: Session = Depends(get_db),
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["admin.organizations"])),
) -> DataSourceConfigResponse:
    """Update data source configuration for an organization.

    Sets or updates the API credentials for the specified data source.
    Automatically enables the feature flag when an API key is set,
    and disables it when the API key is removed.

    For dual-credential sources (e.g., WorldCheck), both api_key and
    api_secret are stored encrypted.

    Requires admin.organizations role.

    Args:
        organization_id: Keycloak organization UUID
        source: Data source identifier (e.g., 'pappers', 'worldcheck')
        request: Configuration request with credentials

    Returns:
        DataSourceConfigResponse with obfuscated credentials
    """
    # Validate source
    if source not in VALID_DATA_SOURCES:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid data source: {source}. Valid sources: {list(VALID_DATA_SOURCES.keys())}",
        )

    flag = VALID_DATA_SOURCES[source]

    logger.info(
        "Updating data source config",
        extra={
            "organization_id": organization_id,
            "source": source,
            "user": user.preferred_username,
            "has_api_key": bool(request.api_key),
            "has_api_secret": bool(request.api_secret),
        },
    )

    # Build config from request
    config: dict[str, str] = {"api_key": request.api_key if request.api_key else ""}
    if source in DUAL_CREDENTIAL_SOURCES:
        config["api_secret"] = request.api_secret if request.api_secret else ""

    result = update_feature_config(
        db=db,
        organization_id=organization_id,
        flag=flag,
        config=config,
        updated_by=user.sub,
    )

    # Decrypt config for obfuscated response
    decrypted_config = get_feature_config(db, organization_id, flag)

    return _build_response(source, result, decrypted_config)


@router.get(
    "/{organization_id}/data-sources/{source}/config",
    response_model=DataSourceConfigResponse,
    openapi_extra={"x-permissions": ["admin.organizations"]},
)
async def get_data_source_config(
    organization_id: str,
    source: str,
    db: Session = Depends(get_db),
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["admin.organizations"])),
) -> DataSourceConfigResponse:
    """Get data source configuration for an organization.

    Returns the current configuration with obfuscated credentials.

    Requires admin.organizations role.

    Args:
        organization_id: Keycloak organization UUID
        source: Data source identifier (e.g., 'pappers', 'worldcheck')

    Returns:
        DataSourceConfigResponse with obfuscated credentials
    """
    # Validate source
    if source not in VALID_DATA_SOURCES:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid data source: {source}. Valid sources: {list(VALID_DATA_SOURCES.keys())}",
        )

    flag = VALID_DATA_SOURCES[source]

    # Get feature config (decrypted)
    config = get_feature_config(db, organization_id, flag)

    # Get the feature flag record for enabled status
    from app.models.organization import OrganizationFeatureFlag

    feature = (
        db.query(OrganizationFeatureFlag)
        .filter(
            OrganizationFeatureFlag.organization_id == organization_id,
            OrganizationFeatureFlag.flag == flag,
        )
        .first()
    )

    if not feature:
        return DataSourceConfigResponse(
            source=source,
            enabled=False,
            api_key_masked=None,
            api_secret_masked=None,
            enabled_at=None,
            updated_at=None,
        )

    return _build_response(source, feature, config)


def _build_response(
    source: str,
    feature: object,
    config: dict | None,
) -> DataSourceConfigResponse:
    """Build DataSourceConfigResponse with obfuscated credentials.

    Args:
        source: Data source identifier
        feature: OrganizationFeatureFlag record
        config: Decrypted config dict, or None

    Returns:
        DataSourceConfigResponse with masked credentials
    """
    api_key_masked = None
    api_secret_masked = None

    if config:
        if config.get("api_key"):
            api_key_masked = obfuscate_api_key(config["api_key"])
        if config.get("api_secret"):
            api_secret_masked = obfuscate_api_key(config["api_secret"])

    return DataSourceConfigResponse(
        source=source,
        enabled=feature.enabled,
        api_key_masked=api_key_masked,
        api_secret_masked=api_secret_masked if source in DUAL_CREDENTIAL_SOURCES else None,
        enabled_at=feature.enabled_at.isoformat() if feature.enabled_at else None,
        updated_at=feature.updated_at.isoformat() if feature.updated_at else None,
    )
