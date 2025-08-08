"""add workspace_modules table

Revision ID: 2a4d9e0be41e
Revises: cf738e5e7d13
Create Date: 2025-08-08 18:32:41.842410

"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql


# revision identifiers, used by Alembic.
revision = '2a4d9e0be41e'
down_revision = 'cf738e5e7d13'
branch_labels = None
depends_on = None


def upgrade():
    # Create modulename enum type
    op.execute("CREATE TYPE modulename AS ENUM ('screen', 'target', 'explore', 'stream')")
    
    # Create workspace_modules table
    op.create_table('workspace_modules',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('workspace_id', sa.Integer(), nullable=False),
        sa.Column('module_name', postgresql.ENUM('screen', 'target', 'explore', 'stream', name='modulename', create_type=False), nullable=False),
        sa.Column('enabled', sa.Boolean(), nullable=False, default=False),
        sa.Column('token_count', sa.Integer(), nullable=False, default=0),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.func.now()),
        sa.Column('updated_at', sa.DateTime(timezone=True), onupdate=sa.func.now()),
        sa.ForeignKeyConstraint(['workspace_id'], ['workspaces.id'], ),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('workspace_id', 'module_name', name='uq_workspace_modules_workspace_module')
    )
    op.create_index('ix_workspace_modules_id', 'workspace_modules', ['id'])


def downgrade():
    # Drop workspace_modules table
    op.drop_index('ix_workspace_modules_id', table_name='workspace_modules')
    op.drop_table('workspace_modules')
    
    # Drop modulename enum type
    op.execute('DROP TYPE IF EXISTS modulename') 