"""Migrate _value_fr columns to translations table and drop them

Revision ID: 018
Revises: 017
Create Date: 2025-01-07

This migration:
1. Migrates all existing French translations from _value_fr columns
   to the normalized translations table
2. Drops all _value_fr columns from source tables

After this migration, French is treated like other languages (es, de, pt)
and stored in the translations table.
"""

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic
revision = '018'
down_revision = '017'
branch_labels = None
depends_on = None


# Define all _value_fr columns to migrate
# Format: (table_name, [(column_base_name, fr_column_name), ...])
# For 1:1 tables, record_id = company_id
# For 1:N tables, record_id = id column of the child table

ONE_TO_ONE_TABLES = [
    ("company_profile", [
        ("insights", "insights_value_fr"),
        ("business_line", "business_line_value_fr"),
        ("catchphrase", "catchphrase_value_fr"),
    ]),
    ("company_digital", [
        ("insights", "insights_value_fr"),
        ("overall_strategy", "overall_strategy_value_fr"),
        ("digital_transformation", "digital_transformation_value_fr"),
        ("ecommerce_capabilities", "ecommerce_capabilities_value_fr"),
        ("mobile_strategy", "mobile_strategy_value_fr"),
        ("digital_marketing_approach", "digital_marketing_approach_value_fr"),
        ("loyalty_program", "loyalty_program_value_fr"),
    ]),
    ("company_timeline", [
        ("insights", "insights_value_fr"),
    ]),
    ("company_products", [
        ("insights", "insights_value_fr"),
        ("customer_type", "customer_type_value_fr"),
        ("marketing_positioning", "marketing_positioning_value_fr"),
    ]),
    ("company_jobs", [
        ("insights_top_departments", "insights_top_departments_value_fr"),
        ("insights_hiring_focus", "insights_hiring_focus_value_fr"),
        ("insights_growth_indicators", "insights_growth_indicators_value_fr"),
    ]),
    ("company_csr", [
        ("insights", "insights_value_fr"),
        ("responsibility", "responsibility_value_fr"),
    ]),
    ("company_press", [
        ("insights", "insights_value_fr"),
    ]),
]

ONE_TO_MANY_TABLES = [
    ("company_online_services", [
        ("name", "name_value_fr"),
        ("description", "description_value_fr"),
    ]),
    ("company_timeline_events", [
        ("title", "title_value_fr"),
        ("description", "description_value_fr"),
        ("category", "category_value_fr"),
        ("impact", "impact_value_fr"),
    ]),
    ("company_product_items", [
        ("value", "value_value_fr"),
    ]),
    ("company_product_categories", [
        ("category_name", "category_name_value_fr"),
        # items_value_fr is ARRAY type - skip for now, handle separately if needed
    ]),
    ("company_job_offers", [
        ("title", "title_value_fr"),
        ("department", "department_value_fr"),
        ("description", "description_value_fr"),
        ("requirements", "requirements_value_fr"),
    ]),
    ("company_csr_initiatives", [
        ("value", "value_value_fr"),
    ]),
    ("company_press_items", [
        ("value", "value_value_fr"),
    ]),
    ("company_team_members", [
        ("position", "position_value_fr"),
    ]),
]

# Special case: items_value_fr is an ARRAY column
ARRAY_COLUMNS = [
    ("company_product_categories", "items_value_fr"),
]


