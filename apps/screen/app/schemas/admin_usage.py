"""Pydantic schemas for usage dashboard admin endpoints.

This module provides schemas for the usage statistics dashboard that displays
company creation trends, task success rates, and organization breakdowns.
"""

from pydantic import BaseModel, ConfigDict, Field


class TimeSeriesDataPoint(BaseModel):
    """A single data point in a time series chart.

    Attributes:
        period: ISO format date string representing the time period (day/week/month)
        count: Number of items (companies) in this period
    """

    period: str = Field(..., description="ISO format date string for the time period")
    count: int = Field(..., ge=0, description="Count of items in this period")


class OrganizationBreakdown(BaseModel):
    """Company count breakdown for a single organization.

    Attributes:
        organization_id: Keycloak organization UUID
        organization_name: Human-readable organization name from Keycloak
        companies_count: Number of companies created by this organization
        percentage: Percentage of total companies (0-100, one decimal precision)
    """

    organization_id: str = Field(..., description="Keycloak organization UUID")
    organization_name: str = Field(..., description="Organization name from Keycloak")
    companies_count: int = Field(..., ge=0, description="Number of companies created")
    percentage: float = Field(..., ge=0, le=100, description="Percentage of total companies")


class UsageStatsResponse(BaseModel):
    """Complete usage statistics response for the admin dashboard.

    This response aggregates data for a given time period and includes:
    - Total counts for KPI cards
    - Time series data for charts
    - Organization breakdown for tables

    Attributes:
        companies_count: Total number of companies created in the period
        task_success_rate: Percentage of successful tasks (0-100, one decimal)
        active_users_count: Number of unique users who created companies
        companies_over_time: Time series data for the line chart
        companies_by_organization: Organization breakdown for table/stacked chart
    """

    companies_count: int = Field(..., ge=0, description="Total companies created in period")
    task_success_rate: float | None = Field(
        None,
        ge=0,
        le=100,
        description="Percentage of successful tasks (None if no tasks)",
    )
    active_users_count: int = Field(..., ge=0, description="Unique users who created companies")
    companies_over_time: list[TimeSeriesDataPoint] = Field(
        default_factory=list, description="Time series data for line chart"
    )
    companies_by_organization: list[OrganizationBreakdown] = Field(
        default_factory=list, description="Organization breakdown for table"
    )

    model_config = ConfigDict(from_attributes=True)
