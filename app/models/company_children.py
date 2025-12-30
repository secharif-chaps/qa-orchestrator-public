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

All models have 1:N relationship with Company via company_id foreign key.
"""

import enum
from sqlalchemy import (
    Column,
    Integer,
    String,
    Text,
    ForeignKey,
    DateTime,
    Enum,
    Index,
)
from sqlalchemy.dialects.postgresql import ARRAY
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import Base


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
        name_value_fr: French translation placeholder
        description: Service description
        description_source: Source URL for description
        description_value_fr: French translation placeholder
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_online_services"

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # Name (translatable)
    name = Column(Text, nullable=True)
    name_source = Column(Text, nullable=True)
    name_value_fr = Column(Text, nullable=True)

    # Description (translatable)
    description = Column(Text, nullable=True)
    description_source = Column(Text, nullable=True)
    description_value_fr = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

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

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # Platform (not translatable - proper noun)
    platform = Column(Text, nullable=True)
    platform_source = Column(Text, nullable=True)

    # URL (not translatable)
    url = Column(Text, nullable=True)
    url_source = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

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
        title_value_fr: French translation placeholder
        description: Event description
        description_source: Source URL for description
        description_value_fr: French translation placeholder
        category: Event category (e.g., Foundation, Acquisition)
        category_source: Source URL for category
        category_value_fr: French translation placeholder
        location: Event location
        location_source: Source URL for location
        impact: Event impact description
        impact_source: Source URL for impact
        impact_value_fr: French translation placeholder
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_timeline_events"

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # Date (not translatable - number/date)
    date = Column(Text, nullable=True)
    date_source = Column(Text, nullable=True)

    # Title (translatable)
    title = Column(Text, nullable=True)
    title_source = Column(Text, nullable=True)
    title_value_fr = Column(Text, nullable=True)

    # Description (translatable)
    description = Column(Text, nullable=True)
    description_source = Column(Text, nullable=True)
    description_value_fr = Column(Text, nullable=True)

    # Category (translatable)
    category = Column(Text, nullable=True)
    category_source = Column(Text, nullable=True)
    category_value_fr = Column(Text, nullable=True)

    # Location (not translatable - proper noun/place)
    location = Column(Text, nullable=True)
    location_source = Column(Text, nullable=True)

    # Impact (translatable)
    impact = Column(Text, nullable=True)
    impact_source = Column(Text, nullable=True)
    impact_value_fr = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

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
        value_value_fr: French translation (only for 'range' type)
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_product_items"

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # Type (ENUM)
    type = Column(
        Enum(
            ProductItemType,
            values_callable=lambda obj: [e.value for e in obj],
            name="product_item_type_enum"
        ),
        nullable=False
    )

    # Value with source (translation only for 'range' type)
    value = Column(Text, nullable=True)
    value_source = Column(Text, nullable=True)
    value_value_fr = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

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
        category_name_value_fr: French translation placeholder
        items: Array of product item names
        items_value_fr: French translations for items array
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_product_categories"

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # Category name (translatable)
    category_name = Column(Text, nullable=True)
    category_name_value_fr = Column(Text, nullable=True)

    # Items array (translatable)
    items = Column(ARRAY(Text), nullable=True)
    items_value_fr = Column(ARRAY(Text), nullable=True)

    # Timestamp
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

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
        title_value_fr: French translation placeholder
        location: Job location
        location_source: Source URL for location
        department: Department name
        department_source: Source URL for department
        department_value_fr: French translation placeholder
        description: Job description
        description_source: Source URL for description
        description_value_fr: French translation placeholder
        requirements: Job requirements
        requirements_source: Source URL for requirements
        requirements_value_fr: French translation placeholder
        posted_date: Date job was posted
        posted_date_source: Source URL for posting date
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_job_offers"

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # Title (translatable)
    title = Column(Text, nullable=True)
    title_source = Column(Text, nullable=True)
    title_value_fr = Column(Text, nullable=True)

    # Location (not translatable - place name)
    location = Column(Text, nullable=True)
    location_source = Column(Text, nullable=True)

    # Department (translatable)
    department = Column(Text, nullable=True)
    department_source = Column(Text, nullable=True)
    department_value_fr = Column(Text, nullable=True)

    # Description (translatable)
    description = Column(Text, nullable=True)
    description_source = Column(Text, nullable=True)
    description_value_fr = Column(Text, nullable=True)

    # Requirements (translatable)
    requirements = Column(Text, nullable=True)
    requirements_source = Column(Text, nullable=True)
    requirements_value_fr = Column(Text, nullable=True)

    # Posted date (not translatable - date)
    posted_date = Column(Text, nullable=True)
    posted_date_source = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

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
        value_value_fr: French translation placeholder
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_csr_initiatives"

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # Type (ENUM)
    type = Column(
        Enum(
            CsrInitiativeType,
            values_callable=lambda obj: [e.value for e in obj],
            name="csr_initiative_type_enum"
        ),
        nullable=False
    )

    # Value with source (translatable)
    value = Column(Text, nullable=True)
    value_source = Column(Text, nullable=True)
    value_value_fr = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

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
        value_value_fr: French translation placeholder
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_press_items"

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # Type (ENUM)
    type = Column(
        Enum(
            PressItemType,
            values_callable=lambda obj: [e.value for e in obj],
            name="press_item_type_enum"
        ),
        nullable=False
    )

    # Value with source (translatable)
    value = Column(Text, nullable=True)
    value_source = Column(Text, nullable=True)
    value_value_fr = Column(Text, nullable=True)

    # Timestamp
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

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
        position_value_fr: French translation placeholder
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

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # Self-referential FK for hierarchy (adjacency list pattern)
    # NULL means top-level (CEO), otherwise points to manager
    parent_id = Column(
        Integer,
        ForeignKey("company_team_members.id", ondelete="SET NULL"),
        nullable=True,
        index=True
    )

    # Position (translatable)
    position = Column(Text, nullable=True)
    position_source = Column(Text, nullable=True)
    position_value_fr = Column(Text, nullable=True)

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
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

    # Relationship to Company
    company = relationship("Company", back_populates="team_members")

    # Self-referential relationships for hierarchy
    # Parent relationship (manager)
    parent = relationship(
        "CompanyTeamMember",
        remote_side=[id],
        back_populates="subordinates",
        foreign_keys=[parent_id]
    )

    # Subordinates relationship (direct reports)
    subordinates = relationship(
        "CompanyTeamMember",
        back_populates="parent",
        foreign_keys=[parent_id]
    )
