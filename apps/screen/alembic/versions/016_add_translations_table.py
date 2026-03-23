"""Add translations table for multi-language support

Revision ID: 016
Revises: 015
Create Date: 2025-01-06

Creates a normalized translations table to store field translations
across multiple languages. This replaces the column-based approach
(e.g., _value_fr columns) for languages other than French.

The table stores translations for any translatable field in company
section tables, enabling easy addition of new languages without
schema changes.
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic
revision = "016"
down_revision = "015"
branch_labels = None
depends_on = None


def upgrade():
    """Create translations table."""

    print("Creating translations table...")
    op.create_table(
        "translations",
        sa.Column("id", sa.Integer(), autoincrement=True, nullable=False),
        sa.Column("company_id", sa.Integer(), nullable=False),
        sa.Column("table_name", sa.String(100), nullable=False),
        sa.Column("record_id", sa.Integer(), nullable=False),
        sa.Column("field_name", sa.String(100), nullable=False),
        sa.Column("language_code", sa.String(5), nullable=False),
        sa.Column("value", sa.Text(), nullable=True),
        sa.Column("source_value_hash", sa.String(64), nullable=True),
        sa.Column("translated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.PrimaryKeyConstraint("id"),
        sa.ForeignKeyConstraint(["company_id"], ["companies.id"], ondelete="CASCADE"),
    )

    # Create indexes
    print("Creating indexes...")

    # Index for company_id (for filtering by company)
    op.create_index("ix_translations_company_id", "translations", ["company_id"])

    # Unique composite index for lookup (one translation per field/language/record)
    op.create_index(
        "ix_translations_lookup",
        "translations",
        ["table_name", "record_id", "field_name", "language_code"],
        unique=True,
    )

    # Index for querying by company and language
    op.create_index("ix_translations_company_language", "translations", ["company_id", "language_code"])

    print("Translations table created successfully!")


def downgrade():
    """Drop translations table."""

    print("Dropping translations table...")

    # Drop indexes first
    op.drop_index("ix_translations_company_language", table_name="translations")
    op.drop_index("ix_translations_lookup", table_name="translations")
    op.drop_index("ix_translations_company_id", table_name="translations")

    # Drop table
    op.drop_table("translations")

    print("Translations table dropped successfully!")
