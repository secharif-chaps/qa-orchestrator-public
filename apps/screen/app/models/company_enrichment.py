"""Company enrichment model for storing external API data.

Stores pre-fetched structured data from external APIs (Pappers, WorldCheck)
collected by the data_collector LangGraph node. Agents access this data
via the get_enrichment_data function tool.
"""

from sqlalchemy import Column, DateTime, ForeignKey, Integer, String
from sqlalchemy.dialects.postgresql import JSONB
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import SCREEN_SCHEMA, Base


class CompanyEnrichment(Base):
    """Enrichment data from an external API for a company.

    Attributes:
        id: Primary key
        company_id: FK to companies table
        source: Data source identifier ("pappers", "worldcheck")
        data: Raw API response as JSON
        status: Collection status ("pending", "success", "error", "skipped")
        error: Error message if collection failed
        fetched_at: When the data was fetched from the API
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
    """

    __tablename__ = "company_enrichments"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, index=True)
    company_id = Column(
        Integer,
        ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    source = Column(String, nullable=False)
    data = Column(JSONB, nullable=True)
    status = Column(String, nullable=False, default="pending")
    error = Column(String, nullable=True)
    fetched_at = Column(DateTime(timezone=True), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    # Relationship back to Company
    company = relationship("Company", back_populates="enrichments")
