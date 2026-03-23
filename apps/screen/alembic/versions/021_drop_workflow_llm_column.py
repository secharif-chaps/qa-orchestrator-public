"""Drop llm column from workflow_configs

Transition to GPT 5.1 only - LLM selection no longer needed.
See specs/2026-01-09-dify-workflow-parameter-refactoring/spec.md

Revision ID: 021
Revises: 020
Create Date: 2026-01-16
"""

import sqlalchemy as sa

from alembic import op

# revision identifiers, used by Alembic
revision = "021"
down_revision = "020"
branch_labels = None
depends_on = None


def upgrade() -> None:
    """Drop llm column from workflow_configs."""
    op.drop_column("workflow_configs", "llm")


def downgrade() -> None:
    """Restore llm column to workflow_configs."""
    op.add_column(
        "workflow_configs",
        sa.Column("llm", sa.String(20), nullable=False, server_default="mistral"),
    )
