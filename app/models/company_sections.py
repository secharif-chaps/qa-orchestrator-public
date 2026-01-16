"""SQLAlchemy models for company section data.

This module defines 1:1 section models for normalized company data storage.
Each section model represents a specific aspect of company information
with SourcedValue pattern support (value, source columns).

Models:
- CompanyProfile: General company profile information
- CompanyDigital: Digital strategy and presence
- CompanyTimeline: Timeline insights (events are 1:N in separate model)
- CompanyProducts: Product portfolio insights
- CompanyJobs: Job market insights
- CompanyCsr: Corporate social responsibility
- CompanyPress: Press coverage insights

All models have 1:1 relationship with Company using company_id as PK and FK.
"""

from sqlalchemy import Column, Integer, String, Text, ForeignKey, DateTime
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import Base


class CompanyProfile(Base):
    """Company profile section with general information.

    Contains core company information like group name, business line,
    establishment year, employee count, revenue, CEO, and headquarters.
    Each field follows the SourcedValue pattern where applicable.

    Attributes:
        company_id: Primary key and foreign key to companies table
        insights: AI-generated summary of company profile
        insights_source: Source of insights (typically "Chaps-e")
        group_name: Parent group or holding company name
        group_name_source: Source URL for group name
        business_line: Main business activity description
        business_line_source: Source URL for business line
        catchphrase: Company slogan or tagline
        catchphrase_source: Source URL for catchphrase
        establishment_year: Year company was founded
        establishment_year_source: Source URL for establishment year
        employee_count: Number of employees
        employee_count_source: Source URL for employee count
        revenue: Annual revenue
        revenue_source: Source URL for revenue
        ceo: Name of CEO or top executive
        ceo_source: Source URL for CEO info
        hq: Headquarters location
        hq_source: Source URL for HQ info
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_profile"

    # Primary key is also foreign key - enforces 1:1 relationship
    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        primary_key=True
    )

    # Insights (translatable)
    insights = Column(Text, nullable=True)
    insights_source = Column(Text, nullable=True)

    # Group name (proper noun - no translation)
    group_name = Column(Text, nullable=True)
    group_name_source = Column(Text, nullable=True)

    # Business line (translatable)
    business_line = Column(Text, nullable=True)
    business_line_source = Column(Text, nullable=True)

    # Catchphrase (translatable)
    catchphrase = Column(Text, nullable=True)
    catchphrase_source = Column(Text, nullable=True)

    # Establishment year (number - no translation)
    establishment_year = Column(Text, nullable=True)
    establishment_year_source = Column(Text, nullable=True)

    # Employee count (number - no translation)
    employee_count = Column(Text, nullable=True)
    employee_count_source = Column(Text, nullable=True)

    # Revenue (number - no translation)
    revenue = Column(Text, nullable=True)
    revenue_source = Column(Text, nullable=True)

    # CEO (proper noun - no translation)
    ceo = Column(Text, nullable=True)
    ceo_source = Column(Text, nullable=True)

    # Headquarters (location - no translation)
    hq = Column(Text, nullable=True)
    hq_source = Column(Text, nullable=True)

    # Timestamps
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )
    updated_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False
    )

    # Relationship to Company (bidirectional with back_populates)
    company = relationship("Company", back_populates="profile_data")


class CompanyDigital(Base):
    """Company digital strategy section.

    Contains digital transformation and online presence information
    including overall strategy, e-commerce, mobile, and marketing.
    Child tables (online_services, social_media) are defined separately.

    Attributes:
        company_id: Primary key and foreign key to companies table
        insights: AI-generated digital strategy summary
        insights_source: Source of insights
        overall_strategy: Overall digital strategy description
        overall_strategy_source: Source URL
        digital_transformation: Digital transformation initiatives
        digital_transformation_source: Source URL
        ecommerce_capabilities: E-commerce platform capabilities
        ecommerce_capabilities_source: Source URL
        mobile_strategy: Mobile app and presence strategy
        mobile_strategy_source: Source URL
        digital_marketing_approach: Digital marketing approach
        digital_marketing_approach_source: Source URL
        loyalty_program: Loyalty program description
        loyalty_program_source: Source URL
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_digital"

    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        primary_key=True
    )

    # Insights (translatable)
    insights = Column(Text, nullable=True)
    insights_source = Column(Text, nullable=True)

    # Overall strategy (translatable)
    overall_strategy = Column(Text, nullable=True)
    overall_strategy_source = Column(Text, nullable=True)

    # Digital transformation (translatable)
    digital_transformation = Column(Text, nullable=True)
    digital_transformation_source = Column(Text, nullable=True)

    # E-commerce capabilities (translatable)
    ecommerce_capabilities = Column(Text, nullable=True)
    ecommerce_capabilities_source = Column(Text, nullable=True)

    # Mobile strategy (translatable)
    mobile_strategy = Column(Text, nullable=True)
    mobile_strategy_source = Column(Text, nullable=True)

    # Digital marketing approach (translatable)
    digital_marketing_approach = Column(Text, nullable=True)
    digital_marketing_approach_source = Column(Text, nullable=True)

    # Loyalty program (translatable)
    loyalty_program = Column(Text, nullable=True)
    loyalty_program_source = Column(Text, nullable=True)

    # Timestamps
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )
    updated_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False
    )

    # Relationship to Company
    company = relationship("Company", back_populates="digital_data")


