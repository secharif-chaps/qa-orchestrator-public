"""Pydantic schemas for data source configuration."""

from pydantic import BaseModel


class DataSourceConfigRequest(BaseModel):
    """Request to update data source configuration."""

    api_key: str | None = None
    api_secret: str | None = None


class DataSourceConfigResponse(BaseModel):
    """Response with data source configuration."""

    source: str
    enabled: bool
    api_key_masked: str | None = None
    api_secret_masked: str | None = None
    enabled_at: str | None = None
    updated_at: str | None = None


class DataSourceInfo(BaseModel):
    """Information about an available data source."""

    source: str
    name: str
    description: str
