"""Pydantic schemas for data source configuration."""

from typing import Optional

from pydantic import BaseModel


class DataSourceConfigRequest(BaseModel):
    """Request to update data source configuration."""
    api_key: Optional[str] = None
    api_secret: Optional[str] = None


class DataSourceConfigResponse(BaseModel):
    """Response with data source configuration."""
    source: str
    enabled: bool
    api_key_masked: Optional[str] = None
    api_secret_masked: Optional[str] = None
    enabled_at: Optional[str] = None
    updated_at: Optional[str] = None


class DataSourceInfo(BaseModel):
    """Information about an available data source."""
    source: str
    name: str
    description: str
