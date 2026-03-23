"""Add worldcheck to featureflag enum and companies table

Revision ID: 027
Revises: 026
Create Date: 2026-03-10

This migration adds the 'worldcheck' value to the featureflag PostgreSQL enum type
and adds the raw_worldcheck_knowledge column to the companies table.
The WORLDCHECK feature flag allows organizations to configure WorldCheck One API
credentials (API Key + API Secret) for due diligence screening data
(sanctions, PEP, adverse media).
"""


from alembic import op

# revision identifiers, used by Alembic.
revision = "027"
down_revision = "026"
branch_labels = None
depends_on = None


def upgrade():
    # Add WORLDCHECK value to FeatureFlag enum
    op.execute("ALTER TYPE featureflag ADD VALUE IF NOT EXISTS 'worldcheck'")

    # Add raw_worldcheck_knowledge column to companies table (IF NOT EXISTS for idempotency)
    # After migration 026, companies table lives in screen_schema
    op.execute("ALTER TABLE screen_schema.companies ADD COLUMN IF NOT EXISTS raw_worldcheck_knowledge VARCHAR")


def downgrade():
    # Remove the column
    op.drop_column("companies", "raw_worldcheck_knowledge", schema="screen_schema")

    # Remove 'worldcheck' value from FeatureFlag enum
    # PostgreSQL doesn't support DROP VALUE, so we need to recreate the enum
    pass
