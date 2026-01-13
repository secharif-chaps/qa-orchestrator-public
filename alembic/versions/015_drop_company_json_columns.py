"""Drop company JSON columns (Task Group 11 cleanup).

Revision ID: 015
Revises: 014
Create Date: 2024-12-29

This migration drops the JSON columns from the companies table after
confirming all data has been migrated to normalized tables:
- profile, digital, timeline, products, jobs, csr, press, team columns removed
- raw_*_knowledge columns are KEPT (as per spec)
- All company section data is now stored in:
  - company_profile, company_digital, company_timeline, etc. (1:1 sections)
  - company_online_services, company_team_members, etc. (1:N children)

This is the final cleanup step for the Company data structure refactoring.
"""

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql


# revision identifiers, used by Alembic.
revision = '015'
down_revision = '014'
branch_labels = None
depends_on = None


def upgrade():
    """Drop JSON columns from companies table.

    The data in these columns has been migrated to normalized tables.

    IMPORTANT: Run scripts/migrate_company_json_to_tables.py BEFORE this migration!
    """
    # Safety check: verify data was migrated before dropping columns
    connection = op.get_bind()

    # Count companies with JSON data
    result = connection.execute(sa.text("""
        SELECT COUNT(*) FROM companies
        WHERE profile IS NOT NULL
           OR digital IS NOT NULL
           OR timeline IS NOT NULL
    """))
    json_companies = result.scalar()

    # Count companies with normalized data
    result = connection.execute(sa.text("""
        SELECT COUNT(*) FROM company_profile
    """))
    normalized_companies = result.scalar()

    if json_companies > 0 and normalized_companies == 0:
        raise RuntimeError(
            f"SAFETY CHECK FAILED: Found {json_companies} companies with JSON data "
            f"but 0 companies in normalized tables. "
            f"Run 'python scripts/migrate_company_json_to_tables.py' first!"
        )

    print(f"Safety check passed: {normalized_companies} companies in normalized tables")

    # Drop JSON columns - these are replaced by normalized tables
    op.drop_column('companies', 'profile')
    op.drop_column('companies', 'digital')
    op.drop_column('companies', 'timeline')
    op.drop_column('companies', 'products')
    op.drop_column('companies', 'jobs')
    op.drop_column('companies', 'csr')
    op.drop_column('companies', 'press')
    op.drop_column('companies', 'team')

    # NOTE: raw_*_knowledge columns are intentionally KEPT
    # - raw_mistral_knowledge
    # - raw_claude_knowledge
    # - raw_wikipedia_knowledge
    # - raw_scraped_website_knowledge


def downgrade():
    """Recreate JSON columns on companies table.

    WARNING: This does not restore data - only recreates the columns.
    Data in normalized tables would need to be migrated back manually.
    """
    # Recreate JSON columns with default empty values
    op.add_column('companies', sa.Column(
        'profile',
        postgresql.JSON(astext_type=sa.Text()),
        nullable=True,
        server_default='{}'
    ))
    op.add_column('companies', sa.Column(
        'digital',
        postgresql.JSON(astext_type=sa.Text()),
        nullable=True,
        server_default='{}'
    ))
    op.add_column('companies', sa.Column(
        'timeline',
        postgresql.JSON(astext_type=sa.Text()),
        nullable=True,
        server_default='{}'
    ))
    op.add_column('companies', sa.Column(
        'products',
        postgresql.JSON(astext_type=sa.Text()),
        nullable=True,
        server_default='{}'
    ))
    op.add_column('companies', sa.Column(
        'jobs',
        postgresql.JSON(astext_type=sa.Text()),
        nullable=True,
        server_default='{}'
    ))
    op.add_column('companies', sa.Column(
        'csr',
        postgresql.JSON(astext_type=sa.Text()),
        nullable=True,
        server_default='{}'
    ))
    op.add_column('companies', sa.Column(
        'press',
        postgresql.JSON(astext_type=sa.Text()),
        nullable=True,
        server_default='{}'
    ))
    op.add_column('companies', sa.Column(
        'team',
        postgresql.JSON(astext_type=sa.Text()),
        nullable=True,
        server_default='[]'
    ))
