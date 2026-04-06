import json
import re
from datetime import datetime
from typing import Any

from pydantic import BaseModel, ConfigDict, Field, HttpUrl, field_validator

from app.models.task import TaskStatus, TaskType


class CompanyBase(BaseModel):
    name: str = Field(..., min_length=2, max_length=100, description="Company name")
    website: HttpUrl = Field(..., description="Company website URL")

    @field_validator("name")
    @classmethod
    def validate_name(cls, v: str) -> str:
        """Validate and sanitize company name.

        Args:
            v: Company name to validate

        Returns:
            Validated company name

        Raises:
            ValueError: If name is invalid
        """
        if not v or not v.strip():
            raise ValueError("Company name cannot be empty or only whitespace")

        # Trim whitespace
        sanitized = v.strip()

        # Check minimum length
        if len(sanitized) < 2:
            raise ValueError("Company name must be at least 2 characters long")

        # Check maximum length
        if len(sanitized) > 100:
            raise ValueError("Company name too long (max 100 characters)")

        # Company name cannot be only numbers
        if sanitized.isdigit():
            raise ValueError("Company name cannot be only numbers")

        # Check for too many repeated characters (potential spam)
        if re.search(r"(.)\1{5,}", sanitized):
            raise ValueError("Company name contains too many repeated characters")

        # Allow Unicode letters, digits, spaces, and common punctuation used in business names
        # Also explicitly allow apostrophes and accented characters by checking Unicode categories
        import unicodedata

        allowed_punct = set("-_.&()'")

        for ch in sanitized:
            if ch.isspace() or ch.isdigit():
                continue
            cat = unicodedata.category(ch)
            # Categories starting with 'L' are letters (including accented letters)
            if cat.startswith("L"):
                continue
            if ch in allowed_punct:
                continue
            raise ValueError(
                """Company name contains invalid characters. Only letters, numbers, spaces, and - _ . & ( ) \'' are allowed"""
            )

        # Additional checks to prevent obvious SQL/XSS injection patterns
        lower = sanitized.lower()
        if any(x in lower for x in ["javascript:", "data:", "<", ">"]):
            raise ValueError("Company name contains invalid or suspicious content")
        if ";" in sanitized or "--" in sanitized:
            raise ValueError("Company name contains invalid or suspicious content")
        # Common SQL injection patterns
        import re as _re

        if _re.search(r"'\s*or\b", lower) or " or 1=1" in lower or "union select" in lower:
            raise ValueError("Company name contains invalid or suspicious content")

        return sanitized

    @field_validator("website")
    @classmethod
    def validate_website(cls, v: HttpUrl) -> str:
        """Validate website URL.

        Args:
            v: Website URL to validate (already validated by HttpUrl type)

        Returns:
            Validated website URL as string

        Raises:
            ValueError: If URL is invalid
        """
        # HttpUrl from Pydantic already validates:
        # - Proper URL format
        # - Valid scheme (http/https)
        # - Valid domain

        url_str = str(v)

        # Additional security checks for suspicious patterns
        url_lower = url_str.lower()
        suspicious_patterns = ["javascript:", "data:", "file:", "ftp:", "localhost", "127.0.0.1", "0.0.0.0"]

        for pattern in suspicious_patterns:
            if pattern in url_lower:
                raise ValueError(f"URL contains suspicious content: {pattern}")

        return url_str


class CompanyCreate(CompanyBase):
    pass


