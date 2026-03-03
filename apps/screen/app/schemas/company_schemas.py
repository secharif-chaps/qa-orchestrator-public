"""Pydantic schemas for normalized company data.

This module defines Pydantic schemas for the new normalized company data structure
with SourcedValue pattern support. These schemas are used for:
- Validating data from Dify workflow callbacks
- Serializing data for API responses
- Ensuring frontend compatibility

The SourcedValue[T] generic provides a consistent pattern for values with source
attribution. Translations are handled via the translations table.

Schemas follow the frontend TypeScript interfaces for compatibility while
supporting the normalized database structure.
"""

from enum import Enum
from typing import TypeVar, Generic, Optional, Any

from pydantic import BaseModel, ConfigDict, Field, field_validator


# Generic type for SourcedValue
T = TypeVar('T')


# ENUM definitions matching SQLAlchemy models

class ProductItemTypeEnum(str, Enum):
    """Product item type enum values."""
    range = "range"
    partner_brand = "partner_brand"
    private_label = "private_label"


class CsrInitiativeTypeEnum(str, Enum):
    """CSR initiative type enum values."""
    responsibility = "responsibility"
    charity = "charity"
    sustainability = "sustainability"
    community = "community"
    diversity = "diversity"
    ethics = "ethics"
    awards = "awards"


