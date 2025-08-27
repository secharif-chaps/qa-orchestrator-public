"""add llm field to workflow configs

Revision ID: b460b397ef67
Revises: b54632488613
Create Date: 2025-08-25 10:14:33.772807

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = 'b460b397ef67'
down_revision = 'b54632488613'
branch_labels = None
depends_on = None


def upgrade():
    # Add llm column to workflow_configs table
    op.add_column('workflow_configs', 
        sa.Column('llm', sa.String(20), nullable=False, server_default='mistral')
    )
    
    # Remove the server default after setting values
    op.alter_column('workflow_configs', 'llm', server_default=None)


def downgrade():
    # Remove llm column
    op.drop_column('workflow_configs', 'llm')