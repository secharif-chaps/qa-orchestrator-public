"""add workflow_configs table

Revision ID: df31b0577253
Revises: 2a4d9e0be41e
Create Date: 2025-08-13 13:37:02.821344

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = 'df31b0577253'
down_revision = '2a4d9e0be41e'
branch_labels = None
depends_on = None


def upgrade():
    # Create workflow_configs table
    op.create_table(
        'workflow_configs',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('task_type', sa.String(length=50), nullable=False),
        sa.Column('title', sa.String(length=100), nullable=False),
        sa.Column('workflow_id', sa.String(length=100), nullable=True),
        sa.Column('api_key', sa.String(length=200), nullable=True),
        sa.Column('created_at', sa.DateTime(), server_default=sa.text('now()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(), server_default=sa.text('now()'), nullable=False),
        sa.PrimaryKeyConstraint('id'),
        sa.UniqueConstraint('task_type')
    )
    op.create_index(op.f('ix_workflow_configs_id'), 'workflow_configs', ['id'], unique=False)
    
    # Insert initial 8 workflow configs
    workflow_configs_table = sa.table(
        'workflow_configs',
        sa.column('task_type', sa.String),
        sa.column('title', sa.String),
        sa.column('workflow_id', sa.String),
        sa.column('api_key', sa.String)
    )
    
    op.bulk_insert(
        workflow_configs_table,
        [
            {'task_type': 'profile', 'title': 'Profile Analysis', 'workflow_id': None, 'api_key': None},
            {'task_type': 'digital', 'title': 'Digital Presence', 'workflow_id': None, 'api_key': None},
            {'task_type': 'timeline', 'title': 'Timeline Analysis', 'workflow_id': '3feffed9-7add-4932-95eb-d0feb9960923', 'api_key': 'app-qX4RISdrrif2aSPAaLVz7tto'},
            {'task_type': 'products', 'title': 'Products Analysis', 'workflow_id': '6b95cda2-b32f-4578-9b63-520ebb527c97', 'api_key': 'app-WpGZCTFDaBzCUS9M4LeoQHGa'},
            {'task_type': 'jobs', 'title': 'Job Opportunities', 'workflow_id': None, 'api_key': None},
            {'task_type': 'csr', 'title': 'CSR Activities', 'workflow_id': None, 'api_key': None},
            {'task_type': 'press', 'title': 'Press Coverage', 'workflow_id': None, 'api_key': None},
            {'task_type': 'team', 'title': 'Team Analysis', 'workflow_id': None, 'api_key': None}
        ]
    )


def downgrade():
    op.drop_index(op.f('ix_workflow_configs_id'), table_name='workflow_configs')
    op.drop_table('workflow_configs') 