class CompanyUpdate(BaseModel):
    name: str | None = Field(None, min_length=2, max_length=100, description="Company name")
    website: HttpUrl | None = Field(None, description="Company website URL")
    profile: dict[str, Any] | None = Field(None, description="Company profile data")
    digital: dict[str, Any] | None = Field(None, description="Digital presence data")
    timeline: dict[str, Any] | None = Field(None, description="Company timeline data")
    products: dict[str, Any] | None = Field(None, description="Products data")
    jobs: dict[str, Any] | None = Field(None, description="Jobs data")
    csr: dict[str, Any] | None = Field(None, description="CSR data")
    press: dict[str, Any] | None = Field(None, description="Press data")
    team: list[dict[str, Any]] | None = Field(None, description="Team data")

    @field_validator("name")
    @classmethod
    def validate_name(cls, v: str | None) -> str | None:
        """Validate and sanitize company name."""
        if v is None:
            return v

        if not v or not v.strip():
            raise ValueError("Company name cannot be empty or only whitespace")

        sanitized = v.strip()

        if len(sanitized) < 2:
            raise ValueError("Company name must be at least 2 characters long")

        if len(sanitized) > 100:
            raise ValueError("Company name too long (max 100 characters)")

        if sanitized.isdigit():
            raise ValueError("Company name cannot be only numbers")

        if re.search(r"(.)\1{5,}", sanitized):
            raise ValueError("Company name contains too many repeated characters")

        # Allow Unicode letters, digits, spaces, and common punctuation used in business names
        # Also explicitly allow apostrophes and accented characters by checking Unicode categories
        import unicodedata

        allowed_punct = set("-_.&()'")

        for ch in sanitized:
            if ch.isspace() or ch.isdigit():
                continue
            cat = unicodedata.category(ch)
            if cat.startswith("L"):
                continue
            if ch in allowed_punct:
                continue
            raise ValueError("Company name contains invalid characters")

        # Additional checks to prevent obvious SQL/XSS injection patterns
        lower = sanitized.lower()
        if any(x in lower for x in ["javascript:", "data:", "<", ">"]):
            raise ValueError("Company name contains invalid or suspicious content")
        if ";" in sanitized or "--" in sanitized:
            raise ValueError("Company name contains invalid or suspicious content")
        import re as _re

        if _re.search(r"'\s*or\b", lower) or " or 1=1" in lower or "union select" in lower:
            raise ValueError("Company name contains invalid or suspicious content")

        return sanitized

    @field_validator("website")
    @classmethod
    def validate_website(cls, v: HttpUrl | None) -> str | None:
        """Validate website URL."""
        if v is None:
            return v

        url_str = str(v)
        url_lower = url_str.lower()

        suspicious_patterns = ["javascript:", "data:", "file:", "ftp:", "localhost", "127.0.0.1", "0.0.0.0"]

        for pattern in suspicious_patterns:
            if pattern in url_lower:
                raise ValueError(f"URL contains suspicious content: {pattern}")

        return url_str

    @field_validator("profile", "digital", "timeline", "products", "jobs", "csr", "press", "team")
    @classmethod
    def validate_json_fields(cls, v: Any, info) -> Any:
        """Validate JSON field data to prevent attacks.

        Args:
            v: JSON data to validate
            info: Field validation info

        Returns:
            Validated data

        Raises:
            ValueError: If data is invalid
        """
        if v is None:
            return v

        max_depth = 10
        max_size = 10000  # characters

        # Check serialized size
        try:
            json_str = json.dumps(v)
        except (TypeError, ValueError):
            raise ValueError(f"Invalid JSON data in field: {info.field_name}")

        if len(json_str) > max_size:
            raise ValueError(f"Field {info.field_name} data too large (max {max_size} characters)")

        # Check nesting depth
        def check_depth(obj: Any, current_depth: int = 0) -> None:
            if current_depth > max_depth:
                raise ValueError(f"Field {info.field_name} has too many nested levels (max {max_depth})")

            if isinstance(obj, dict):
                if len(obj) > 100:
                    raise ValueError(f"Field {info.field_name} object has too many keys (max 100)")
                for value in obj.values():
                    check_depth(value, current_depth + 1)
            elif isinstance(obj, list):
                if len(obj) > 1000:
                    raise ValueError(f"Field {info.field_name} array too large (max 1000 items)")
                for item in obj:
                    check_depth(item, current_depth + 1)

        check_depth(v)
        return v


class TaskResponse(BaseModel):
    id: int
    company_id: int
    type: TaskType
    status: TaskStatus
    error: str | None = None
    input_tokens: int | None = None
    output_tokens: int | None = None
    total_cost: float | None = None
    created_at: datetime | None = None
    updated_at: datetime | None = None

    model_config = ConfigDict(from_attributes=True)


class CompanyResponse(CompanyBase):
    id: int
    owner_id: str | None = Field(None, description="Keycloak user UUID of the company owner")
    owner_username: str
    website: str  # Override to str since validator converts HttpUrl to str
    profile: dict[str, Any] = Field(default_factory=dict)
    digital: dict[str, Any] = Field(default_factory=dict)
    timeline: dict[str, Any] = Field(default_factory=dict)
    products: dict[str, Any] = Field(default_factory=dict)
    jobs: dict[str, Any] = Field(default_factory=dict)
    csr: dict[str, Any] = Field(default_factory=dict)
    press: dict[str, Any] = Field(default_factory=dict)
    financial: dict[str, Any] = Field(default_factory=dict)
    team: list[dict[str, Any]] = Field(default_factory=list)
    corporate_structure: dict[str, Any] = Field(default_factory=dict)
    sanctions: dict[str, Any] = Field(default_factory=dict)
    error: str | None = None
    is_deleted: bool = Field(default=False)
    created_at: datetime | None = None
    updated_at: datetime | None = None
    tasks: list[TaskResponse] = Field(default_factory=list)
    folder_id: str | None = Field(None, description="Primary folder ID (if company is in folders)")
    folder_name: str | None = Field(None, description="Primary folder name (if company is in folders)")

    model_config = ConfigDict(from_attributes=True)


class CompanyCSVRow(BaseModel):
    """Single row from CSV import"""

    row_number: int
    name: str
    website: str


class CompanyCSVValidationError(BaseModel):
    """Validation error for a specific row"""

    row_number: int
    field: str
    error: str


class CompanyCSVValidationRequest(BaseModel):
    """Request to validate CSV data"""

    companies: list[CompanyCSVRow]


class CompanyCSVValidationResponse(BaseModel):
    """Response from validation endpoint.

    Note: When tokens_available is None, token balance is unknown (validation
    skipped because global-service handles consumption). The import endpoint
    will return 402 if there are insufficient tokens.
    """

    valid_count: int
    error_count: int
    errors: list[CompanyCSVValidationError]
    has_sufficient_tokens: bool
    tokens_required: int
    tokens_available: int | None = None


class CompanyCSVImportRequest(BaseModel):
    """Request to import validated CSV data"""

    companies: list[CompanyCSVRow]
    skip_invalid: bool = True  # Whether to skip invalid rows or fail entire import


class CompanyCSVImportResult(BaseModel):
    """Result for a single company import"""

    row_number: int
    success: bool
    company_id: int | None = None
    name: str | None = None
    error: str | None = None


class CompanyCSVImportResponse(BaseModel):
    """Response from import endpoint"""

    total_rows: int
    successful: int
    failed: int
    results: list[CompanyCSVImportResult]
