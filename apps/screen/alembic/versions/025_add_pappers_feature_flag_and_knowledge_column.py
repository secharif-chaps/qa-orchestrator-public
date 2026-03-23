"""Add pappers to featureflag enum and companies table

Revision ID: 025
Revises: 024
Create Date: 2026-02-05

This migration adds the 'pappers' value to the featureflag PostgreSQL enum type
and adds the raw_pappers_knowledge column to the companies table.
The PAPPERS feature flag allows organizations to configure a Pappers API key
for enriching company data with French business registry information.
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic.
revision = "025"
down_revision = "024"
branch_labels = None
depends_on = None


def upgrade():
    # Add PAPPERS value to FeatureFlag enum
    op.execute("ALTER TYPE featureflag ADD VALUE IF NOT EXISTS 'pappers'")

    # Add raw_pappers_knowledge column to companies table
    op.add_column("companies", sa.Column("raw_pappers_knowledge", sa.String(), nullable=True))


def downgrade():
    # Remove the column
    op.drop_column("companies", "raw_pappers_knowledge")

    # Remove 'pappers' value from FeatureFlag enum
    # PostgreSQL doesn't support DROP VALUE, so we need to recreate the enum
    pass
