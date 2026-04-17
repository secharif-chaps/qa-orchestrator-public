"""Pydantic output schemas for agent structured outputs.

These schemas serve as the single source of truth for:
1. OpenAI structured output enforcement (text.format.json_schema)
2. Prompt output format generation (auto-generated from schemas)
3. Post-parse validation (belt and suspenders)

Key design decisions:
- All fields Optional (LLM may not find data)
- extra="forbid" generates additionalProperties: false (required by OpenAI)
- Field names match what writers in company_section_service.py ALREADY expect
- Enum values match DB enums exactly (no mapping needed)
"""

from enum import StrEnum

from pydantic import BaseModel, ConfigDict, Field

# =============================================================================
# SHARED MODELS
# =============================================================================


class SourcedValue(BaseModel):
    """A value with its source URL."""

    model_config = ConfigDict(extra="forbid")

    value: str | None = None
    source: str | None = None
    context: str | None = None


# =============================================================================
# PROFILE AGENT
# =============================================================================


class ProfileAgentOutput(BaseModel):
    """Output schema for the profile agent.

    Field names use camelCase to match what save_profile_data() reads
    via _get_sourced_value(data, "groupName"), etc.
    """

    model_config = ConfigDict(extra="forbid")

    insights: str | None = None
    groupName: SourcedValue | None = None
    businessLine: SourcedValue | None = None
    catchphrase: SourcedValue | None = None
    establishmentYear: SourcedValue | None = None
    employeeCount: SourcedValue | None = None
    revenue: SourcedValue | None = None
    ceo: SourcedValue | None = None
    hq: SourcedValue | None = None


# =============================================================================
# DIGITAL AGENT
# =============================================================================


class DigitalStrategyItem(BaseModel):
    """Individual digital strategy field."""

    model_config = ConfigDict(extra="forbid")

    overall_strategy: SourcedValue | None = None
    digital_transformation: SourcedValue | None = None
    e_commerce_capabilities: SourcedValue | None = None
    mobile_strategy: SourcedValue | None = None
    digital_marketing_approach: SourcedValue | None = None


class SocialMediaAccount(BaseModel):
    """Social media account entry."""

    model_config = ConfigDict(extra="forbid")

    platform: str | None = None
    url: str | None = None
    followers: str | None = None
    source: str | None = None


class OnlineService(BaseModel):
    """Online service entry."""

    model_config = ConfigDict(extra="forbid")

    name: str | None = None
    description: str | None = None
    source: str | None = None


class LoyaltyProgram(BaseModel):
    """Loyalty program entry."""

    model_config = ConfigDict(extra="forbid")

    name: SourcedValue | None = None
    description: SourcedValue | None = None


class DigitalAgentOutput(BaseModel):
    """Output schema for the digital agent.

    Field names use snake_case to match what save_digital_data() reads:
    - social_media_accounts (not socialMedia)
    - online_services (not onlineServices)
    - digital_strategy (nested object)
    - loyalty_programs (list)
    """

    model_config = ConfigDict(extra="forbid")

    insights: str | None = None
    # Use Field(default_factory=list) so _make_strict_compatible() strips `default`
    # and adds these to `required`, forcing the LLM to return an array (not null)
    social_media_accounts: list[SocialMediaAccount] = Field(default_factory=list)
    online_services: list[OnlineService] = Field(default_factory=list)
    digital_strategy: DigitalStrategyItem | None = None
    loyalty_programs: list[LoyaltyProgram] = Field(default_factory=list)


# =============================================================================
# PRESS AGENT
# =============================================================================


class PressItemTypeEnum(StrEnum):
    """Press item types matching DB enum PressItemType."""

    article = "article"
    press_release = "press_release"
    media_mention = "media_mention"
    award = "award"
    product_launch = "product_launch"
    interview = "interview"
    financial = "financial"
    partnership = "partnership"


class PressItem(BaseModel):
    """Unified press item with type field."""

    model_config = ConfigDict(extra="forbid")

    type: PressItemTypeEnum | None = None
    value: str | None = None
    source: str | None = None


