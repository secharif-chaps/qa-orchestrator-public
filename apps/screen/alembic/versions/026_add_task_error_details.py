"""Add error_details JSON column to tasks table

Revision ID: 026
Revises: 025
Create Date: 2026-03-09

Stores structured Dify error information (error_type, is_recoverable,
retry_after_seconds, recommended_action) for contextualized frontend display (TAR-1172).
"""
import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic.
revision = "026"
down_revision = "025"
branch_labels = None
depends_on = None


def upgrade():
    op.add_column("tasks", sa.Column("error_details", sa.JSON(), nullable=True))


def downgrade():
    op.drop_column("tasks", "error_details")
