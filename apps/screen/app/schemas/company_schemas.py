"""Pydantic schemas for normalized company data.

This module defines Pydantic schemas for the new normalized company data structure
with SourcedValue pattern support. These schemas are used for:
- Validating data from LangGraph agent output
- Serializing data for API responses
- Ensuring frontend compatibility

The SourcedValue[T] generic provides a consistent pattern for values with source
attribution. Translations are handled via the translations table.

Schemas follow the frontend TypeScript interfaces for compatibility while
supporting the normalized database structure.
"""

import datetime
from enum import StrEnum
from typing import Any, Generic, TypeVar

from pydantic import BaseModel, ConfigDict, Field, field_validator
from pydantic.alias_generators import to_camel

# Generic type for SourcedValue
T = TypeVar("T")


# ENUM definitions matching SQLAlchemy models


class ProductItemTypeEnum(StrEnum):
    """Product item type enum values."""

    range = "range"
    partner_brand = "partner_brand"
    private_label = "private_label"


class CsrInitiativeTypeEnum(StrEnum):
    """CSR initiative type enum values."""

    responsibility = "responsibility"
    charity = "charity"
    sustainability = "sustainability"
    community = "community"
    diversity = "diversity"
    ethics = "ethics"
    awards = "awards"


class PressItemTypeEnum(StrEnum):
    """Press item type enum values."""

    article = "article"
    press_release = "press_release"
    media_mention = "media_mention"
    award = "award"
    product_launch = "product_launch"
    interview = "interview"
    financial = "financial"
    partnership = "partnership"


# Core SourcedValue generic schema


class SourcedValue(BaseModel, Generic[T]):
    """Generic schema for values with source attribution.

    This schema represents a value that comes from a specific source
    (URL, tool name, or AI-generated "Chaps-e") with optional favicon.
    Translations are handled via the translations table and applied
    by modifying the value field when a language parameter is passed.

    Type Parameters:
        T: The type of the value field (str, int, list, etc.)

    Attributes:
        value: The actual value of type T
        source: Source URL or tool name (validated)
        favicon: Optional favicon URL for the source

    Examples:
        >>> sv = SourcedValue[str](value="LVMH", source="https://wikipedia.org/wiki/LVMH")
        >>> sv = SourcedValue[int](value=250, source="https://careers.company.com")
        >>> sv = SourcedValue[str](value="AI-generated insight", source="Chaps-e")
    """

    value: T
    source: str
    favicon: str | None = None
    context: str | None = None

    model_config = ConfigDict(
        # Allow extra fields for forward compatibility
        extra="ignore",
        # Enable from_attributes for ORM model conversion
        from_attributes=True,
    )

    @field_validator("source")
    @classmethod
    def validate_source(cls, v: str) -> str:
        """Validate source field.

        Accepts:
        - URLs starting with http:// or https://
        - Known tool names: Chaps-e, linkedin, glassdoor, mistral, claude,
          perplexity, wikipedia, langgraph, azure_openai
        - Any other string (lenient for flexibility)

        Args:
            v: Source string to validate

        Returns:
            Validated source string
        """
        if not v:
            return v

        # Accept URLs
        if v.startswith(("http://", "https://")):
            return v

        # Accept known tool names (case-insensitive check but preserve original)
        known_tools = {
            "chaps-e",
            "linkedin",
            "glassdoor",
            "mistral",
            "claude",
            "perplexity",
            "wikipedia",
            "langgraph",
            "azure_openai",
            "n8n",
        }
        if v.lower() in known_tools:
            return v

        # Be lenient - accept any string for flexibility
        return v


# Profile section schemas


class ProfileResponse(BaseModel):
    """Profile section response schema.

    Contains core company information with SourcedValue pattern.
    Matches frontend Company.profile interface.

    Translatable fields (via translations table):
    - insights, businessLine, catchphrase

    Non-translatable fields (proper nouns, numbers, locations):
    - groupName, ceo, hq, establishmentYear, employeeCount, revenue
    """

    insights: SourcedValue[str] | None = None
    groupName: SourcedValue[str] | None = None
    businessLine: SourcedValue[str] | None = None
    catchphrase: SourcedValue[str] | None = None
    establishmentYear: SourcedValue[str] | None = None
    employeeCount: SourcedValue[str] | None = None
    revenue: SourcedValue[str] | None = None
    ceo: SourcedValue[str] | None = None
    hq: SourcedValue[str] | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class ProfileCreate(BaseModel):
    """Profile section input schema for creating/updating profile data."""

    insights: str | None = None
    groupName: SourcedValue[str] | None = None
    businessLine: SourcedValue[str] | None = None
    catchphrase: SourcedValue[str] | None = None
    establishmentYear: SourcedValue[str] | None = None
    employeeCount: SourcedValue[str] | None = None
    revenue: SourcedValue[str] | None = None
    ceo: SourcedValue[str] | None = None
    hq: SourcedValue[str] | None = None

    model_config = ConfigDict(extra="ignore")


