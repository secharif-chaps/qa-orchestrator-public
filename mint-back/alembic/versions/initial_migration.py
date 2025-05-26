"""Initial migration

Revision ID: 001
Revises: 
Create Date: 2023-08-01 00:00:00.000000

"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision = '001'
down_revision = None
branch_labels = None
depends_on = None


def upgrade():
    # Create companies table
    op.create_table('companies',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('name', sa.String(), nullable=False),
        sa.Column('website', sa.String(), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.Column('profile', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('digital', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('timeline', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('products', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('jobs', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('csr', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('press', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('team', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('pending_states', postgresql.JSON(astext_type=sa.Text()), nullable=True),
        sa.Column('error', sa.String(), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index(op.f('ix_companies_id'), 'companies', ['id'], unique=False)
    op.create_index(op.f('ix_companies_name'), 'companies', ['name'], unique=False)
    op.create_index(op.f('ix_companies_website'), 'companies', ['website'], unique=False)


def downgrade():
    op.drop_index(op.f('ix_companies_website'), table_name='companies')
    op.drop_index(op.f('ix_companies_name'), table_name='companies')
    op.drop_index(op.f('ix_companies_id'), table_name='companies')
    op.drop_table('companies') 