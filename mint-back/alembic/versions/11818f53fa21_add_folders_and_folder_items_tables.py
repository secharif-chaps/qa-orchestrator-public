"""add folders and folder_items tables

Revision ID: 11818f53fa21
Revises: b460b397ef67
Create Date: 2025-08-27 14:17:40.234525

"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision = '11818f53fa21'
down_revision = 'b460b397ef67'
branch_labels = None
depends_on = None


def upgrade():
    # Create folders table
    op.create_table('folders',
        sa.Column('id', postgresql.UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('workspace_id', sa.Integer(), nullable=False),
        sa.Column('owner_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('name', sa.String(), nullable=False),
        sa.Column('color', sa.String(), nullable=True),
        sa.Column('icon', sa.String(), nullable=True),
        sa.Column('tags', postgresql.ARRAY(sa.String()), server_default='{}', nullable=False),
        sa.Column('is_favorite', sa.Boolean(), server_default='false', nullable=False),
        sa.Column('is_deleted', sa.Boolean(), server_default='false', nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['owner_id'], ['users.id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index('ix_folders_workspace_id', 'folders', ['workspace_id'], unique=False)
    op.create_index('ix_folders_owner_id', 'folders', ['owner_id'], unique=False)
    op.create_index('ix_folders_is_deleted', 'folders', ['is_deleted'], unique=False)
    
    # Create folder_items junction table
    op.create_table('folder_items',
        sa.Column('id', postgresql.UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('folder_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('item_id', postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column('item_type', sa.String(), nullable=False),  # 'company', 'contact', etc.
        sa.Column('position', sa.Integer(), nullable=True),  # For ordering items in folder
        sa.Column('added_at', sa.DateTime(timezone=True), server_default=sa.text('now()'), nullable=False),
        sa.Column('added_by', postgresql.UUID(as_uuid=True), nullable=False),
        sa.ForeignKeyConstraint(['folder_id'], ['folders.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['added_by'], ['users.id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('folder_id', 'item_id', 'item_type', name='uq_folder_item')
    )
    op.create_index('ix_folder_items_folder_id', 'folder_items', ['folder_id'], unique=False)
    op.create_index('ix_folder_items_item_id', 'folder_items', ['item_id'], unique=False)
    op.create_index('ix_folder_items_item_type', 'folder_items', ['item_type'], unique=False)
    
    # Add soft delete column to companies table
    op.add_column('companies', sa.Column('is_deleted', sa.Boolean(), server_default='false', nullable=False))
    op.create_index('ix_companies_is_deleted', 'companies', ['is_deleted'], unique=False)


def downgrade():
    # Remove soft delete from companies
    op.drop_index('ix_companies_is_deleted', table_name='companies')
    op.drop_column('companies', 'is_deleted')
    
    # Drop folder_items table
    op.drop_index('ix_folder_items_item_type', table_name='folder_items')
    op.drop_index('ix_folder_items_item_id', table_name='folder_items')
    op.drop_index('ix_folder_items_folder_id', table_name='folder_items')
    op.drop_table('folder_items')
    
    # Drop folders table
    op.drop_index('ix_folders_is_deleted', table_name='folders')
    op.drop_index('ix_folders_owner_id', table_name='folders')
    op.drop_index('ix_folders_workspace_id', table_name='folders')
    op.drop_table('folders')