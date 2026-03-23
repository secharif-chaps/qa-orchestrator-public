"""Add name column to chapse_conversation_context

Revision ID: 031
Revises: 030
Create Date: 2026-03-19

Adds an optional name column for user-facing conversation names.
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic.
revision = "031"
down_revision = "030"
branch_labels = None
depends_on = None

SCHEMA = "screen_schema"
TABLE = "chapse_conversation_context"


def upgrade() -> None:
    """Add name column."""
    op.add_column(
        TABLE,
        sa.Column("name", sa.String(255), nullable=True),
        schema=SCHEMA,
    )


def downgrade() -> None:
    """Remove name column."""
    op.drop_column(TABLE, "name", schema=SCHEMA)
