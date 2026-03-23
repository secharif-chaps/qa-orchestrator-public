"""Pydantic schemas for organization credit statistics.

This module contains schemas for:
- Credit usage statistics and breakdown by module
- Top credit users leaderboard
- Daily credit usage time series
"""

from pydantic import BaseModel, Field


class ModuleUsageItem(BaseModel):
    """Credit usage breakdown for a single module."""

    module: str = Field(..., description="Module identifier (screen, target, explore)")
    label: str = Field(..., description="Human-readable module label")
    credits_consumed: int = Field(..., description="Total credits consumed by this module")
    percentage: float = Field(..., description="Percentage of total consumption (0-100)")


class ModuleForecastItem(BaseModel):
    """Remaining capacity forecast for a single module."""

    module: str = Field(..., description="Module identifier (screen, target, explore)")
    label: str = Field(..., description="Human-readable module label")
    icon: str = Field(..., description="FontAwesome icon class")
    cost: int | None = Field(None, description="Credits per item (None if disabled)")
    remaining_count: int | None = Field(
        None, description="Number of items that can still be created (None if disabled)"
    )
    enabled: bool = Field(..., description="Whether the module is enabled for this organization")
    item_label: str = Field(..., description="Singular item name")
    item_label_plural: str = Field(..., description="Plural item name")


class CreditStatsResponse(BaseModel):
    """Response schema for organization credit statistics."""

    balance: int = Field(..., ge=0, description="Current credit balance")
    usage_by_module: list[ModuleUsageItem] = Field(
        default_factory=list, description="Credit consumption breakdown by module"
    )
    remaining_capacity: list[ModuleForecastItem] = Field(
        default_factory=list, description="Remaining capacity forecast per module"
    )


class TopCreditUser(BaseModel):
    """A single user in the top credit users leaderboard."""

    rank: int = Field(..., description="Position in the leaderboard (1-indexed)")
    user_id: str = Field(..., description="Keycloak user UUID")
    username: str = Field(..., description="Username")
    full_name: str = Field(..., description="User's full name")
    email: str = Field(..., description="User's email address")
    initials: str = Field(..., description="User initials for avatar")
    credits_consumed: int = Field(..., description="Total credits consumed by this user")


class TopCreditUsersResponse(BaseModel):
    """Paginated response for top credit users."""

    items: list[TopCreditUser] = Field(default_factory=list, description="List of top users")
    total: int = Field(..., description="Total number of users with consumption")
    page: int = Field(..., description="Current page number (1-indexed)")
    size: int = Field(..., description="Items per page")


class DailyUsageItem(BaseModel):
    """Credit usage for a single day."""

    date: str = Field(..., description="Date in YYYY-MM-DD format")
    credits_consumed: int = Field(..., description="Total credits consumed on this day")


class DailyCreditUsageResponse(BaseModel):
    """Response for daily credit usage time series."""

    daily_usage: list[DailyUsageItem] = Field(default_factory=list, description="Daily credit consumption data points")
