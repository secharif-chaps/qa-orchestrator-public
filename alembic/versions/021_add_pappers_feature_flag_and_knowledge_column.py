from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision = "021"
down_revision = "020"
branch_labels = None
depends_on = None


def upgrade():
    # Add PAPPERS value to FeatureFlag enum
    op.execute("ALTER TYPE featureflag ADD VALUE IF NOT EXISTS 'pappers'")

    # Add raw_pappers_knowledge column to companies table
    op.add_column(
        'companies',
        sa.Column('raw_pappers_knowledge', sa.String(), nullable=True)
    )


def downgrade():
    # Remove the column
    op.drop_column('companies', 'raw_pappers_knowledge')

    # Remove 'pappers' value from FeatureFlag enum
    # PostgreSQL doesn't support DROP VALUE, so we need to recreate the enum

    # 1. Remove any rows using 'pappers' value
    op.execute("DELETE FROM organization_feature_flags WHERE flag = 'pappers'")

    # 2. Rename the old enum
    op.execute("ALTER TYPE featureflag RENAME TO featureflag_old")

    # 3. Create new enum without 'pappers' (list all other values)
    op.execute("""
        CREATE TYPE featureflag AS ENUM (
            'translation'
        )
    """)

    # 4. Update the column to use the new enum
    op.execute("""
        ALTER TABLE organization_feature_flags
        ALTER COLUMN flag TYPE featureflag
        USING flag::text::featureflag
    """)

    # 5. Drop the old enum
    op.execute("DROP TYPE featureflag_old")
