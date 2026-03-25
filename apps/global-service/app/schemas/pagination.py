from enum import StrEnum
from typing import TypeVar

from pydantic import BaseModel, Field

# Generic type for paginated data
T = TypeVar('T')

class SortOrder(StrEnum):
    ASC = "asc"
    DESC = "desc"

class PaginationMeta(BaseModel):
    """Pagination metadata"""
    total: int = Field(..., description="Total number of items")
    per_page: int = Field(..., description="Items per page")
    current_page: int = Field(..., description="Current page number")
    last_page: int = Field(..., description="Last page number")
    from_: int = Field(..., alias="from", description="First item number on current page")
    to: int = Field(..., description="Last item number on current page")

class PaginatedResponse[T](BaseModel):
    """Generic paginated response wrapper"""
    data: list[T] = Field(..., description="List of items")
    meta: PaginationMeta = Field(..., description="Pagination metadata")

class PaginationParams(BaseModel):
    """Query parameters for pagination"""
    page: int = Field(default=1, ge=1, description="Page number (starting from 1)")
    per_page: int = Field(default=10, ge=1, le=100, description="Items per page (max 100)")
    sort: str | None = Field(default=None, description="Field to sort by")
    order: SortOrder = Field(default=SortOrder.DESC, description="Sort order")

    def get_offset(self) -> int:
        """Calculate offset for database query"""
        return (self.page - 1) * self.per_page

    def get_limit(self) -> int:
        """Get limit for database query"""
        return self.per_page

def create_pagination_meta(
    total: int,
    page: int,
    per_page: int
) -> PaginationMeta:
    """Create pagination metadata"""
    last_page = (total + per_page - 1) // per_page if total > 0 else 1
    from_value = (page - 1) * per_page + 1 if total > 0 else 0
    to = min(page * per_page, total)
    
    return PaginationMeta(
        total=total,
        per_page=per_page,
        current_page=page,
        last_page=last_page,
        **{"from": from_value},  # Use the alias name
        to=to
    )