class CompanyTimeline(Base):
    """Company timeline section with insights.

    Contains timeline insights summary. Individual timeline events
    are stored in a separate 1:N table (CompanyTimelineEvent).

    Attributes:
        company_id: Primary key and foreign key to companies table
        insights: AI-generated timeline summary
        insights_source: Source of insights
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_timeline"

    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        primary_key=True
    )

    # Insights (translatable)
    insights = Column(Text, nullable=True)
    insights_source = Column(Text, nullable=True)

    # Timestamps
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )
    updated_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False
    )

    # Relationship to Company
    company = relationship("Company", back_populates="timeline_data")


class CompanyProducts(Base):
    """Company products section with portfolio insights.

    Contains product portfolio insights, customer type, and marketing
    positioning. Individual products are in 1:N tables (CompanyProductItem,
    CompanyProductCategory).

    Attributes:
        company_id: Primary key and foreign key to companies table
        insights: AI-generated products summary
        insights_source: Source of insights
        customer_type: Target customer description
        customer_type_source: Source URL
        marketing_positioning: Market positioning strategy
        marketing_positioning_source: Source URL
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_products"

    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        primary_key=True
    )

    # Insights (translatable)
    insights = Column(Text, nullable=True)
    insights_source = Column(Text, nullable=True)

    # Customer type (translatable)
    customer_type = Column(Text, nullable=True)
    customer_type_source = Column(Text, nullable=True)

    # Marketing positioning (translatable)
    marketing_positioning = Column(Text, nullable=True)
    marketing_positioning_source = Column(Text, nullable=True)

    # Timestamps
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )
    updated_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False
    )

    # Relationship to Company
    company = relationship("Company", back_populates="products_data")


class CompanyJobs(Base):
    """Company jobs section with hiring insights.

    Contains structured job market insights including total openings,
    top departments, hiring focus, and growth indicators.
    Individual job offers are in 1:N table (CompanyJobOffer).

    Attributes:
        company_id: Primary key and foreign key to companies table
        insights_total_openings: Total number of job openings (integer)
        insights_total_openings_source: Source URL
        insights_top_departments: Most active hiring departments
        insights_top_departments_source: Source URL
        insights_hiring_focus: Current hiring priorities
        insights_hiring_focus_source: Source URL
        insights_growth_indicators: Hiring growth indicators
        insights_growth_indicators_source: Source URL
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_jobs"

    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        primary_key=True
    )

    # Total openings (number - no translation)
    insights_total_openings = Column(Integer, nullable=True)
    insights_total_openings_source = Column(Text, nullable=True)

    # Top departments (translatable)
    insights_top_departments = Column(Text, nullable=True)
    insights_top_departments_source = Column(Text, nullable=True)

    # Hiring focus (translatable)
    insights_hiring_focus = Column(Text, nullable=True)
    insights_hiring_focus_source = Column(Text, nullable=True)

    # Growth indicators (translatable)
    insights_growth_indicators = Column(Text, nullable=True)
    insights_growth_indicators_source = Column(Text, nullable=True)

    # Timestamps
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )
    updated_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False
    )

    # Relationship to Company
    company = relationship("Company", back_populates="jobs_data")


class CompanyCsr(Base):
    """Company CSR (Corporate Social Responsibility) section.

    Contains CSR insights and responsibility statement.
    Individual initiatives are in 1:N table (CompanyCsrInitiative).

    Attributes:
        company_id: Primary key and foreign key to companies table
        insights: AI-generated CSR summary
        insights_source: Source of insights
        responsibility: General responsibility statement
        responsibility_source: Source URL
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_csr"

    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        primary_key=True
    )

    # Insights (translatable)
    insights = Column(Text, nullable=True)
    insights_source = Column(Text, nullable=True)

    # Responsibility (translatable)
    responsibility = Column(Text, nullable=True)
    responsibility_source = Column(Text, nullable=True)

    # Timestamps
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )
    updated_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False
    )

    # Relationship to Company
    company = relationship("Company", back_populates="csr_data")


class CompanyPress(Base):
    """Company press coverage section.

    Contains press/media insights summary.
    Individual press items are in 1:N table (CompanyPressItem).

    Attributes:
        company_id: Primary key and foreign key to companies table
        insights: AI-generated press summary
        insights_source: Source of insights
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        company: Relationship to parent Company model
    """
    __tablename__ = "company_press"

    company_id = Column(
        Integer,
        ForeignKey("companies.id", ondelete="CASCADE"),
        primary_key=True
    )

    # Insights (translatable)
    insights = Column(Text, nullable=True)
    insights_source = Column(Text, nullable=True)

    # Timestamps
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )
    updated_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
        nullable=False
    )

    # Relationship to Company
    company = relationship("Company", back_populates="press_data")
