"""SQLAlchemy models for company child data (1:N relationships).

This module defines 1:N child models for normalized company data storage.
Each child model represents a collection of items related to a company
with SourcedValue pattern support where applicable.

Models:
- CompanyOnlineService: Online services offered by the company
- CompanySocialMediaAccount: Social media presence
- CompanyTimelineEvent: Historical events and milestones
- CompanyProductItem: Product items (ranges, partner brands, private labels)
- CompanyProductCategory: Product categories with items arrays
- CompanyJobOffer: Job openings
- CompanyCsrInitiative: CSR initiatives by type
- CompanyPressItem: Press coverage items by type
- CompanyTeamMember: Team hierarchy with self-referential parent_id
- CompanyCorporateEntity: Corporate structure entities (parents, subsidiaries, affiliates)
- CompanySanctionItem: Sanctions and compliance records from WorldCheck

All models have 1:N relationship with Company via company_id foreign key.
"""

import enum

from sqlalchemy import (
    Boolean,
    Column,
    Date,
    DateTime,
    Enum,
    ForeignKey,
    Integer,
    String,
    Text,
)
from sqlalchemy.dialects.postgresql import ARRAY
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import SCREEN_SCHEMA, Base

# ENUM definitions for type columns


class ProductItemType(enum.Enum):
    """Product item type enum.

    Values:
        range: Company's own product range
        partner_brand: Products from partner brands
        private_label: Private label products
    """

    range = "range"
    partner_brand = "partner_brand"
    private_label = "private_label"


class CsrInitiativeType(enum.Enum):
    """CSR initiative type enum.

    Values:
        responsibility: General corporate responsibility
        charity: Charitable giving and philanthropy
        sustainability: Environmental sustainability efforts
        community: Community engagement programs
        diversity: Diversity and inclusion initiatives
        ethics: Business ethics and governance
        awards: CSR-related awards and recognition
    """

    responsibility = "responsibility"
    charity = "charity"
    sustainability = "sustainability"
    community = "community"
    diversity = "diversity"
    ethics = "ethics"
    awards = "awards"


class PressItemType(enum.Enum):
    """Press item type enum.

    Values:
        article: News articles about the company
        press_release: Official press releases
        media_mention: Mentions in media coverage
        award: Awards and recognition
        product_launch: Product launch announcements
        interview: Executive interviews
        financial: Financial news and reports
        partnership: Partnership announcements
    """

    article = "article"
    press_release = "press_release"
    media_mention = "media_mention"
    award = "award"
    product_launch = "product_launch"
    interview = "interview"
    financial = "financial"
    partnership = "partnership"


class CorporateRelationshipType(enum.Enum):
    """Corporate entity relationship type enum.

    Values:
        parent: Parent company or holding group
        subsidiary: Subsidiary company
        affiliate: Affiliated company
        branch: Local branch or office
        regional_entity: Regional entity or division
    """

    parent = "parent"
    subsidiary = "subsidiary"
    affiliate = "affiliate"
    branch = "branch"
    regional_entity = "regional_entity"


class SanctionType(enum.StrEnum):
    """Types of sanctions/enforcement actions."""

    unfair_competition = "unfair_competition"
    data_protection = "data_protection"
    consumer_protection = "consumer_protection"
    ip_rights_infringement = "ip_rights_infringement"
    regulatory_enforcement = "regulatory_enforcement"
    financial_crime = "financial_crime"
    corruption = "corruption"
    money_laundering = "money_laundering"
    terrorism_financing = "terrorism_financing"
    tax_evasion = "tax_evasion"
    sanctions_violation = "sanctions_violation"
    environmental = "environmental"
    other = "other"


class RiskLevel(enum.StrEnum):
    """Risk level classification."""

    low = "low"
    medium = "medium"
    high = "high"
    critical = "critical"


# Child model definitions


class CompanyOnlineService(Base):
    """Online service offered by a company.

    Represents digital services or features available on the company's
    online platforms (e.g., virtual try-on, live chat, personalization).

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        name: Service name
        name_source: Source URL for service name
        description: Service description
        description_source: Source URL for description
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_online_services"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Name (translatable)
    name = Column(Text, nullable=True)
    name_source = Column(Text, nullable=True)

    # Description (translatable)
    description = Column(Text, nullable=True)
    description_source = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="online_services")


class CompanySocialMediaAccount(Base):
    """Social media account for a company.

    Represents a company's presence on social media platforms.
    No translation columns as platforms and URLs are not translatable.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        platform: Social media platform name (e.g., Instagram, LinkedIn)
        platform_source: Source URL where platform was found
        url: Direct URL to the company's account
        url_source: Source URL where URL was found
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_social_media_accounts"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Platform (not translatable - proper noun)
    platform = Column(Text, nullable=True)
    platform_source = Column(Text, nullable=True)

    # URL (not translatable)
    url = Column(Text, nullable=True)
    url_source = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="social_media_accounts")


