"""Add worldcheck to featureflag enum and companies table

Revision ID: 026
Revises: 025
Create Date: 2026-03-10

This migration adds the 'worldcheck' value to the featureflag PostgreSQL enum type
and adds the raw_worldcheck_knowledge column to the companies table.
The WORLDCHECK feature flag allows organizations to configure WorldCheck One API
credentials (API Key + API Secret) for due diligence screening data
(sanctions, PEP, adverse media).
"""
import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic.
revision = "026"
down_revision = "025"
branch_labels = None
depends_on = None


def upgrade():
    # Add WORLDCHECK value to FeatureFlag enum
    op.execute("ALTER TYPE featureflag ADD VALUE IF NOT EXISTS 'worldcheck'")

    # Add raw_worldcheck_knowledge column to companies table
    op.add_column('companies', sa.Column('raw_worldcheck_knowledge', sa.String(), nullable=True))


def downgrade():
    # Remove the column
    op.drop_column('companies', 'raw_worldcheck_knowledge')

    # Remove 'worldcheck' value from FeatureFlag enum
    # PostgreSQL doesn't support DROP VALUE, so we need to recreate the enum
    pass
