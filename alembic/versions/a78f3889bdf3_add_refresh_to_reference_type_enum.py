"""add refresh to reference_type enum

Revision ID: a78f3889bdf3
Revises: 020
Create Date: 2026-01-19 17:02:53.911909

"""
from alembic import op


# revision identifiers, used by Alembic.
revision = 'a78f3889bdf3'
down_revision = '020'
branch_labels = None
depends_on = None


def upgrade():
    # Add 'refresh' value to the reference_type enum
    op.execute("ALTER TYPE reference_type ADD VALUE IF NOT EXISTS 'refresh'")


def downgrade():
    # PostgreSQL doesn't support removing enum values directly
    # The 'refresh' value will remain in the enum but won't be used
    pass