class PressAgentOutput(BaseModel):
    """Output schema for the press agent.

    Uses unified items list with type field instead of 8 separate arrays.
    This matches the updated _save_press_items() writer.
    """

    model_config = ConfigDict(extra="forbid")

    insights: str | None = None
    items: list[PressItem] = Field(default_factory=list)


# =============================================================================
# JOBS AGENT
# =============================================================================


class JobsInsightsData(BaseModel):
    """Structured job market insights."""

    model_config = ConfigDict(extra="forbid")

    total_openings: SourcedValue | None = None
    top_departments: SourcedValue | None = None
    hiring_focus: SourcedValue | None = None
    growth_indicators: SourcedValue | None = None


class JobOffer(BaseModel):
    """Job offer entry."""

    model_config = ConfigDict(extra="forbid")

    title: SourcedValue | None = None
    location: SourcedValue | None = None
    department: SourcedValue | None = None
    description: SourcedValue | None = None
    requirements: SourcedValue | None = None
    posted_date: SourcedValue | None = None


class JobsAgentOutput(BaseModel):
    """Output schema for the jobs agent.

    Already works — field names match what save_jobs_data() expects.
    """

    model_config = ConfigDict(extra="forbid")

    insights: str | None = None
    insights_data: JobsInsightsData | None = None
    offers: list[JobOffer] = Field(default_factory=list)


# =============================================================================
# PRODUCTS AGENT
# =============================================================================


class ProductItem(BaseModel):
    """Product item entry (for range, partner_brands, private_labels)."""

    model_config = ConfigDict(extra="forbid")

    name: SourcedValue | None = None


class ProductCategory(BaseModel):
    """Product category with items list."""

    model_config = ConfigDict(extra="forbid")

    name: str | None = None
    items: list[str] | None = None


class ProductsAgentOutput(BaseModel):
    """Output schema for the products agent.

    Field names match what save_products_data() and _save_product_items() expect:
    - range, partner_brands, private_labels (typed arrays)
    - categories (list of {name, items})
    - customer_type, marketing_positioning (SourcedValue)
    """

    model_config = ConfigDict(extra="forbid")

    insights: str | None = None
    customer_type: SourcedValue | None = None
    marketing_positioning: SourcedValue | None = None
    range: list[ProductItem] = Field(default_factory=list)
    partner_brands: list[ProductItem] = Field(default_factory=list)
    private_labels: list[ProductItem] = Field(default_factory=list)
    categories: list[ProductCategory] = Field(default_factory=list)


# =============================================================================
# TIMELINE AGENT
# =============================================================================


class TimelineEvent(BaseModel):
    """Timeline event entry."""

    model_config = ConfigDict(extra="forbid")

    date: SourcedValue | None = None
    title: SourcedValue | None = None
    description: SourcedValue | None = None
    category: SourcedValue | None = None
    location: SourcedValue | None = None
    impact: SourcedValue | None = None


class TimelineAgentOutput(BaseModel):
    """Output schema for the timeline agent.

    Already works — field names match what save_timeline_data() expects.
    """

    model_config = ConfigDict(extra="forbid")

    insights: str | None = None
    events: list[TimelineEvent] = Field(default_factory=list)


# =============================================================================
# CSR AGENT
# =============================================================================


class CsrInitiativeTypeEnum(StrEnum):
    """CSR initiative types matching DB enum CsrInitiativeType."""

    responsibility = "responsibility"
    charity = "charity"
    sustainability = "sustainability"
    community = "community"
    diversity = "diversity"
    ethics = "ethics"
    awards = "awards"


class CsrInitiative(BaseModel):
    """CSR initiative with type field matching DB enum."""

    model_config = ConfigDict(extra="forbid")

    type: CsrInitiativeTypeEnum | None = None
    value: str | None = None
    source: str | None = None


class CsrAgentOutput(BaseModel):
    """Output schema for the CSR agent.

    Uses DB enum values (sustainability, not environmental).
    The items list with type field matches _save_csr_initiatives().
    """

    model_config = ConfigDict(extra="forbid")

    insights: str | None = None
    responsibility: SourcedValue | None = None
    # Renamed from "initiatives" to "items" so the JSON key sent to OpenAI
    # is also "items", matching what _save_csr_initiatives() reads via
    # csr_data.get("items", []) after the corresponding fix in the writer.
    items: list[CsrInitiative] = Field(default_factory=list)