# Digital section schemas


class OnlineServiceResponse(BaseModel):
    """Online service response schema."""

    name: str | None = None
    description: str | None = None
    name_source: str | None = None
    description_source: str | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class OnlineServiceSourced(BaseModel):
    """Online service with sourced fields for agent input."""

    name: SourcedValue[str] | None = None
    description: SourcedValue[str] | None = None

    model_config = ConfigDict(extra="ignore")


class SocialMediaAccountResponse(BaseModel):
    """Social media account response schema."""

    platform: str
    url: str
    source: str | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class DigitalStrategyResponse(BaseModel):
    """Digital strategy nested response matching frontend interface."""

    overallStrategy: str | None = None
    digitalTransformation: str | None = None
    eCommerceCapabilities: str | None = None
    mobileStrategy: str | None = None
    digitalMarketingApproach: str | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class DigitalResponse(BaseModel):
    """Digital section response schema.

    Matches frontend Company.digital interface with nested structures
    for digital strategy, online services, and social media.
    """

    insights: str | None = None
    digitalStrategy: SourcedValue[DigitalStrategyResponse] | None = None
    onlineServices: SourcedValue[list[OnlineServiceResponse]] | None = None
    socialMediaAccounts: list[SocialMediaAccountResponse] | None = None
    loyaltyProgram: SourcedValue[str] | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class DigitalCreate(BaseModel):
    """Digital section input schema from agent output."""

    insights: str | None = None
    digitalStrategy: dict[str, Any] | None = None
    onlineServices: list[OnlineServiceSourced] | None = None
    socialMediaAccounts: list[SocialMediaAccountResponse] | None = None
    loyaltyProgram: SourcedValue[str] | None = None

    model_config = ConfigDict(extra="ignore")


# Timeline section schemas


class TimelineEventResponse(BaseModel):
    """Timeline event response schema.

    Matches frontend timeline.events[] interface.
    """

    date: str | None = None
    title: str | None = None
    description: str | None = None
    category: str | None = None
    location: str | None = None
    impact: str | None = None
    source: str | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class TimelineEventSourced(BaseModel):
    """Timeline event with sourced fields for agent input."""

    date: SourcedValue[str] | None = None
    title: SourcedValue[str] | None = None
    description: SourcedValue[str] | None = None
    category: SourcedValue[str] | None = None
    location: SourcedValue[str] | None = None
    impact: SourcedValue[str] | None = None

    model_config = ConfigDict(extra="ignore")


class TimelineResponse(BaseModel):
    """Timeline section response schema.

    Contains insights summary and list of historical events.
    """

    insights: str | None = None
    events: list[TimelineEventResponse] | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class TimelineCreate(BaseModel):
    """Timeline section input schema from agent output."""

    insights: str | None = None
    events: list[TimelineEventSourced] | None = None

    model_config = ConfigDict(extra="ignore")


# Products section schemas


class ProductItemResponse(BaseModel):
    """Product item response schema with type and sourced value."""

    value: str
    source: str
    type: ProductItemTypeEnum | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class ProductCategoryResponse(BaseModel):
    """Product category response schema."""

    category_name: str
    items: list[str] = Field(default_factory=list)

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class ProductsResponse(BaseModel):
    """Products section response schema.

    Matches frontend Company.products interface with grouped
    product items by type and categories dict.
    """

    insights: str | None = None
    customerType: str | None = None
    marketingPositioning: str | None = None
    range: list[SourcedValue[str]] = Field(default_factory=list)
    partnerBrands: list[SourcedValue[str]] = Field(default_factory=list)
    privateLabels: list[SourcedValue[str]] = Field(default_factory=list)
    categories: dict[str, list[str]] = Field(default_factory=dict)

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class ProductsCreate(BaseModel):
    """Products section input schema from agent output."""

    insights: str | None = None
    customerType: SourcedValue[str] | None = None
    marketingPositioning: SourcedValue[str] | None = None
    range: list[SourcedValue[str]] | None = None
    partnerBrands: list[SourcedValue[str]] | None = None
    privateLabels: list[SourcedValue[str]] | None = None
    categories: dict[str, list[str]] | None = None

    model_config = ConfigDict(extra="ignore")


# Jobs section schemas


class JobOfferResponse(BaseModel):
    """Job offer response schema.

    Matches frontend jobs.offers[] interface.
    """

    title: str | None = None
    location: str | None = None
    department: str | None = None
    description: str | None = None
    requirements: str | None = None
    posted_date: str | None = None
    source: str | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class JobOfferSourced(BaseModel):
    """Job offer with sourced fields for agent input."""

    title: SourcedValue[str] | None = None
    location: SourcedValue[str] | None = None
    department: SourcedValue[str] | None = None
    description: SourcedValue[str] | None = None
    requirements: SourcedValue[str] | None = None
    posted_date: SourcedValue[str] | None = None

    model_config = ConfigDict(extra="ignore")


