"""004 Create folder_items, folder_shares, user_folder_favorites in global_schema

Revision ID: c3d4e5f6a7b8
Revises: b2c3d4e5f6a7
Create Date: 2026-02-12 10:00:00.000000

This migration creates the supporting tables for the folder system:
- folder_items: Junction table for folder→item relationships
- folder_shares: User-level sharing with reader/writer roles
- user_folder_favorites: Per-user folder favorites
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import UUID, ENUM as PG_ENUM


# revision identifiers, used by Alembic.
revision: str = 'c3d4e5f6a7b8'
down_revision: Union[str, Sequence[str], None] = 'b2c3d4e5f6a7'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

SCHEMA = 'global_schema'


def upgrade() -> None:
    """Create folder_items, folder_shares, and user_folder_favorites tables."""

    # 1. Create share_role enum type in global_schema (IF NOT EXISTS)
    op.execute("""
        DO $$ BEGIN
            CREATE TYPE global_schema.share_role AS ENUM ('reader', 'writer');
        EXCEPTION
            WHEN duplicate_object THEN NULL;
        END $$;
    """)

    # 2. Create folder_shares table
    op.create_table(
        'folder_shares',
        sa.Column('id', UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('folder_id', UUID(as_uuid=True), nullable=False),
        sa.Column('user_id', sa.String(), nullable=False),
        sa.Column('user_username', sa.String(), nullable=False),
        sa.Column('role', PG_ENUM('reader', 'writer', name='share_role', schema=SCHEMA, create_type=False), nullable=False, server_default='reader'),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['folder_id'], [f'{SCHEMA}.folders.id'], ondelete='CASCADE'),
        sa.UniqueConstraint('folder_id', 'user_id', name='uq_folder_share_folder_user'),
        schema=SCHEMA,
    )
    op.create_index('ix_global_folder_shares_folder_id', 'folder_shares', ['folder_id'], schema=SCHEMA)
    op.create_index('ix_global_folder_shares_user_id', 'folder_shares', ['user_id'], schema=SCHEMA)

    # 3. Create folder_items table
    op.create_table(
        'folder_items',
        sa.Column('id', UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('folder_id', UUID(as_uuid=True), nullable=False),
        sa.Column('item_id', sa.String(), nullable=False),
        sa.Column('item_type', sa.String(), nullable=False),
        sa.Column('position', sa.Integer(), nullable=True),
        sa.Column('added_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column('owner', sa.String(), nullable=True),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['folder_id'], [f'{SCHEMA}.folders.id'], ondelete='CASCADE'),
        schema=SCHEMA,
    )
    op.create_index('ix_global_folder_items_folder_id', 'folder_items', ['folder_id'], schema=SCHEMA)
    op.create_index('ix_global_folder_items_item_type', 'folder_items', ['item_type'], schema=SCHEMA)

    # 4. Create user_folder_favorites table
    op.create_table(
        'user_folder_favorites',
        sa.Column('id', UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('user_id', sa.String(), nullable=False),
        sa.Column('folder_id', UUID(as_uuid=True), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.ForeignKeyConstraint(['folder_id'], [f'{SCHEMA}.folders.id'], ondelete='CASCADE'),
        sa.UniqueConstraint('user_id', 'folder_id', name='uq_user_folder_favorite'),
        schema=SCHEMA,
    )
    op.create_index('ix_global_user_folder_favorites_user_id', 'user_folder_favorites', ['user_id'], schema=SCHEMA)
    op.create_index('ix_global_user_folder_favorites_folder_id', 'user_folder_favorites', ['folder_id'], schema=SCHEMA)


def downgrade() -> None:
    """Drop folder_items, folder_shares, and user_folder_favorites tables."""
    # Drop in reverse order (respecting FK dependencies)
    op.drop_index('ix_global_user_folder_favorites_folder_id', table_name='user_folder_favorites', schema=SCHEMA)
    op.drop_index('ix_global_user_folder_favorites_user_id', table_name='user_folder_favorites', schema=SCHEMA)
    op.drop_table('user_folder_favorites', schema=SCHEMA)

    op.drop_index('ix_global_folder_items_item_type', table_name='folder_items', schema=SCHEMA)
    op.drop_index('ix_global_folder_items_folder_id', table_name='folder_items', schema=SCHEMA)
    op.drop_table('folder_items', schema=SCHEMA)

    op.drop_index('ix_global_folder_shares_user_id', table_name='folder_shares', schema=SCHEMA)
    op.drop_index('ix_global_folder_shares_folder_id', table_name='folder_shares', schema=SCHEMA)
    op.drop_table('folder_shares', schema=SCHEMA)

    # Drop the enum type
    sa.Enum(name='share_role', schema=SCHEMA).drop(op.get_bind(), checkfirst=True)
