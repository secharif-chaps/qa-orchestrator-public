"""Add first_name and last_name to workspace_members

Revision ID: 4af8b7ae794b
Revises: 15232be66651
Create Date: 2025-08-06 08:38:02.596251

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = '4af8b7ae794b'
down_revision = '15232be66651'
branch_labels = None
depends_on = None


def upgrade():
    # Add first_name and last_name columns to workspace_members
    op.add_column('workspace_members', sa.Column('first_name', sa.String(100), nullable=True))
    op.add_column('workspace_members', sa.Column('last_name', sa.String(100), nullable=True))


def downgrade():
    # Drop first_name and last_name columns from workspace_members
    op.drop_column('workspace_members', 'last_name')
    op.drop_column('workspace_members', 'first_name') 