def upgrade():
    """Migrate _value_fr data to translations table and drop columns."""

    print("Starting migration of French translations to translations table...")

    # Get connection for raw SQL
    connection = op.get_bind()

    # Migrate 1:1 tables (record_id = company_id)
    for table_name, columns in ONE_TO_ONE_TABLES:
        print(f"  Migrating {table_name}...")
        for field_name, fr_column in columns:
            # Check if column exists before migrating
            result = connection.execute(sa.text(f"""
                SELECT column_name FROM information_schema.columns
                WHERE table_name = '{table_name}' AND column_name = '{fr_column}'
            """))
            if result.fetchone() is None:
                print(f"    Skipping {fr_column} (column doesn't exist)")
                continue

            # Insert non-null French values into translations table
            connection.execute(sa.text(f"""
                INSERT INTO translations (table_name, record_id, field_name, language_code, value, created_at, company_id)
                SELECT
                    '{table_name}',
                    company_id,
                    '{field_name}',
                    'fr',
                    {fr_column},
                    NOW(),
                    company_id
                FROM {table_name}
                WHERE {fr_column} IS NOT NULL
                ON CONFLICT (table_name, record_id, field_name, language_code)
                DO UPDATE SET value = EXCLUDED.value
            """))
            print(f"    Migrated {field_name} -> translations")

    # Migrate 1:N tables (record_id = id)
    for table_name, columns in ONE_TO_MANY_TABLES:
        print(f"  Migrating {table_name}...")
        for field_name, fr_column in columns:
            # Check if column exists before migrating
            result = connection.execute(sa.text(f"""
                SELECT column_name FROM information_schema.columns
                WHERE table_name = '{table_name}' AND column_name = '{fr_column}'
            """))
            if result.fetchone() is None:
                print(f"    Skipping {fr_column} (column doesn't exist)")
                continue

            # Insert non-null French values into translations table
            connection.execute(sa.text(f"""
                INSERT INTO translations (table_name, record_id, field_name, language_code, value, created_at, company_id)
                SELECT
                    '{table_name}',
                    id,
                    '{field_name}',
                    'fr',
                    {fr_column},
                    NOW(),
                    company_id
                FROM {table_name}
                WHERE {fr_column} IS NOT NULL
                ON CONFLICT (table_name, record_id, field_name, language_code)
                DO UPDATE SET value = EXCLUDED.value
            """))
            print(f"    Migrated {field_name} -> translations")

    print("Migration of French data complete. Now dropping _value_fr columns...")

    # Drop _value_fr columns from 1:1 tables
    for table_name, columns in ONE_TO_ONE_TABLES:
        for field_name, fr_column in columns:
            try:
                op.drop_column(table_name, fr_column)
                print(f"  Dropped {table_name}.{fr_column}")
            except Exception as e:
                print(f"  Skipping drop {table_name}.{fr_column}: {e}")

    # Drop _value_fr columns from 1:N tables
    for table_name, columns in ONE_TO_MANY_TABLES:
        for field_name, fr_column in columns:
            try:
                op.drop_column(table_name, fr_column)
                print(f"  Dropped {table_name}.{fr_column}")
            except Exception as e:
                print(f"  Skipping drop {table_name}.{fr_column}: {e}")

    # Drop ARRAY columns
    for table_name, fr_column in ARRAY_COLUMNS:
        try:
            op.drop_column(table_name, fr_column)
            print(f"  Dropped {table_name}.{fr_column}")
        except Exception as e:
            print(f"  Skipping drop {table_name}.{fr_column}: {e}")

    print("Migration complete!")


def downgrade():
    """Recreate _value_fr columns and restore data from translations table."""

    print("Recreating _value_fr columns...")

    # Recreate columns for 1:1 tables
    for table_name, columns in ONE_TO_ONE_TABLES:
        for field_name, fr_column in columns:
            op.add_column(table_name, sa.Column(fr_column, sa.Text(), nullable=True))
            print(f"  Added {table_name}.{fr_column}")

    # Recreate columns for 1:N tables
    for table_name, columns in ONE_TO_MANY_TABLES:
        for field_name, fr_column in columns:
            op.add_column(table_name, sa.Column(fr_column, sa.Text(), nullable=True))
            print(f"  Added {table_name}.{fr_column}")

    # Recreate ARRAY columns
    for table_name, fr_column in ARRAY_COLUMNS:
        op.add_column(table_name, sa.Column(fr_column, sa.ARRAY(sa.Text()), nullable=True))
        print(f"  Added {table_name}.{fr_column}")

    print("Restoring French data from translations table...")

    # Get connection for raw SQL
    connection = op.get_bind()

    # Restore data to 1:1 tables
    for table_name, columns in ONE_TO_ONE_TABLES:
        for field_name, fr_column in columns:
            connection.execute(sa.text(f"""
                UPDATE {table_name} t
                SET {fr_column} = tr.value
                FROM translations tr
                WHERE tr.table_name = '{table_name}'
                  AND tr.record_id = t.company_id
                  AND tr.field_name = '{field_name}'
                  AND tr.language_code = 'fr'
            """))
            print(f"  Restored {table_name}.{fr_column}")

    # Restore data to 1:N tables
    for table_name, columns in ONE_TO_MANY_TABLES:
        for field_name, fr_column in columns:
            connection.execute(sa.text(f"""
                UPDATE {table_name} t
                SET {fr_column} = tr.value
                FROM translations tr
                WHERE tr.table_name = '{table_name}'
                  AND tr.record_id = t.id
                  AND tr.field_name = '{field_name}'
                  AND tr.language_code = 'fr'
            """))
            print(f"  Restored {table_name}.{fr_column}")

    # Delete French translations from translations table
    connection.execute(sa.text("""
        DELETE FROM translations WHERE language_code = 'fr'
    """))
    print("Deleted French translations from translations table")

    print("Downgrade complete!")
