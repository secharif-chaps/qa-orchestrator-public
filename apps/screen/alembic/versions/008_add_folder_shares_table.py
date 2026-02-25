"""Add folder_shares table for user-level folder sharing

This migration creates the folder_shares table which enables private folders
with user-level sharing. Each folder can be shared with specific users who
receive either Reader (view-only) or Writer (can add items) access.

Revision ID: 008
Revises: 007
Create Date: 2025-12-16

"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision = '008'
down_revision = '007'
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create folder_shares table with share_role enum."""

    # 1. Create the share_role enum type
    share_role_enum = postgresql.ENUM('reader', 'writer', name='share_role', create_type=False)
    share_role_enum.create(op.get_bind(), checkfirst=True)

    # 2. Create folder_shares table
    op.create_table(
        'folder_shares',
        sa.Column(
            'id',
            postgresql.UUID(as_uuid=True),
            server_default=sa.text('gen_random_uuid()'),
            nullable=False
        ),
        sa.Column(
            'folder_id',
            postgresql.UUID(as_uuid=True),
            sa.ForeignKey('folders.id', ondelete='CASCADE'),
            nullable=False
        ),
        sa.Column('user_id', sa.String(), nullable=False),
        sa.Column('user_username', sa.String(), nullable=False),
        sa.Column(
            'role',
            share_role_enum,
            nullable=False,
            server_default='reader'
        ),
        sa.Column(
            'created_at',
            sa.DateTime(timezone=True),
            server_default=sa.text('NOW()'),
            nullable=False
        ),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('folder_id', 'user_id', name='uq_folder_share_folder_user')
    )

    # 3. Create indexes for efficient lookups
    op.create_index('ix_folder_shares_folder_id', 'folder_shares', ['folder_id'])
    op.create_index('ix_folder_shares_user_id', 'folder_shares', ['user_id'])


def downgrade() -> None:
    """Drop folder_shares table and share_role enum."""

    # 1. Drop indexes
    op.drop_index('ix_folder_shares_user_id', table_name='folder_shares')
    op.drop_index('ix_folder_shares_folder_id', table_name='folder_shares')

    # 2. Drop table
    op.drop_table('folder_shares')

    # 3. Drop the enum type
    share_role_enum = postgresql.ENUM('reader', 'writer', name='share_role', create_type=False)
    share_role_enum.drop(op.get_bind(), checkfirst=True)
