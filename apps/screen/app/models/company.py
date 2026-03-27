"""Company model for storing company information.

This model serves as the central entity for company data with:
- Core fields (name, website, organization, owner)
- Raw knowledge fields from data collection (preserved)
- 1:1 relationships to normalized section tables
- 1:N relationships to child tables (online services, events, items, etc.)
- 1:N relationship to tasks

After Task Group 11 cleanup, all section data is stored in normalized tables:
- company_profile, company_digital, company_timeline, etc. (1:1 sections)
- company_online_services, company_team_members, etc. (1:N children)

Legacy JSON columns (profile, digital, timeline, products, jobs, csr, press, team)
have been removed. Use the relationships to access section data.
"""

from sqlalchemy import Boolean, Column, DateTime, Integer, String
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import SCREEN_SCHEMA, Base


class Company(Base):
    """Company model representing a monitored company.

    Attributes:
        id: Primary key
        name: Company name
        website: Company website URL
        organization_id: Keycloak organization UUID for multi-tenancy
        owner_id: Keycloak user UUID who created the company
        owner_username: Username for display (denormalized)
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        is_deleted: Soft delete flag
        raw_worldcheck_knowledge: Raw knowledge from WorldCheck One (sanctions, PEP, adverse media)
        error: Error message if data collection failed
        tasks: Relationship to Task model (1:N)
        profile_data: Relationship to CompanyProfile (1:1)
        digital_data: Relationship to CompanyDigital (1:1)
        timeline_data: Relationship to CompanyTimeline (1:1)
        products_data: Relationship to CompanyProducts (1:1)
        jobs_data: Relationship to CompanyJobs (1:1)
        csr_data: Relationship to CompanyCsr (1:1)
        press_data: Relationship to CompanyPress (1:1)
        online_services: Relationship to CompanyOnlineService (1:N)
        social_media_accounts: Relationship to CompanySocialMediaAccount (1:N)
        timeline_events: Relationship to CompanyTimelineEvent (1:N)
        product_items: Relationship to CompanyProductItem (1:N)
        product_categories: Relationship to CompanyProductCategory (1:N)
        job_offers: Relationship to CompanyJobOffer (1:N)
        csr_initiatives: Relationship to CompanyCsrInitiative (1:N)
        press_items: Relationship to CompanyPressItem (1:N)
        team_members: Relationship to CompanyTeamMember (1:N)
    """

    __tablename__ = "companies"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, index=True, nullable=False)
    website = Column(String, index=True, nullable=False)

    # Organization-based multi-tenancy via Keycloak Organizations
    organization_id = Column(String, index=True, nullable=False)  # Keycloak organization UUID

    # Owner fields - Keycloak user identification
    owner_id = Column(String, index=True, nullable=True)  # Keycloak user UUID (from JWT sub claim)
    owner_username = Column(String, index=True, nullable=True)  # Username for display (denormalized)

    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)
    is_deleted = Column(Boolean, default=False, nullable=False)

    # WorldCheck One knowledge (sanctions, PEP, adverse media) - separate ingestion flow
    raw_worldcheck_knowledge = Column(String, nullable=True)

    # Error field
    error = Column(String, nullable=True)

    # Relationships to Task model (1:N)
    tasks = relationship("Task", back_populates="company", cascade="all, delete-orphan")

    # 1:1 Relationships to normalized section tables
    # Using uselist=False enforces 1:1 relationship
    # cascade="all, delete-orphan" ensures section data is deleted with company
    profile_data = relationship("CompanyProfile", back_populates="company", uselist=False, cascade="all, delete-orphan")
    digital_data = relationship("CompanyDigital", back_populates="company", uselist=False, cascade="all, delete-orphan")
    timeline_data = relationship(
        "CompanyTimeline", back_populates="company", uselist=False, cascade="all, delete-orphan"
    )
    products_data = relationship(
        "CompanyProducts", back_populates="company", uselist=False, cascade="all, delete-orphan"
    )
    jobs_data = relationship("CompanyJobs", back_populates="company", uselist=False, cascade="all, delete-orphan")
    csr_data = relationship("CompanyCsr", back_populates="company", uselist=False, cascade="all, delete-orphan")
    press_data = relationship("CompanyPress", back_populates="company", uselist=False, cascade="all, delete-orphan")

    # 1:N Relationships to child tables
    # cascade="all, delete-orphan" ensures child records are deleted with company
    online_services = relationship("CompanyOnlineService", back_populates="company", cascade="all, delete-orphan")
    social_media_accounts = relationship(
        "CompanySocialMediaAccount", back_populates="company", cascade="all, delete-orphan"
    )
    timeline_events = relationship("CompanyTimelineEvent", back_populates="company", cascade="all, delete-orphan")
    product_items = relationship("CompanyProductItem", back_populates="company", cascade="all, delete-orphan")
    product_categories = relationship("CompanyProductCategory", back_populates="company", cascade="all, delete-orphan")
    job_offers = relationship("CompanyJobOffer", back_populates="company", cascade="all, delete-orphan")
    csr_initiatives = relationship("CompanyCsrInitiative", back_populates="company", cascade="all, delete-orphan")
    press_items = relationship("CompanyPressItem", back_populates="company", cascade="all, delete-orphan")
    team_members = relationship("CompanyTeamMember", back_populates="company", cascade="all, delete-orphan")

    # Enrichment data from external APIs (1:N)
    enrichments = relationship("CompanyEnrichment", back_populates="company", cascade="all, delete-orphan")

    # Translations relationship (1:N)
    translations = relationship("Translation", back_populates="company", cascade="all, delete-orphan")
