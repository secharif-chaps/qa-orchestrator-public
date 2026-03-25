"""Pydantic response schemas for cost analysis endpoints."""


from pydantic import BaseModel, Field


class PeriodRange(BaseModel):
    """Date range for cost analysis."""

    start_date: str = Field(..., description="Start date in ISO format")
    end_date: str = Field(..., description="End date in ISO format")


class GlobalSummary(BaseModel):
    """Global cost summary statistics."""

    total_tasks: int = 0
    total_companies: int = 0
    total_organizations: int = 0
    total_input_tokens: int = 0
    total_output_tokens: int = 0
    total_cost: float = 0.0
    avg_cost_per_task: float = 0.0
    avg_cost_per_company: float = 0.0


class GlobalCostResponse(BaseModel):
    """Response for global cost analysis endpoint."""

    period: PeriodRange
    global_summary: GlobalSummary


class OrganizationCostItem(BaseModel):
    """Cost breakdown for a single organization."""

    organization_id: str | None = None
    company_count: int = 0
    task_count: int = 0
    total_input_tokens: int = 0
    total_output_tokens: int = 0
    total_cost: float = 0.0
    avg_cost_per_task: float = 0.0
    avg_cost_per_company: float = 0.0


class OrganizationCostSummary(BaseModel):
    """Summary across all organizations."""

    total_organizations: int = 0
    total_cost: float = 0.0
    total_tasks: int = 0
    total_companies: int = 0
    avg_cost_per_organization: float = 0.0


class OrganizationCostResponse(BaseModel):
    """Response for cost by organization endpoint."""

    period: PeriodRange
    organizations: list[OrganizationCostItem]
    summary: OrganizationCostSummary


class TaskTypeCostItem(BaseModel):
    """Cost breakdown for a single task type."""

    task_type: str
    task_count: int = 0
    total_input_tokens: int = 0
    total_output_tokens: int = 0
    total_cost: float = 0.0
    avg_cost_per_task: float = 0.0
    avg_input_tokens: float = 0.0
    avg_output_tokens: float = 0.0


class TaskTypeCostSummary(BaseModel):
    """Summary across all task types."""

    total_task_types: int = 0
    total_cost: float = 0.0
    total_tasks: int = 0
    most_expensive_type: str | None = None
    most_frequent_type: str | None = None


class TaskTypeCostResponse(BaseModel):
    """Response for cost by task type endpoint."""

    period: PeriodRange
    organization_id: str | None = None
    task_types: list[TaskTypeCostItem]
    summary: TaskTypeCostSummary


class TrendDataPoint(BaseModel):
    """Single data point in cost trends."""

    period: str
    task_count: int = 0
    total_input_tokens: int = 0
    total_output_tokens: int = 0
    total_cost: float = 0.0


class TrendSummary(BaseModel):
    """Summary statistics for cost trends."""

    total_periods: int = 0
    total_cost: float = 0.0
    avg_cost_per_period: float = 0.0
    max_cost_period: TrendDataPoint | None = None
    min_cost_period: TrendDataPoint | None = None


class CostTrendsResponse(BaseModel):
    """Response for cost trends endpoint."""

    period: PeriodRange
    granularity: str
    organization_id: str | None = None
    trends: list[TrendDataPoint]
    summary: TrendSummary


class RefreshMaterializedViewsResponse(BaseModel):
    """Response for refresh materialized views endpoint."""

    status: str
    message: str
    timestamp: str
