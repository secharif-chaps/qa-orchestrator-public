#!/usr/bin/env python3
"""
Data migration script: Copy folder data from mint_db to global_db.global_schema.

Migrates all folder-related tables:
1. folders
2. folder_items
3. folder_shares (+ share_role enum)
4. user_folder_favorites

Usage (inside the global-service container):
    python scripts/migrate_folders.py

Environment variables:
    SOURCE_DATABASE_URL  - mint_db connection (default: postgresql://postgres:postgres@db:5432/mint_db)
    DATABASE_URL         - global_db connection (default from app config)

The script is idempotent: ON CONFLICT (id) DO NOTHING.
"""
import os
import sys
from pathlib import Path

# Add project root to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlalchemy import create_engine, text

# Configuration
SOURCE_DB_URL = os.getenv(
    "SOURCE_DATABASE_URL",
    "postgresql://postgres:postgres@db:5432/mint_db",
)
TARGET_DB_URL = os.getenv(
    "DATABASE_URL",
    "postgresql://postgres:postgres@db:5432/global_db",
)

BATCH_SIZE = 500
TARGET_SCHEMA = "global_schema"

# Mapping of old item_type values (mint_db) to new enum values (global_db)
# Only 'company' maps directly; other values are not migrated
ITEM_TYPE_MAP = {
    "company": "company",
    # "contact" and "document" were allowed by old regex but unused in practice
}

# Table migration definitions: (source_table, target_table, columns)
# Optional "casts" dict maps column names to SQL cast expressions for INSERT
TABLES = [
    {
        "name": "folders",
        "source": "folders",
        "target": f"{TARGET_SCHEMA}.folders",
        "columns": [
            "id", "organization_id", "owner_id", "owner", "name",
            "color", "icon", "tags", "is_deleted", "is_orphaned",
            "created_at", "updated_at",
        ],
    },
    {
        "name": "folder_items",
        "source": "folder_items",
        "target": f"{TARGET_SCHEMA}.folder_items",
        "columns": [
            "id", "folder_id", "item_id", "item_type",
            "position", "added_at", "owner",
        ],
        # Cast item_type text to the PostgreSQL enum type
        "casts": {
            "item_type": f":item_type::{TARGET_SCHEMA}.item_type",
        },
        # Only migrate rows whose item_type exists in the enum
        "source_filter": f"item_type IN ({', '.join(repr(v) for v in ITEM_TYPE_MAP.values())})",
    },
    {
        "name": "folder_shares",
        "source": "folder_shares",
        "target": f"{TARGET_SCHEMA}.folder_shares",
        "columns": [
            "id", "folder_id", "user_id", "user_username",
            "role", "created_at",
        ],
    },
    {
        "name": "user_folder_favorites",
        "source": "user_folder_favorites",
        "target": f"{TARGET_SCHEMA}.user_folder_favorites",
        "columns": [
            "id", "user_id", "folder_id", "created_at",
        ],
    },
]


def migrate_table(source_engine, target_engine, table_def):
    """Migrate a single table from source to target database."""
    name = table_def["name"]
    source_table = table_def["source"]
    target_table = table_def["target"]
    columns = table_def["columns"]
    casts = table_def.get("casts", {})
    source_filter = table_def.get("source_filter")

    columns_csv = ", ".join(columns)
    # Use cast expressions for columns that need type conversion
    placeholders = ", ".join(casts.get(col, f":{col}") for col in columns)

    print(f"\n{'='*60}")
    print(f"Migrating: {name}")
    print(f"  Source: mint_db.public.{source_table}")
    print(f"  Target: global_db.{target_table}")

    # Check if source table exists
    with source_engine.connect() as src:
        exists = src.execute(text(
            "SELECT EXISTS ("
            "  SELECT FROM information_schema.tables "
            "  WHERE table_schema = 'public' AND table_name = :table_name"
            ")"
        ), {"table_name": source_table}).scalar()

        if not exists:
            print(f"  SKIPPED: Source table '{source_table}' does not exist")
            return 0

    # Build source query with optional filter
    where_clause = f" WHERE {source_filter}" if source_filter else ""

    # Count source rows
    with source_engine.connect() as src:
        source_count = src.execute(
            text(f"SELECT COUNT(*) FROM {source_table}{where_clause}")
        ).scalar()
        print(f"  Source rows: {source_count}")

    if source_count == 0:
        print(f"  No rows to migrate.")
        return 0

    # Read all rows from source (with filter if specified)
    with source_engine.connect() as src:
        rows = src.execute(
            text(f"SELECT {columns_csv} FROM {source_table}{where_clause}")
        ).mappings().all()

    # Insert into target in batches
    inserted = 0
    skipped = 0
    insert_sql = text(
        f"INSERT INTO {target_table} ({columns_csv}) "
        f"VALUES ({placeholders}) "
        f"ON CONFLICT (id) DO NOTHING"
    )

    with target_engine.connect() as tgt:
        for i in range(0, len(rows), BATCH_SIZE):
            batch = rows[i : i + BATCH_SIZE]
            result = tgt.execute(insert_sql, [dict(row) for row in batch])
            batch_inserted = result.rowcount
            inserted += batch_inserted
            skipped += len(batch) - batch_inserted
            print(f"    Batch {i // BATCH_SIZE + 1}: {batch_inserted}/{len(batch)} inserted")
        tgt.commit()

    # Verify target count
    with target_engine.connect() as tgt:
        target_count = tgt.execute(
            text(f"SELECT COUNT(*) FROM {target_table}")
        ).scalar()

    print(f"  Results:")
    print(f"    Inserted:       {inserted}")
    print(f"    Skipped (dups): {skipped}")
    print(f"    Target count:   {target_count}")

    status = "OK" if target_count >= source_count else f"WARNING: Target ({target_count}) < Source ({source_count})"
    print(f"    Status: {status}")

    return inserted


def migrate_all():
    """Migrate all folder-related tables from mint_db to global_db."""
    print("=" * 60)
    print("Folder Data Migration: mint_db → global_db.global_schema")
    print("=" * 60)

    source_engine = create_engine(SOURCE_DB_URL)
    target_engine = create_engine(TARGET_DB_URL)

    total_inserted = 0

    # Migrate tables in order (respecting FK dependencies)
    for table_def in TABLES:
        inserted = migrate_table(source_engine, target_engine, table_def)
        total_inserted += inserted

    print(f"\n{'='*60}")
    print(f"Migration complete. Total rows inserted: {total_inserted}")
    print("=" * 60)


if __name__ == "__main__":
    migrate_all()
