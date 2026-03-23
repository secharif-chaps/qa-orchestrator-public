"""Rename raw_claude_knowledge to raw_gpt_knowledge

Transition to GPT 5.1 naming convention.
See specs/2026-01-09-dify-workflow-parameter-refactoring/spec.md

Revision ID: 022
Revises: 021
Create Date: 2026-01-16
"""

from alembic import op

# revision identifiers, used by Alembic
revision = "022"
down_revision = "021"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Rename raw_claude_knowledge to raw_gpt_knowledge."""
    op.alter_column("companies", "raw_claude_knowledge", new_column_name="raw_gpt_knowledge")


def downgrade() -> None:
    """Restore raw_claude_knowledge column name."""
    op.alter_column("companies", "raw_gpt_knowledge", new_column_name="raw_claude_knowledge")