# =============================================================================
# TEAM AGENT
# =============================================================================


class TeamMember(BaseModel):
    """Team member with hierarchy support.

    Field names match what _save_team_members_recursive() expects:
    - first_name, last_name (not name)
    - position (not title)
    - linkedin_url (not linkedin)
    - subordinates (recursive)
    """

    model_config = ConfigDict(extra="forbid")

    first_name: str | None = None
    last_name: str | None = None
    position: str | None = None
    linkedin_url: str | None = None
    subordinates: list["TeamMember"] = Field(default_factory=list)


class TeamAgentOutput(BaseModel):
    """Output schema for the team agent.

    Uses "team" key matching what save_team_data() looks for.
    """

    model_config = ConfigDict(extra="forbid")

    insights: str | None = None
    team: list[TeamMember] = Field(default_factory=list)


# =============================================================================
# FINANCIAL AGENT
# =============================================================================


class FinancialMetric(BaseModel):
    """Individual financial metric with period tracking.

    Field names use camelCase to match what _save_financial_metrics() reads
    via metric_data.get("metricName", metric_data.get("metric_name", "")).

    metricName: LLM may use any descriptive name; backend normalises it to a
    canonical key (revenue, ebitda, netIncome, freeCashFlow) when applicable.
    context: optional extra context from the LLM about this metric, or
    the original name if normalisation renamed it.
    """

    model_config = ConfigDict(extra="forbid")

    metricName: str | None = None
    period: str | None = None
    value: str | None = None
    unit: str | None = None
    source: str | None = None
    context: str | None = None


class FundingRound(BaseModel):
    """Funding round entry for private companies.

    Field names use camelCase to match what _save_funding_rounds() reads
    via round_data.get("roundType", round_data.get("round_type")).
    """

    model_config = ConfigDict(extra="forbid")

    roundType: str | None = None
    amount: str | None = None
    date: str | None = None
    leadInvestor: str | None = None
    valuation: str | None = None
    source: str | None = None


class FinancialAgentOutput(BaseModel):
    """Output schema for the financial agent.

    Field names use camelCase to match what save_financial_data() reads
    via _get_sourced_value(data, "companyType"), etc.

    Covers all three company paths:
    - Public US: ticker, exchange, market data, valuation, SEC fundamentals
    - Public non-US: ticker, exchange, market data, valuation
    - Private: funding rounds, total funding, last valuation, revenue estimates
    """

    model_config = ConfigDict(extra="forbid")

    insights: str | None = None
    companyType: SourcedValue | None = None
    tickerSymbol: SourcedValue | None = None
    stockExchange: SourcedValue | None = None
    currency: SourcedValue | None = None
    fiscalYearEnd: SourcedValue | None = None
    revenue: SourcedValue | None = None
    revenueGrowth: SourcedValue | None = None
    grossMargin: SourcedValue | None = None
    ebitdaMargin: SourcedValue | None = None
    netMargin: SourcedValue | None = None
    marketCap: SourcedValue | None = None
    enterpriseValue: SourcedValue | None = None
    peRatio: SourcedValue | None = None
    evEbitda: SourcedValue | None = None
    evRevenue: SourcedValue | None = None
    employeeCount: SourcedValue | None = None
    totalFunding: SourcedValue | None = None
    lastValuation: SourcedValue | None = None
    debtToEquity: SourcedValue | None = None
    freeCashFlow: SourcedValue | None = None
    metrics: list[FinancialMetric] | None = None
    fundingRounds: list[FundingRound] | None = None


# =============================================================================
# REGISTRY
# =============================================================================

AGENT_OUTPUT_SCHEMAS: dict[str, type[BaseModel]] = {
    "profile": ProfileAgentOutput,
    "digital": DigitalAgentOutput,
    "press": PressAgentOutput,
    "jobs": JobsAgentOutput,
    "products": ProductsAgentOutput,
    "timeline": TimelineAgentOutput,
    "csr": CsrAgentOutput,
    "team": TeamAgentOutput,
    "financial": FinancialAgentOutput,
}
