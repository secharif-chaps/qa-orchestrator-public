"""add owner_username to companies

Revision ID: add_owner_username
Revises: initial_migration
Create Date: 2025-01-02 11:00:00.000000

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = 'add_owner_username'
down_revision = 'initial_migration'
branch_labels = None
depends_on = None


def upgrade():
    # Add owner_username column to companies table
    op.add_column('companies', sa.Column('owner_username', sa.String(), nullable=False, server_default='suh'))
    
    # Add index for better query performance
    op.create_index(op.f('ix_companies_owner_username'), 'companies', ['owner_username'], unique=False)


def downgrade():
    # Remove index first
    op.drop_index(op.f('ix_companies_owner_username'), table_name='companies')
    
    # Remove column
    op.drop_column('companies', 'owner_username')