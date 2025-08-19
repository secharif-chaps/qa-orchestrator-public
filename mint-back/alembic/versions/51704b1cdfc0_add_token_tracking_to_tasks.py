"""add_token_tracking_to_tasks

Revision ID: 51704b1cdfc0
Revises: df31b0577253
Create Date: 2025-08-19 11:58:44.707839

"""
from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = '51704b1cdfc0'
down_revision = 'df31b0577253'
branch_labels = None
depends_on = None


def upgrade():
    # Add token tracking columns to tasks table
    op.add_column('tasks', sa.Column('input_tokens', sa.Integer(), nullable=True))
    op.add_column('tasks', sa.Column('output_tokens', sa.Integer(), nullable=True))
    op.add_column('tasks', sa.Column('total_cost', sa.Float(), nullable=True))


def downgrade():
    # Remove token tracking columns
    op.drop_column('tasks', 'total_cost')
    op.drop_column('tasks', 'output_tokens')
    op.drop_column('tasks', 'input_tokens')