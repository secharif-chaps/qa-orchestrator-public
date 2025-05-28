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
        sa.Column('error', sa.String(), nullable=True),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index(op.f('ix_companies_id'), 'companies', ['id'], unique=False)
    op.create_index(op.f('ix_companies_name'), 'companies', ['name'], unique=False)
    op.create_index(op.f('ix_companies_website'), 'companies', ['website'], unique=False)

    # Create task type enum
    # op.execute("CREATE TYPE task_type_enum AS ENUM ('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team')")
    
    # Create task status enum
    # op.execute("CREATE TYPE task_status_enum AS ENUM ('pending', 'running', 'succeeded', 'error')")

    # Create tasks table
    op.create_table('tasks',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('company_id', sa.Integer(), nullable=False),
        sa.Column('type', sa.Enum('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team', name='task_type_enum'), nullable=False),
        sa.Column('status', sa.Enum('pending', 'running', 'succeeded', 'error', name='task_status_enum'), nullable=False),
        sa.Column('error', sa.String(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.ForeignKeyConstraint(['company_id'], ['companies.id'], ),
        sa.PrimaryKeyConstraint('id')
    )
    op.create_index(op.f('ix_tasks_id'), 'tasks', ['id'], unique=False)


def downgrade():
    # Drop tasks table
    op.drop_index(op.f('ix_tasks_id'), table_name='tasks')
    op.drop_table('tasks')

    # Drop enum types
    op.execute('DROP TYPE task_type_enum')
    op.execute('DROP TYPE task_status_enum')

    # Drop companies table
    op.drop_index(op.f('ix_companies_website'), table_name='companies')
    op.drop_index(op.f('ix_companies_name'), table_name='companies')
    op.drop_index(op.f('ix_companies_id'), table_name='companies')
    op.drop_table('companies') 