class JobInsightsResponse(BaseModel):
    """Job insights response schema.

    Matches frontend jobs.insights interface with structured
    hiring data (total_openings as int, others as sourced values).
    """

    total_openings: SourcedValue[int] | None = None
    top_departments: SourcedValue[list[str]] | None = None
    hiring_focus: SourcedValue[str] | None = None
    growth_indicators: SourcedValue[str] | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class JobsResponse(BaseModel):
    """Jobs section response schema.

    Contains structured insights and list of job offers.
    Matches frontend Company.jobs interface.
    """

    insights: JobInsightsResponse | None = None
    offers: list[JobOfferResponse] | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class JobsCreate(BaseModel):
    """Jobs section input schema from agent output."""

    insights: dict[str, Any] | None = None
    offers: list[JobOfferSourced] | None = None

    model_config = ConfigDict(extra="ignore")


# CSR section schemas


class CsrInitiativeResponse(BaseModel):
    """CSR initiative response schema."""

    type: CsrInitiativeTypeEnum
    title: str | None = Field(
        default=None,
        description="Short title of the CSR initiative (program, certification, report, etc.).",
        examples=["Programme Net Zéro 2030"],
    )
    value: str
    source: str
    date: datetime.date | None = Field(
        default=None,
        description="Initiative or publication date in ISO 8601 (YYYY-MM-DD). Null when the source does not provide one.",
        examples=["2024-03-15"],
    )

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class CsrResponse(BaseModel):
    """CSR section response schema.

    Contains insights, responsibility statement, and initiatives
    grouped by type. Matches frontend Company.csr interface.
    """

    insights: str | None = None
    responsibility: str | None = None
    responsibility_initiatives: list[SourcedValue[str]] = Field(default_factory=list)
    charity_actions: list[SourcedValue[str]] = Field(default_factory=list)
    sustainability_programs: list[SourcedValue[str]] = Field(default_factory=list)
    community_involvement: list[SourcedValue[str]] = Field(default_factory=list)
    diversity_inclusion: list[SourcedValue[str]] = Field(default_factory=list)
    ethical_practices: list[SourcedValue[str]] = Field(default_factory=list)
    awards_certifications: list[SourcedValue[str]] = Field(default_factory=list)

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class CsrInitiativeSourced(BaseModel):
    """CSR initiative with sourced fields for agent input."""

    type: CsrInitiativeTypeEnum
    title: str | None = Field(default=None, max_length=500)
    value: str
    source: str
    date: datetime.date | None = None

    model_config = ConfigDict(extra="ignore")


class CsrCreate(BaseModel):
    """CSR section input schema from agent output."""

    insights: str | None = None
    responsibility: SourcedValue[str] | None = None
    initiatives: list[CsrInitiativeSourced] | None = None

    model_config = ConfigDict(extra="ignore")


# Press section schemas


class PressItemResponse(BaseModel):
    """Press item response schema.

    Note: Uses single 'source' field instead of old 'sources[]' array.
    """

    type: PressItemTypeEnum
    title: str | None = Field(
        default=None,
        description="Short title of the press item (article headline, release name, etc.).",
        examples=["Interview CEO – Les Échos"],
    )
    value: str
    source: str
    date: datetime.date | None = Field(
        default=None,
        description="Publication date in ISO 8601 (YYYY-MM-DD). Null when the source does not provide one.",
        examples=["2024-03-15"],
    )

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class PressResponse(BaseModel):
    """Press section response schema.

    Contains insights and items grouped by type.
    Matches frontend Company.press interface.

    Note: Frontend interface still uses old structure with separate
    arrays and 'sources[]'. Response schema here matches the normalized
    structure - a future task will update frontend to match.
    """

    insights: str | None = None
    # Grouped by type for frontend compatibility
    articles: list[PressItemResponse] = Field(default_factory=list)
    press_releases: list[PressItemResponse] = Field(default_factory=list)
    media_mentions: list[PressItemResponse] = Field(default_factory=list)
    awards_recognition: list[PressItemResponse] = Field(default_factory=list)
    product_launches: list[PressItemResponse] = Field(default_factory=list)
    executive_interviews: list[PressItemResponse] = Field(default_factory=list)
    financial_news: list[PressItemResponse] = Field(default_factory=list)
    partnership_announcements: list[PressItemResponse] = Field(default_factory=list)

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class PressItemSourced(BaseModel):
    """Press item with sourced fields for agent input."""

    type: PressItemTypeEnum
    title: str | None = Field(default=None, max_length=500)
    value: str
    source: str
    date: datetime.date | None = None

    model_config = ConfigDict(extra="ignore")


