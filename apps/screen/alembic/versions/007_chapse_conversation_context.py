"""Create chapse_conversation_context table

This migration creates the table for storing company context linked to Dify conversations.
Dify manages conversations and messages, we only store which companies are linked.

Revision ID: 007
Revises: 006
Create Date: 2025-11-28

"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision = '007'
down_revision = '006'
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Create chapse_conversation_context table."""

    op.create_table(
        'chapse_conversation_context',
        sa.Column('id', postgresql.UUID(as_uuid=True), server_default=sa.text('gen_random_uuid()'), nullable=False),
        sa.Column('dify_conversation_id', sa.String(255), nullable=False),
        sa.Column('user_id', sa.String(), nullable=False),
        sa.Column('organization_id', sa.String(), nullable=False),
        sa.Column('company_ids', postgresql.ARRAY(sa.Integer()), server_default='{}', nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), server_default=sa.text('NOW()'), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), server_default=sa.text('NOW()'), nullable=False),
        sa.PrimaryKeyConstraint('id')
    )

    # Create indexes for efficient lookups
    op.create_index('ix_chapse_context_dify_id', 'chapse_conversation_context', ['dify_conversation_id'], unique=True)
    op.create_index('ix_chapse_context_user_id', 'chapse_conversation_context', ['user_id'])
    op.create_index('ix_chapse_context_org_id', 'chapse_conversation_context', ['organization_id'])


def downgrade() -> None:
    """Drop chapse_conversation_context table."""

    op.drop_index('ix_chapse_context_org_id', table_name='chapse_conversation_context')
    op.drop_index('ix_chapse_context_user_id', table_name='chapse_conversation_context')
    op.drop_index('ix_chapse_context_dify_id', table_name='chapse_conversation_context')
    op.drop_table('chapse_conversation_context')