class CompanyTimelineEvent(Base):
    """Timeline event for a company.

    Represents a significant historical event or milestone in the company's
    history (e.g., founding, acquisitions, launches, partnerships).

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        date: Event date (as text for flexibility)
        date_source: Source URL for date
        title: Event title
        title_source: Source URL for title
        description: Event description
        description_source: Source URL for description
        category: Event category (e.g., Foundation, Acquisition)
        category_source: Source URL for category
        location: Event location
        location_source: Source URL for location
        impact: Event impact description
        impact_source: Source URL for impact
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_timeline_events"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Date (not translatable - number/date)
    date = Column(Text, nullable=True)
    date_source = Column(Text, nullable=True)

    # Title (translatable)
    title = Column(Text, nullable=True)
    title_source = Column(Text, nullable=True)

    # Description (translatable)
    description = Column(Text, nullable=True)
    description_source = Column(Text, nullable=True)

    # Category (translatable)
    category = Column(Text, nullable=True)
    category_source = Column(Text, nullable=True)

    # Location (not translatable - proper noun/place)
    location = Column(Text, nullable=True)
    location_source = Column(Text, nullable=True)

    # Impact (translatable)
    impact = Column(Text, nullable=True)
    impact_source = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="timeline_events")


class CompanyProductItem(Base):
    """Product item for a company.

    Represents a product offering with type distinction between own ranges,
    partner brands, and private labels. Translation is only applicable
    for 'range' type items.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        type: Product item type (range, partner_brand, private_label)
        value: Product name or description
        value_source: Source URL for product info
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_product_items"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Type (ENUM)
    type = Column(
        Enum(
            ProductItemType,
            values_callable=lambda obj: [e.value for e in obj],
            name="product_item_type_enum",
            schema=SCREEN_SCHEMA,
        ),
        nullable=False,
    )

    # Value with source (translation only for 'range' type)
    value = Column(Text, nullable=True)
    value_source = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="product_items")


class CompanyProductCategory(Base):
    """Product category for a company.

    Represents a category of products with an array of items.
    Both category name and items are translatable.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        category_name: Category name
        items: Array of product item names
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_product_categories"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Category name (translatable)
    category_name = Column(Text, nullable=True)

    # Items array (translatable)
    items = Column(ARRAY(Text), nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="product_categories")


class CompanyJobOffer(Base):
    """Job offer for a company.

    Represents an open job position with details about location,
    department, requirements, and posting date.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        title: Job title
        title_source: Source URL for job posting
        location: Job location
        location_source: Source URL for location
        department: Department name
        department_source: Source URL for department
        description: Job description
        description_source: Source URL for description
        requirements: Job requirements
        requirements_source: Source URL for requirements
        posted_date: Date job was posted
        posted_date_source: Source URL for posting date
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_job_offers"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Title (translatable)
    title = Column(Text, nullable=True)
    title_source = Column(Text, nullable=True)

    # Location (not translatable - place name)
    location = Column(Text, nullable=True)
    location_source = Column(Text, nullable=True)

    # Department (translatable)
    department = Column(Text, nullable=True)
    department_source = Column(Text, nullable=True)

    # Description (translatable)
    description = Column(Text, nullable=True)
    description_source = Column(Text, nullable=True)

    # Requirements (translatable)
    requirements = Column(Text, nullable=True)
    requirements_source = Column(Text, nullable=True)

    # Posted date (not translatable - date)
    posted_date = Column(Text, nullable=True)
    posted_date_source = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="job_offers")


class CompanyCsrInitiative(Base):
    """CSR initiative for a company.

    Represents a corporate social responsibility initiative categorized
    by type (sustainability, charity, diversity, etc.).

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        type: Initiative type (from CsrInitiativeType enum)
        value: Initiative description
        value_source: Source URL for initiative info
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_csr_initiatives"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Type (ENUM)
    type = Column(
        Enum(
            CsrInitiativeType,
            values_callable=lambda obj: [e.value for e in obj],
            name="csr_initiative_type_enum",
            schema=SCREEN_SCHEMA,
        ),
        nullable=False,
    )

    # Value with source (translatable)
    value = Column(Text, nullable=True)
    value_source = Column(Text, nullable=True)

    # Title and date (extracted by LLM)
    title = Column(String(500), nullable=True)
    date = Column(Date, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="csr_initiatives")


class CompanyPressItem(Base):
    """Press item for a company.

    Represents a press coverage item categorized by type
    (article, press release, award, etc.).

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        type: Press item type (from PressItemType enum)
        value: Press item description or headline
        value_source: Source URL for press item
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_press_items"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Type (ENUM)
    type = Column(
        Enum(
            PressItemType,
            values_callable=lambda obj: [e.value for e in obj],
            name="press_item_type_enum",
            schema=SCREEN_SCHEMA,
        ),
        nullable=False,
    )

    # Value with source (translatable)
    value = Column(Text, nullable=True)
    value_source = Column(Text, nullable=True)

    # Title and date (extracted by LLM)
    title = Column(String(500), nullable=True)
    date = Column(Date, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="press_items")


