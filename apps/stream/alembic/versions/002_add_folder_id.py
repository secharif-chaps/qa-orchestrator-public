"""Add folder_id to streams table.

Revision ID: 002_add_folder_id
Revises: 001_create_stream
Create Date: 2026-03-30
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers
revision = "002_add_folder_id"
down_revision = "001_create_stream"
branch_labels = None
depends_on = None

SCHEMA = "stream_schema"


def upgrade() -> None:
    op.add_column(
        "streams",
        sa.Column("folder_id", sa.String(), nullable=False, server_default=""),
        schema=SCHEMA,
    )
    op.create_index("ix_streams_folder_id", "streams", ["folder_id"], schema=SCHEMA)
    # Remove the server_default after backfill
    op.alter_column("streams", "folder_id", server_default=None, schema=SCHEMA)


def downgrade() -> None:
    op.drop_index("ix_streams_folder_id", table_name="streams", schema=SCHEMA)
    op.drop_column("streams", "folder_id", schema=SCHEMA)
