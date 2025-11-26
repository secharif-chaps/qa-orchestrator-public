"""Convert folder favorites to user-scoped

This migration:
1. Creates a new user_folder_favorites junction table for per-user favorites
2. Removes the is_favorite column from the folders table

Note: Existing favorite data will be lost as we're moving from organization-scoped
to user-scoped favorites. This is acceptable per requirements.

Revision ID: 006
Revises: 005
Create Date: 2025-01-26

"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision = '006'
down_revision = '005'
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create user_folder_favorites table and remove is_favorite from folders."""

    # 1. Create new user_folder_favorites junction table
    op.create_table(
        'user_folder_favorites',
        sa.Column('id', postgresql.UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('user_id', sa.String(), nullable=False),
        sa.Column('folder_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('NOW()'), nullable=False),
        sa.ForeignKeyConstraint(['folder_id'], ['folders.id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('user_id', 'folder_id', name='uq_user_folder_favorite')
    )

    # Create indexes for efficient lookups
    op.create_index('ix_user_folder_favorites_user_id', 'user_folder_favorites', ['user_id'])
    op.create_index('ix_user_folder_favorites_folder_id', 'user_folder_favorites', ['folder_id'])

    # 2. Remove is_favorite column from folders table
    # Note: Existing favorite data will be lost (acceptable per requirements)
    op.drop_column('folders', 'is_favorite')


def downgrade() -> None:
    """Restore is_favorite column and drop user_folder_favorites table."""

    # 1. Add is_favorite column back to folders
    op.add_column(
        'folders',
        sa.Column('is_favorite', sa.Boolean(), server_default=sa.text('false'), nullable=False)
    )

    # 2. Drop indexes
    op.drop_index('ix_user_folder_favorites_folder_id', 'user_folder_favorites')
    op.drop_index('ix_user_folder_favorites_user_id', 'user_folder_favorites')

    # 3. Drop user_folder_favorites table
    op.drop_table('user_folder_favorites')
