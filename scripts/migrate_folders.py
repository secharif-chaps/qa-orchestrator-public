#!/usr/bin/env python3
"""
Data migration script: Copy folders from mint_db to global_db.global_schema.

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
TARGET_TABLE = f"{TARGET_SCHEMA}.folders"

# Columns to migrate (same order for SELECT and INSERT)
COLUMNS = [
    "id",
    "organization_id",
    "owner_id",
    "owner",
    "name",
    "color",
    "icon",
    "tags",
    "is_deleted",
    "is_orphaned",
    "created_at",
    "updated_at",
]

COLUMNS_CSV = ", ".join(COLUMNS)
PLACEHOLDERS = ", ".join(f":{col}" for col in COLUMNS)


def migrate_folders():
    """Copy all folders from mint_db.public.folders to global_db.global_schema.folders."""
    source_engine = create_engine(SOURCE_DB_URL)
    target_engine = create_engine(TARGET_DB_URL)

    # Count source rows
    with source_engine.connect() as src:
        source_count = src.execute(text("SELECT COUNT(*) FROM folders")).scalar()
        print(f"Source (mint_db.folders): {source_count} rows")

    if source_count == 0:
        print("No folders to migrate.")
        return

    # Read all rows from source
    with source_engine.connect() as src:
        rows = src.execute(text(f"SELECT {COLUMNS_CSV} FROM folders")).mappings().all()

    # Insert into target in batches
    inserted = 0
    skipped = 0
    insert_sql = text(
        f"INSERT INTO {TARGET_TABLE} ({COLUMNS_CSV}) "
        f"VALUES ({PLACEHOLDERS}) "
        f"ON CONFLICT (id) DO NOTHING"
    )

    with target_engine.connect() as tgt:
        for i in range(0, len(rows), BATCH_SIZE):
            batch = rows[i : i + BATCH_SIZE]
            result = tgt.execute(insert_sql, [dict(row) for row in batch])
            batch_inserted = result.rowcount
            inserted += batch_inserted
            skipped += len(batch) - batch_inserted
            print(f"  Batch {i // BATCH_SIZE + 1}: {batch_inserted}/{len(batch)} inserted")
        tgt.commit()

    # Verify target count
    with target_engine.connect() as tgt:
        target_count = tgt.execute(
            text(f"SELECT COUNT(*) FROM {TARGET_TABLE}")
        ).scalar()

    print(f"\nMigration complete:")
    print(f"  Source count:   {source_count}")
    print(f"  Inserted:       {inserted}")
    print(f"  Skipped (dups): {skipped}")
    print(f"  Target count:   {target_count}")

    if target_count >= source_count:
        print("  Status: OK")
    else:
        print(f"  WARNING: Target ({target_count}) < Source ({source_count})")


if __name__ == "__main__":
    migrate_folders()
