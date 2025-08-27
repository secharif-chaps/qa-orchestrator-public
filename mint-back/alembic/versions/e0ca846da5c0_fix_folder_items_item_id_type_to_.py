"""fix folder_items item_id type to support integer company IDs

Revision ID: e0ca846da5c0
Revises: 11818f53fa21
Create Date: 2025-08-27 14:31:43.123456

"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision = 'e0ca846da5c0'
down_revision = '11818f53fa21'
branch_labels = None
depends_on = None


def upgrade():
    # Change item_id from UUID to String to support different ID types (integer company IDs, etc.)
    op.alter_column('folder_items', 'item_id',
                    existing_type=postgresql.UUID(),
                    type_=sa.String(),
                    existing_nullable=False)


def downgrade():
    # Change item_id back to UUID (this may cause data loss if string values can't be cast to UUID)
    op.alter_column('folder_items', 'item_id',
                    existing_type=sa.String(),
                    type_=postgresql.UUID(),
                    existing_nullable=False)