"""Add owner_username to folders table

Revision ID: 5d2eccea2fb2
Revises: e0ca846da5c0
Create Date: 2025-08-27 15:28:14.939571

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = '5d2eccea2fb2'
down_revision = 'e0ca846da5c0'
branch_labels = None
depends_on = None


def upgrade():
    # Add owner_username column to folders
    op.add_column('folders', sa.Column('owner_username', sa.String(), nullable=True))
    
    # Populate owner_username from users table for existing records
    op.execute("""
        UPDATE folders 
        SET owner_username = users.username 
        FROM users 
        WHERE folders.owner_id = users.id
    """)
    
    # Set owner_username to 'system' for records without matching users
    op.execute("""
        UPDATE folders 
        SET owner_username = 'system' 
        WHERE owner_username IS NULL
    """)
    
    # Make owner_username non-nullable
    op.alter_column('folders', 'owner_username', nullable=False)
    
    # Make owner_id nullable (for backwards compatibility)
    op.alter_column('folders', 'owner_id', nullable=True)
    
    # Update folder_items table
    # Make added_by nullable
    op.alter_column('folder_items', 'added_by', nullable=True)
    
    # Add added_by_username column
    op.add_column('folder_items', sa.Column('added_by_username', sa.String(), nullable=True))
    
    # Populate added_by_username from users table for existing records
    op.execute("""
        UPDATE folder_items 
        SET added_by_username = users.username 
        FROM users 
        WHERE folder_items.added_by = users.id
    """)


def downgrade():
    # Drop added_by_username from folder_items
    op.drop_column('folder_items', 'added_by_username')
    
    # Make added_by non-nullable again
    op.alter_column('folder_items', 'added_by', nullable=False)
    
    # Make owner_id non-nullable again
    op.alter_column('folders', 'owner_id', nullable=False)
    
    # Drop owner_username column
    op.drop_column('folders', 'owner_username')