"""Add data_collection task type and knowledge fields

Revision ID: 003
Revises: 002
Create Date: 2025-10-20 10:00:00.000000

"""
from alembic import op
import sqlalchemy as sa

# revision identifiers, used by Alembic.
revision = '003'
down_revision = '002'
branch_labels = None
depends_on = None

def upgrade():
    # Add data_collection to task_type_enum
    op.execute("ALTER TYPE task_type_enum ADD VALUE 'data_collection'")

    # Add 4 new knowledge fields to companies table
    op.add_column('companies', sa.Column('raw_mistral_knowledge', sa.String(), nullable=True))
    op.add_column('companies', sa.Column('raw_claude_knowledge', sa.String(), nullable=True))
    op.add_column('companies', sa.Column('raw_wikipedia_knowledge', sa.String(), nullable=True))
    op.add_column('companies', sa.Column('raw_scraped_website_knowledge', sa.String(), nullable=True))

    # Insert workflow_config for data_collection task
    op.execute("""
        INSERT INTO workflow_configs (task_type, title, workflow_id, api_key, llm, created_at, updated_at)
        VALUES (
            'data_collection',
            'Data Collection',
            '11c35ece-b7f6-4b09-abb1-133399b321af',
            'app-qSdKHTLoR0WiESMRcVBKSzlI',
            'mistral',
            NOW(),
            NOW()
        )
        ON CONFLICT (task_type) DO NOTHING
    """)

def downgrade():
    # Remove workflow_config for data_collection
    op.execute("DELETE FROM workflow_configs WHERE task_type = 'data_collection'")

    # Remove 4 knowledge fields from companies table
    op.drop_column('companies', 'raw_scraped_website_knowledge')
    op.drop_column('companies', 'raw_wikipedia_knowledge')
    op.drop_column('companies', 'raw_claude_knowledge')
    op.drop_column('companies', 'raw_mistral_knowledge')

    # Note: Cannot remove enum value in PostgreSQL without recreating the entire enum type
    # This would require dropping all dependent columns and recreating them
    # For safety, we leave the enum value in place during downgrade