class CompanyTeamMember(Base):
    """Team member for a company.

    Represents a member of the company's leadership team with self-referential
    relationship for hierarchy (adjacency list pattern).
    CEO has parent_id=NULL, direct reports reference their manager's id.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        parent_id: Self-referential FK for hierarchy (NULL for CEO)
        position: Job title/position
        position_source: Source URL for position info
        first_name: First name
        first_name_source: Source URL for first name
        last_name: Last name
        last_name_source: Source URL for last name
        linkedin_url: LinkedIn profile URL
        linkedin_url_source: Source URL where LinkedIn was found
        created_at: Record creation timestamp
        company: Relationship to parent Company model
        parent: Self-referential relationship to manager
        subordinates: List of direct reports
    """

    __tablename__ = "company_team_members"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Self-referential FK for hierarchy (adjacency list pattern)
    # NULL means top-level (CEO), otherwise points to manager
    parent_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.company_team_members.id", ondelete="SET NULL"), nullable=True, index=True
    )

    # Position (translatable)
    position = Column(Text, nullable=True)
    position_source = Column(Text, nullable=True)

    # First name (proper noun - not translatable)
    first_name = Column(Text, nullable=True)
    first_name_source = Column(Text, nullable=True)

    # Last name (proper noun - not translatable)
    last_name = Column(Text, nullable=True)
    last_name_source = Column(Text, nullable=True)

    # LinkedIn URL (not translatable)
    linkedin_url = Column(Text, nullable=True)
    linkedin_url_source = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="team_members")

    # Self-referential relationships for hierarchy
    # Parent relationship (manager)
    parent = relationship(
        "CompanyTeamMember", remote_side=[id], back_populates="subordinates", foreign_keys=[parent_id]
    )

    # Subordinates relationship (direct reports)
    subordinates = relationship("CompanyTeamMember", back_populates="parent", foreign_keys=[parent_id])


class CompanyCorporateEntity(Base):
    """Corporate structure entity linked to a company (parent, subsidiary, affiliate, etc.).

    Stores entities extracted from WorldCheck, Pappers, or web search that form
    the corporate group structure of a company.

    Attributes:
        id: Primary key
        company_id: Foreign key to parent company
        type: Relationship type (parent, subsidiary, affiliate, branch, regional_entity)
        name: Entity name
        name_source: Source URL for the entity name
        country: Country where the entity is located
        country_source: Source URL for the country information
        source: Data provenance system ("worldcheck", "pappers", or "web")
        wc_reference_id: WorldCheck reference ID (for future TAR-1419 integration)
        match_strength: Match confidence from enrichment data (for future TAR-1419 integration)
        created_at: Record creation timestamp
    """

    __tablename__ = "company_corporate_entities"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )

    # Relationship type
    type = Column(
        Enum(
            CorporateRelationshipType,
            values_callable=lambda obj: [e.value for e in obj],
            name="corporate_relationship_type_enum",
            schema=SCREEN_SCHEMA,
        ),
        nullable=False,
    )

    # Entity name with source
    name = Column(Text, nullable=False)
    name_source = Column(Text, nullable=True)

    # Country with source
    country = Column(Text, nullable=True)
    country_source = Column(Text, nullable=True)

    # Data provenance: "worldcheck", "pappers", or "web"
    source = Column(Text, nullable=True)

    # WorldCheck fields (reserved for TAR-1419 integration)
    wc_reference_id = Column(Text, nullable=True)
    match_strength = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="corporate_entities")


class CompanySanctionItem(Base):
    """Individual sanction/enforcement record for a company.

    Stores sanctions, regulatory enforcement actions, and compliance issues
    extracted from WorldCheck screening data.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table
        entity_name: Name of the sanctioned/enforcement entity
        country: Country of the enforcement action
        sanction_nature: Nature of the sanction (e.g. "Regulatory Enforcement")
        description: Detailed description of the sanction
        source_code: WorldCheck source code (e.g. "FRAC")
        sanction_type: Categorized type of sanction
        date: Date of the sanction or enforcement action
        weblinks: Array of reference URLs
        is_onu_eu_ofac: Whether this is an ONU/EU/OFAC sanction list entry
        risk_level: Assessed risk level for this item
        risk_justification: Explanation of the risk assessment
        created_at: Record creation timestamp
    """

    __tablename__ = "company_sanction_items"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )

    entity_name = Column(Text, nullable=False)
    country = Column(Text, nullable=True)
    sanction_nature = Column(Text, nullable=True)
    description = Column(Text, nullable=True)
    source_code = Column(Text, nullable=True)

    sanction_type = Column(
        Enum(
            SanctionType,
            values_callable=lambda obj: [e.value for e in obj],
            name="sanction_type_enum",
            schema=SCREEN_SCHEMA,
        ),
        nullable=True,
    )

    date = Column(Text, nullable=True)
    weblinks = Column(ARRAY(Text), nullable=True)
    is_onu_eu_ofac = Column(Boolean, default=False, nullable=False)

    risk_level = Column(
        Enum(
            RiskLevel,
            values_callable=lambda obj: [e.value for e in obj],
            name="risk_level_enum",
            schema=SCREEN_SCHEMA,
        ),
        nullable=True,
    )

    risk_justification = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="sanction_items")
