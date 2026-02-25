"""006 Create folders table in global_schema

Revision ID: b2c3d4e5f6a7
Revises: cee8c9f92d8e
Create Date: 2026-02-11 10:00:00.000000

This migration creates the folders table in global_schema
as part of US-3.1: Folders Table Migration.
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import UUID


# revision identifiers, used by Alembic.
revision: str = 'b2c3d4e5f6a7'
down_revision: Union[str, Sequence[str], None] = 'cee8c9f92d8e'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

SCHEMA = 'global_schema'


def upgrade() -> None:
    """Create folders table in global_schema."""
    op.create_table(
        'folders',
        sa.Column('id', UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('organization_id', sa.String(), nullable=False),
        sa.Column('owner_id', sa.String(), nullable=True),
        sa.Column('owner', sa.String(), nullable=True),
        sa.Column('name', sa.String(), nullable=False),
        sa.Column('color', sa.String(), nullable=True),
        sa.Column('icon', sa.String(), nullable=True),
        sa.Column('tags', sa.ARRAY(sa.String()), server_default=sa.text("'{}'::text[]"), nullable=False),
        sa.Column('is_deleted', sa.Boolean(), server_default=sa.text('false'), nullable=False),
        sa.Column('is_orphaned', sa.Boolean(), server_default=sa.text('false'), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        schema=SCHEMA,
    )

    # Create indexes with prefixed names to avoid conflicts
    op.create_index('ix_global_folders_organization_id', 'folders', ['organization_id'], schema=SCHEMA)
    op.create_index('ix_global_folders_owner_id', 'folders', ['owner_id'], schema=SCHEMA)
    op.create_index('ix_global_folders_is_deleted', 'folders', ['is_deleted'], schema=SCHEMA)


def downgrade() -> None:
    """Drop folders table from global_schema."""
    op.drop_index('ix_global_folders_is_deleted', table_name='folders', schema=SCHEMA)
    op.drop_index('ix_global_folders_owner_id', table_name='folders', schema=SCHEMA)
    op.drop_index('ix_global_folders_organization_id', table_name='folders', schema=SCHEMA)
    op.drop_table('folders', schema=SCHEMA)