class PressItemTypeEnum(str, Enum):
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
    favicon: Optional[str] = None

    model_config = ConfigDict(
        # Allow extra fields for forward compatibility
        extra="ignore",
        # Enable from_attributes for ORM model conversion
        from_attributes=True
    )

    @field_validator('source')
    @classmethod
    def validate_source(cls, v: str) -> str:
        """Validate source field.

        Accepts:
        - URLs starting with http:// or https://
        - Known tool names: Chaps-e, linkedin, glassdoor, mistral, claude,
          perplexity, wikipedia, dify
        - Any other string (lenient for flexibility)

        Args:
            v: Source string to validate

        Returns:
            Validated source string
        """
        if not v:
            return v

        # Accept URLs
        if v.startswith(('http://', 'https://')):
            return v

        # Accept known tool names (case-insensitive check but preserve original)
        known_tools = {
            'chaps-e', 'linkedin', 'glassdoor', 'mistral', 'claude',
            'perplexity', 'wikipedia', 'dify', 'n8n'
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
    insights: Optional[SourcedValue[str]] = None
    groupName: Optional[SourcedValue[str]] = None
    businessLine: Optional[SourcedValue[str]] = None
    catchphrase: Optional[SourcedValue[str]] = None
    establishmentYear: Optional[SourcedValue[str]] = None
    employeeCount: Optional[SourcedValue[str]] = None
    revenue: Optional[SourcedValue[str]] = None
    ceo: Optional[SourcedValue[str]] = None
    hq: Optional[SourcedValue[str]] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class ProfileCreate(BaseModel):
    """Profile section input schema for creating/updating profile data."""
    insights: Optional[str] = None
    groupName: Optional[SourcedValue[str]] = None
    businessLine: Optional[SourcedValue[str]] = None
    catchphrase: Optional[SourcedValue[str]] = None
    establishmentYear: Optional[SourcedValue[str]] = None
    employeeCount: Optional[SourcedValue[str]] = None
    revenue: Optional[SourcedValue[str]] = None
    ceo: Optional[SourcedValue[str]] = None
    hq: Optional[SourcedValue[str]] = None

    model_config = ConfigDict(extra="ignore")


# Digital section schemas

class OnlineServiceResponse(BaseModel):
    """Online service response schema."""
    name: Optional[str] = None
    description: Optional[str] = None
    name_source: Optional[str] = None
    description_source: Optional[str] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class OnlineServiceSourced(BaseModel):
    """Online service with sourced fields for Dify input."""
    name: Optional[SourcedValue[str]] = None
    description: Optional[SourcedValue[str]] = None

    model_config = ConfigDict(extra="ignore")


class SocialMediaAccountResponse(BaseModel):
    """Social media account response schema."""
    platform: str
    url: str
    source: Optional[str] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class DigitalStrategyResponse(BaseModel):
    """Digital strategy nested response matching frontend interface."""
    overallStrategy: Optional[str] = None
    digitalTransformation: Optional[str] = None
    eCommerceCapabilities: Optional[str] = None
    mobileStrategy: Optional[str] = None
    digitalMarketingApproach: Optional[str] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class DigitalResponse(BaseModel):
    """Digital section response schema.

    Matches frontend Company.digital interface with nested structures
    for digital strategy, online services, and social media.
    """
    insights: Optional[str] = None
    digitalStrategy: Optional[SourcedValue[DigitalStrategyResponse]] = None
    onlineServices: Optional[SourcedValue[list[OnlineServiceResponse]]] = None
    socialMediaAccounts: Optional[list[SocialMediaAccountResponse]] = None
    loyaltyProgram: Optional[SourcedValue[str]] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class DigitalCreate(BaseModel):
    """Digital section input schema from Dify webhook."""
    insights: Optional[str] = None
    digitalStrategy: Optional[dict[str, Any]] = None
    onlineServices: Optional[list[OnlineServiceSourced]] = None
    socialMediaAccounts: Optional[list[SocialMediaAccountResponse]] = None
    loyaltyProgram: Optional[SourcedValue[str]] = None

    model_config = ConfigDict(extra="ignore")


# Timeline section schemas

class TimelineEventResponse(BaseModel):
    """Timeline event response schema.

    Matches frontend timeline.events[] interface.
    """
    date: Optional[str] = None
    title: Optional[str] = None
    description: Optional[str] = None
    category: Optional[str] = None
    location: Optional[str] = None
    impact: Optional[str] = None
    source: Optional[str] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class TimelineEventSourced(BaseModel):
    """Timeline event with sourced fields for Dify input."""
    date: Optional[SourcedValue[str]] = None
    title: Optional[SourcedValue[str]] = None
    description: Optional[SourcedValue[str]] = None
    category: Optional[SourcedValue[str]] = None
    location: Optional[SourcedValue[str]] = None
    impact: Optional[SourcedValue[str]] = None

    model_config = ConfigDict(extra="ignore")


class TimelineResponse(BaseModel):
    """Timeline section response schema.

    Contains insights summary and list of historical events.
    """
    insights: Optional[str] = None
    events: Optional[list[TimelineEventResponse]] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class TimelineCreate(BaseModel):
    """Timeline section input schema from Dify webhook."""
    insights: Optional[str] = None
    events: Optional[list[TimelineEventSourced]] = None

    model_config = ConfigDict(extra="ignore")


# Products section schemas

class ProductItemResponse(BaseModel):
    """Product item response schema with type and sourced value."""
    value: str
    source: str
    type: Optional[ProductItemTypeEnum] = None

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
    insights: Optional[str] = None
    customerType: Optional[str] = None
    marketingPositioning: Optional[str] = None
    range: list[SourcedValue[str]] = Field(default_factory=list)
    partnerBrands: list[SourcedValue[str]] = Field(default_factory=list)
    privateLabels: list[SourcedValue[str]] = Field(default_factory=list)
    categories: dict[str, list[str]] = Field(default_factory=dict)

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class ProductsCreate(BaseModel):
    """Products section input schema from Dify webhook."""
    insights: Optional[str] = None
    customerType: Optional[SourcedValue[str]] = None
    marketingPositioning: Optional[SourcedValue[str]] = None
    range: Optional[list[SourcedValue[str]]] = None
    partnerBrands: Optional[list[SourcedValue[str]]] = None
    privateLabels: Optional[list[SourcedValue[str]]] = None
    categories: Optional[dict[str, list[str]]] = None

    model_config = ConfigDict(extra="ignore")


# Jobs section schemas

class JobOfferResponse(BaseModel):
    """Job offer response schema.

    Matches frontend jobs.offers[] interface.
    """
    title: Optional[str] = None
    location: Optional[str] = None
    department: Optional[str] = None
    description: Optional[str] = None
    requirements: Optional[str] = None
    posted_date: Optional[str] = None
    source: Optional[str] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class JobOfferSourced(BaseModel):
    """Job offer with sourced fields for Dify input."""
    title: Optional[SourcedValue[str]] = None
    location: Optional[SourcedValue[str]] = None
    department: Optional[SourcedValue[str]] = None
    description: Optional[SourcedValue[str]] = None
    requirements: Optional[SourcedValue[str]] = None
    posted_date: Optional[SourcedValue[str]] = None

    model_config = ConfigDict(extra="ignore")


class JobInsightsResponse(BaseModel):
    """Job insights response schema.

    Matches frontend jobs.insights interface with structured
    hiring data (total_openings as int, others as sourced values).
    """
    total_openings: Optional[SourcedValue[int]] = None
    top_departments: Optional[SourcedValue[list[str]]] = None
    hiring_focus: Optional[SourcedValue[str]] = None
    growth_indicators: Optional[SourcedValue[str]] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class JobsResponse(BaseModel):
    """Jobs section response schema.

    Contains structured insights and list of job offers.
    Matches frontend Company.jobs interface.
    """
    insights: Optional[JobInsightsResponse] = None
    offers: Optional[list[JobOfferResponse]] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class JobsCreate(BaseModel):
    """Jobs section input schema from Dify webhook."""
    insights: Optional[dict[str, Any]] = None
    offers: Optional[list[JobOfferSourced]] = None

    model_config = ConfigDict(extra="ignore")


# CSR section schemas

class CsrInitiativeResponse(BaseModel):
    """CSR initiative response schema."""
    type: CsrInitiativeTypeEnum
    value: str
    source: str

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class CsrResponse(BaseModel):
    """CSR section response schema.

    Contains insights, responsibility statement, and initiatives
    grouped by type. Matches frontend Company.csr interface.
    """
    insights: Optional[str] = None
    responsibility: Optional[str] = None
    responsibility_initiatives: list[SourcedValue[str]] = Field(default_factory=list)
    charity_actions: list[SourcedValue[str]] = Field(default_factory=list)
    sustainability_programs: list[SourcedValue[str]] = Field(default_factory=list)
    community_involvement: list[SourcedValue[str]] = Field(default_factory=list)
    diversity_inclusion: list[SourcedValue[str]] = Field(default_factory=list)
    ethical_practices: list[SourcedValue[str]] = Field(default_factory=list)
    awards_certifications: list[SourcedValue[str]] = Field(default_factory=list)

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class CsrInitiativeSourced(BaseModel):
    """CSR initiative with sourced fields for Dify input."""
    type: CsrInitiativeTypeEnum
    value: str
    source: str

    model_config = ConfigDict(extra="ignore")


class CsrCreate(BaseModel):
    """CSR section input schema from Dify webhook."""
    insights: Optional[str] = None
    responsibility: Optional[SourcedValue[str]] = None
    initiatives: Optional[list[CsrInitiativeSourced]] = None

    model_config = ConfigDict(extra="ignore")


# Press section schemas

class PressItemResponse(BaseModel):
    """Press item response schema.

    Note: Uses single 'source' field instead of old 'sources[]' array.
    """
    type: PressItemTypeEnum
    value: str
    source: str

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class PressResponse(BaseModel):
    """Press section response schema.

    Contains insights and items grouped by type.
    Matches frontend Company.press interface.

    Note: Frontend interface still uses old structure with separate
    arrays and 'sources[]'. Response schema here matches the normalized
    structure - a future task will update frontend to match.
    """
    insights: Optional[str] = None
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
    """Press item with sourced fields for Dify input."""
    type: PressItemTypeEnum
    value: str
    source: str

    model_config = ConfigDict(extra="ignore")


class PressCreate(BaseModel):
    """Press section input schema from Dify webhook."""
    insights: Optional[str] = None
    items: Optional[list[PressItemSourced]] = None

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
    linkedinUrl: Optional[str] = None
    subordinates: Optional[list['TeamMemberResponse']] = None

    model_config = ConfigDict(from_attributes=True, extra="ignore")


class TeamMemberSourced(BaseModel):
    """Team member with sourced fields for Dify input."""
    position: Optional[SourcedValue[str]] = None
    firstName: Optional[SourcedValue[str]] = None
    lastName: Optional[SourcedValue[str]] = None
    linkedinUrl: Optional[SourcedValue[str]] = None
    subordinates: Optional[list['TeamMemberSourced']] = None

    model_config = ConfigDict(extra="ignore")


class TeamCreate(BaseModel):
    """Team section input schema from Dify webhook.

    Team is passed as a flat list with hierarchy defined by
    nested subordinates arrays in the Dify JSON.
    """
    members: Optional[list[TeamMemberSourced]] = None

    model_config = ConfigDict(extra="ignore")


# Update forward references for recursive types
TeamMemberResponse.model_rebuild()
TeamMemberSourced.model_rebuild()


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
    team: list[TeamMemberResponse] = Field(default_factory=list)

    model_config = ConfigDict(from_attributes=True, extra="ignore")