class PressCreate(BaseModel):
    """Press section input schema from agent output."""

    insights: str | None = None
    items: list[PressItemSourced] | None = None

    model_config = ConfigDict(extra="ignore")


# Team section schemas


class TeamMemberResponse(BaseModel):
    """Team member response schema with hierarchical support.

    Uses adjacency list pattern in database but builds tree
    structure for frontend compatibility.

    Attributes:
        position: Job title/position (sourced)
        firstName: First name
        lastName: Last name
        linkedinUrl: Optional LinkedIn profile URL
        subordinates: Optional list of direct reports (recursive)
    """

    position: str
    firstName: str
    lastName: str
    linkedinUrl: str | None = None
    subordinates: list["TeamMemberResponse"] | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class TeamMemberSourced(BaseModel):
    """Team member with sourced fields for agent input."""

    position: SourcedValue[str] | None = None
    firstName: SourcedValue[str] | None = None
    lastName: SourcedValue[str] | None = None
    linkedinUrl: SourcedValue[str] | None = None
    subordinates: list["TeamMemberSourced"] | None = None

    model_config = ConfigDict(extra="ignore")


class TeamCreate(BaseModel):
    """Team section input schema from agent output.

    Team is passed as a flat list with hierarchy defined by
    nested subordinates arrays in the agent JSON.
    """

    members: list[TeamMemberSourced] | None = None

    model_config = ConfigDict(extra="ignore")


# Update forward references for recursive types
TeamMemberResponse.model_rebuild()
TeamMemberSourced.model_rebuild()


# Financial section schemas


class FinancialMetricResponse(BaseModel):
    """Individual financial metric for a reporting period."""

    metric_name: str
    period: str | None = None
    value: str | None = None
    unit: str | None = None
    source: str | None = None
    context: str | None = None

    model_config = ConfigDict(
        from_attributes=True,
        extra="ignore",
        populate_by_name=True,
        alias_generator=to_camel,  # serialises as metricName, etc.
    )


class FundingRoundResponse(BaseModel):
    """Funding round in company's financing history."""

    round_type: str | None = None
    amount: str | None = None
    date: str | None = None
    lead_investor: str | None = None
    valuation: str | None = None
    source: str | None = None

    model_config = ConfigDict(
        from_attributes=True,
        extra="ignore",
        populate_by_name=True,
        alias_generator=to_camel,  # serialises as roundType, leadInvestor, etc.
    )


class FinancialResponse(BaseModel):
    """Financial section response with overview, metrics, and funding rounds."""

    insights: SourcedValue[str] | None = None
    companyType: SourcedValue[str] | None = None
    tickerSymbol: SourcedValue[str] | None = None
    stockExchange: SourcedValue[str] | None = None
    currency: SourcedValue[str] | None = None
    fiscalYearEnd: SourcedValue[str] | None = None
    revenue: SourcedValue[str] | None = None
    revenueGrowth: SourcedValue[str] | None = None
    grossMargin: SourcedValue[str] | None = None
    ebitdaMargin: SourcedValue[str] | None = None
    netMargin: SourcedValue[str] | None = None
    marketCap: SourcedValue[str] | None = None
    enterpriseValue: SourcedValue[str] | None = None
    peRatio: SourcedValue[str] | None = None
    evEbitda: SourcedValue[str] | None = None
    evRevenue: SourcedValue[str] | None = None
    employeeCount: SourcedValue[str] | None = None
    totalFunding: SourcedValue[str] | None = None
    lastValuation: SourcedValue[str] | None = None
    debtToEquity: SourcedValue[str] | None = None
    freeCashFlow: SourcedValue[str] | None = None
    metrics: list[FinancialMetricResponse] | None = None
    fundingRounds: list[FundingRoundResponse] | None = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


# Complete company sections response


class CompanySectionsResponse(BaseModel):
    """Complete response schema for all company sections.

    This is used to build the full CompanyResponse with typed
    section data instead of Dict[str, Any].
    """

    profile: ProfileResponse = Field(default_factory=ProfileResponse)
    digital: DigitalResponse = Field(default_factory=DigitalResponse)
    timeline: TimelineResponse = Field(default_factory=TimelineResponse)
    products: ProductsResponse = Field(default_factory=ProductsResponse)
    jobs: JobsResponse = Field(default_factory=JobsResponse)
    csr: CsrResponse = Field(default_factory=CsrResponse)
    press: PressResponse = Field(default_factory=PressResponse)
    financial: FinancialResponse = Field(default_factory=FinancialResponse)
    team: list[TeamMemberResponse] = Field(default_factory=list)

    model_config = ConfigDict(from_attributes=True, extra="ignore")
