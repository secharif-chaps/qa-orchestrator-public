"""add owner column to folders and migrate from owner_username

Revision ID: 7a00644af8d6
Revises: e0ca846da5c0
Create Date: 2025-08-28 16:57:54.445255

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = '7a00644af8d6'
down_revision = 'e0ca846da5c0'
branch_labels = None
depends_on = None


def upgrade():
    # Add new owner column
    op.add_column('folders', sa.Column('owner', sa.String(), nullable=True))
    
    # Copy data from owner_username to owner
    op.execute("UPDATE folders SET owner = owner_username WHERE owner_username IS NOT NULL")
    
    # Drop old columns
    op.drop_column('folders', 'owner_id')
    op.drop_column('folders', 'owner_username')


def downgrade():
    # Add back the old columns
    op.add_column('folders', sa.Column('owner_username', sa.String(), nullable=True))
    op.add_column('folders', sa.Column('owner_id', sa.UUID(), nullable=True))
    
    # Copy data back
    op.execute("UPDATE folders SET owner_username = owner WHERE owner IS NOT NULL")
    
    # Drop the new column
    op.drop_column('folders', 'owner')