"""Data source configuration endpoints for organization data providers.

This module provides endpoints for managing external data source configurations
per organization, such as Pappers API keys.

Endpoints:
- PUT /organizations/{org_id}/data-sources/{source}/config - Update source config
"""

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi_keycloak import OIDCUser
from sqlalchemy.orm import Session

from app.core.keycloak import idp
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
}


@router.put("/{organization_id}/data-sources/{source}/config", response_model=DataSourceConfigResponse)
async def update_data_source_config(
    organization_id: str,
    source: str,
    request: DataSourceConfigRequest,
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Update data source configuration for an organization.

    Sets or updates the API key for the specified data source.
    Automatically enables the feature flag when an API key is set,
    and disables it when the API key is removed.

    Requires admin.organizations role.

    Args:
        organization_id: Keycloak organization UUID
        source: Data source identifier (e.g., 'pappers')
        request: Configuration request with api_key

    Returns:
        DataSourceConfigResponse with obfuscated API key
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
        },
    )

    # Update feature config
    config = {"api_key": request.api_key} if request.api_key else {"api_key": ""}
    result = update_feature_config(
        db=db,
        organization_id=organization_id,
        flag=flag,
        config=config,
        updated_by=user.sub,
    )

    # Get obfuscated API key for response
    api_key_masked = None
    if result.config and result.config.get("api_key"):
        api_key_masked = obfuscate_api_key(result.config.get("api_key"))

    return DataSourceConfigResponse(
        source=source,
        enabled=result.enabled,
        api_key_masked=api_key_masked,
        enabled_at=result.enabled_at.isoformat() if result.enabled_at else None,
        updated_at=result.updated_at.isoformat() if result.updated_at else None,
    )


@router.get("/{organization_id}/data-sources/{source}/config", response_model=DataSourceConfigResponse)
async def get_data_source_config(
    organization_id: str,
    source: str,
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Get data source configuration for an organization.

    Returns the current configuration with obfuscated API key.

    Requires admin.organizations role.

    Args:
        organization_id: Keycloak organization UUID
        source: Data source identifier (e.g., 'pappers')

    Returns:
        DataSourceConfigResponse with obfuscated API key
    """
    # Validate source
    if source not in VALID_DATA_SOURCES:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid data source: {source}. Valid sources: {list(VALID_DATA_SOURCES.keys())}",
        )

    flag = VALID_DATA_SOURCES[source]

    # Get feature config
    config = get_feature_config(db, organization_id, flag)

    # Get the feature flag record for enabled status
    from app.models.organization import OrganizationFeatureFlag

    feature = db.query(OrganizationFeatureFlag).filter(
        OrganizationFeatureFlag.organization_id == organization_id,
        OrganizationFeatureFlag.flag == flag,
    ).first()

    if not feature:
        return DataSourceConfigResponse(
            source=source,
            enabled=False,
            api_key_masked=None,
            enabled_at=None,
            updated_at=None,
        )

    # Get obfuscated API key for response
    api_key_masked = None
    if config and config.get("api_key"):
        api_key_masked = obfuscate_api_key(config.get("api_key"))

    return DataSourceConfigResponse(
        source=source,
        enabled=feature.enabled,
        api_key_masked=api_key_masked,
        enabled_at=feature.enabled_at.isoformat() if feature.enabled_at else None,
        updated_at=feature.updated_at.isoformat() if feature.updated_at else None,
    )
