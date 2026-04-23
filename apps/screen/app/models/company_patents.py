"""SQLAlchemy models for the patents section.

Two tables mirroring the existing section / items pattern
(``company_financial`` + ``company_financial_metric``):

- ``CompanyPatents`` (1:1): narrative insights plus aggregate indicators
  produced by the patents agent (Phase 1 programmatic + Phase 2 LLM).
- ``CompanyPatentItem`` (1:N): individual patent publications kept for
  drill-down; one row per ``doc_id`` per company.

CPC codes on items come exclusively from ``epo_families``; when the
companion ``epo_families`` enrichment is unavailable, ``cpc_codes`` is
simply an empty list. The families / legal enrichment extensions
(``geographic_coverage``, ``status_breakdown``, ``portfolio_strength``
on the section; ``family_size``, ``family_countries``, ``legal_status``
on items) mirror the same graceful-degradation contract: empty
aggregates and ``None`` / zero / ``[]`` at the item level when families
or legal are missing.
"""

import enum

from sqlalchemy import (
    ARRAY,
    Boolean,
    Column,
    Date,
    DateTime,
    Enum,
    ForeignKey,
    Integer,
    Text,
    UniqueConstraint,
)
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import SCREEN_SCHEMA, Base


class LegalStatus(enum.StrEnum):
    """Simplified patent legal status.

    Mirrors the four buckets produced by the EPO parser
    (``SimplifiedLegalStatus`` in ``app.infrastructure.epo.schemas``)
    so the agent can persist the status as-is without remapping.
    """

    active = "active"
    expired = "expired"
    pending = "pending"
    unknown = "unknown"


class CompanyPatents(Base):
    """Aggregate patent analysis for a company.

    Uses ``company_id`` as both PK and FK to enforce the 1:1 relationship
    with the companies table — same convention as ``CompanyFinancial``.

    Attributes:
        company_id: Primary key and foreign key to companies table
        insights: LLM-generated narrative summary of the company's
            innovation activity
        total_patents_count: Number of unique publications analysed
        top_cpc_domains: List of ``{code, label, count}`` dicts produced by
            the LLM; empty list when CPC data is unavailable
        filing_trend: Mapping of ``{year: count}`` derived programmatically
            from publication dates; keys are year strings (``"2024"``)
            so the JSONB payload is stable under JSON serialisation
        geographic_coverage: Mapping of ``{country_code: count}`` aggregating
            family members across the portfolio; empty dict when
            ``epo_families`` was not collected
        status_breakdown: Mapping of ``{legal_status: count}`` with the
            four ``LegalStatus`` buckets; empty dict when ``epo_legal``
            was not collected
        portfolio_strength: LLM assessment of portfolio solidity and
            geographic strategy (1-2 paragraphs); ``None`` when families
            AND legal data are both missing (graceful degradation)
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_patents"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    company_id = Column(
        Integer,
        ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"),
        primary_key=True,
    )

    insights = Column(Text, nullable=True)
    total_patents_count = Column(Integer, nullable=False, default=0)
    top_cpc_domains = Column(JSONB, nullable=False, default=list)
    filing_trend = Column(JSONB, nullable=False, default=dict)

    geographic_coverage = Column(JSONB, nullable=False, default=dict)
    status_breakdown = Column(JSONB, nullable=False, default=dict)
    portfolio_strength = Column(Text, nullable=True)

    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    company = relationship("Company", back_populates="patents_data")


class CompanyPatentItem(Base):
    """Individual patent publication retained for drill-down.

    One row per ``(company_id, patent_number)`` — ``patent_number`` is the
    EPO docdb id (e.g. ``EP.1234567.A1``). The unique constraint protects
    the delete-then-insert replace flow used by ``save_patents_data``.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table (indexed)
        patent_number: EPO docdb identifier, unique per company
        title: English-preferred invention title (may be ``None``)
        abstract: Abstract text when available
        inventors: List of inventor names (JSON array)
        applicants: List of applicant / assignee names (JSON array)
        publication_date: Gazette publication date (may be ``None``)
        cpc_codes: CPC classification symbols from ``epo_families``;
            empty list when families data is unavailable for this doc_id
        is_key_patent: True when the LLM flagged this publication as a
            representative / breakthrough patent for the portfolio
        family_size: Number of publications in the international patent
            family, from ``epo_families``; ``0`` when absent
        family_countries: Sorted unique country codes present in the
            family members; empty list when families data is unavailable
        legal_status: Simplified legal status from ``epo_legal``
            (active / expired / pending / unknown); ``None`` when legal
            data is unavailable for this doc_id
        created_at: Record creation timestamp
        company: Relationship to parent Company model
    """

    __tablename__ = "company_patent_item"
    __table_args__ = (
        UniqueConstraint("company_id", "patent_number", name="uq_company_patent_item_doc"),
        {"schema": SCREEN_SCHEMA},
    )

    id = Column(Integer, primary_key=True, autoincrement=True)
    company_id = Column(
        Integer,
        ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )

    patent_number = Column(Text, nullable=False)
    title = Column(Text, nullable=True)
    abstract = Column(Text, nullable=True)
    inventors = Column(JSONB, nullable=False, default=list)
    applicants = Column(JSONB, nullable=False, default=list)
    publication_date = Column(Date, nullable=True)
    cpc_codes = Column(JSONB, nullable=False, default=list)
    is_key_patent = Column(Boolean, nullable=False, default=False)

    family_size = Column(Integer, nullable=False, default=0)
    family_countries = Column(ARRAY(Text), nullable=False, default=list)
    legal_status = Column(
        Enum(
            LegalStatus,
            values_callable=lambda obj: [e.value for e in obj],
            name="patent_legal_status_enum",
            schema=SCREEN_SCHEMA,
        ),
        nullable=True,
    )

    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    company = relationship("Company", back_populates="patent_items")
