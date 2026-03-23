"""SQLAlchemy model for translations.

This module defines the Translation model for storing field translations
across multiple languages using a normalized table structure.

The Translation model stores translations for any translatable field
in the company data structure, allowing easy addition of new languages
without schema changes.
"""

from sqlalchemy import Column, DateTime, ForeignKey, Index, Integer, String, Text
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import SCREEN_SCHEMA, Base


class Translation(Base):
    """Translation record for a company field.

    Stores translations for translatable fields across multiple tables
    and languages. Each record represents one field's translation in
    one language for one record.

    Attributes:
        id: Primary key
        company_id: Foreign key to companies table (for easy querying)
        table_name: Source table name (e.g., "company_profile")
        record_id: Primary key of the source record
        field_name: Field being translated (e.g., "insights", "business_line")
        language_code: ISO language code (e.g., "es", "de", "pt")
        value: Translated text value
        source_value_hash: Hash of source value to detect changes
        translated_at: Timestamp of translation
        created_at: Record creation timestamp
        updated_at: Record last update timestamp
    """

    __tablename__ = "translations"

    id = Column(Integer, primary_key=True, autoincrement=True)

    # Link to company for easy status queries
    company_id = Column(
        Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id", ondelete="CASCADE"), nullable=False, index=True
    )

    # Source record identification
    table_name = Column(String(100), nullable=False)
    record_id = Column(Integer, nullable=False)
    field_name = Column(String(100), nullable=False)

    # Translation data
    language_code = Column(String(5), nullable=False)
    value = Column(Text, nullable=True)

    # Track source changes (to know if re-translation needed)
    source_value_hash = Column(String(64), nullable=True)

    # Timestamps
    translated_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    # Relationship to Company
    company = relationship("Company", back_populates="translations")

    # Unique constraint: one translation per field/language/record
    __table_args__ = (
        Index("ix_translations_lookup", "table_name", "record_id", "field_name", "language_code", unique=True),
        Index("ix_translations_company_language", "company_id", "language_code"),
        {"schema": SCREEN_SCHEMA},
    )

    def __repr__(self) -> str:
        return (
            f"<Translation(id={self.id}, company_id={self.company_id}, "
            f"table={self.table_name}, field={self.field_name}, "
            f"lang={self.language_code})>"
        )
