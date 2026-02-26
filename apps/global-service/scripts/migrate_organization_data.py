#!/usr/bin/env python3
"""Data migration script: Copy organization data from backend to global-service.

This script migrates data from the backend's chapsmind_db to global-service's
global_db.global_schema for Phase 2.1 of the Global Service API Gateway migration.

Tables migrated:
- organizations
- token_transactions
- organization_modules
- organization_feature_flags

Usage:
    # From infra directory:
    docker compose -f compose.yaml -f compose.local.yaml exec \
        global-service python scripts/migrate_organization_data.py

    # With dry-run (no changes):
    docker compose -f compose.yaml -f compose.local.yaml exec \
        global-service python scripts/migrate_organization_data.py --dry-run

Environment variables:
    SOURCE_DATABASE_URL: Backend database URL (default: postgresql://postgres:postgres@db:5432/chapsmind_db)
    DATABASE_URL: Global service database URL (uses app config)
"""

import argparse
import os
import sys
from datetime import datetime

from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker

# Add app to path for imports
sys.path.insert(0, "/app")

from app.database import GLOBAL_SCHEMA, GlobalSessionLocal
from app.models.organization import (
    FeatureFlag,
    ModuleName,
    Organization,
    OrganizationFeatureFlag,
    OrganizationModule,
    ReferenceType,
    TokenTransaction,
    TransactionType,
)


def get_source_engine():
    """Create engine for source database (backend/chapsmind_db)."""
    source_url = os.getenv(
        "SOURCE_DATABASE_URL", "postgresql://postgres:postgres@db:5432/chapsmind_db"
    )
    return create_engine(source_url)


def migrate_organizations(source_session, target_session, dry_run: bool = False):
    """Migrate organizations table."""
    print("\n=== Migrating organizations ===")

    # Read from source
    result = source_session.execute(
        text(
            "SELECT organization_id, token_balance, created_at, updated_at FROM organizations"
        )
    )
    rows = result.fetchall()
    print(f"Found {len(rows)} organizations in source")

    if dry_run:
        for row in rows:
            print(f"  [DRY-RUN] Would migrate: {row.organization_id}")
        return len(rows)

    # Check existing in target
    existing = {
        r[0]
        for r in target_session.execute(
            text(f"SELECT organization_id FROM {GLOBAL_SCHEMA}.organizations")
        ).fetchall()
    }

    migrated = 0
    for row in rows:
        if row.organization_id in existing:
            print(f"  [SKIP] Already exists: {row.organization_id}")
            continue

        org = Organization(
            organization_id=row.organization_id,
            token_balance=row.token_balance,
            created_at=row.created_at,
            updated_at=row.updated_at,
        )
        target_session.add(org)
        migrated += 1
        print(f"  [OK] Migrated: {row.organization_id}")

    return migrated


def migrate_token_transactions(source_session, target_session, dry_run: bool = False):
    """Migrate token_transactions table."""
    print("\n=== Migrating token_transactions ===")

    result = source_session.execute(
        text(
            """
            SELECT id, organization_id, amount, balance_after, transaction_type,
                   reference_type, reference_id, created_at, created_by
            FROM token_transactions
            ORDER BY id
            """
        )
    )
    rows = result.fetchall()
    print(f"Found {len(rows)} token_transactions in source")

    if dry_run:
        for row in rows:
            print(f"  [DRY-RUN] Would migrate: id={row.id}, org={row.organization_id}")
        return len(rows)

    # Check existing in target
    existing = {
        r[0]
        for r in target_session.execute(
            text(f"SELECT id FROM {GLOBAL_SCHEMA}.token_transactions")
        ).fetchall()
    }

    migrated = 0
    for row in rows:
        if row.id in existing:
            print(f"  [SKIP] Already exists: id={row.id}")
            continue

        # Map string enum values to Python enums
        txn = TokenTransaction(
            id=row.id,
            organization_id=row.organization_id,
            amount=row.amount,
            balance_after=row.balance_after,
            transaction_type=TransactionType(row.transaction_type),
            reference_type=ReferenceType(row.reference_type),
            reference_id=row.reference_id,
            created_at=row.created_at,
            created_by=row.created_by,
        )
        target_session.add(txn)
        migrated += 1
        print(f"  [OK] Migrated: id={row.id}")

    return migrated


def migrate_organization_modules(source_session, target_session, dry_run: bool = False):
    """Migrate organization_modules table."""
    print("\n=== Migrating organization_modules ===")

    result = source_session.execute(
        text(
            """
            SELECT id, organization_id, module_name, enabled, created_at, updated_at
            FROM organization_modules
            ORDER BY id
            """
        )
    )
    rows = result.fetchall()
    print(f"Found {len(rows)} organization_modules in source")

    if dry_run:
        for row in rows:
            print(
                f"  [DRY-RUN] Would migrate: id={row.id}, org={row.organization_id}, module={row.module_name}"
            )
        return len(rows)

    # Check existing in target (by unique constraint: org_id + module_name)
    existing = {
        (r[0], r[1])
        for r in target_session.execute(
            text(
                f"SELECT organization_id, module_name FROM {GLOBAL_SCHEMA}.organization_modules"
            )
        ).fetchall()
    }

    migrated = 0
    for row in rows:
        key = (row.organization_id, row.module_name)
        if key in existing:
            print(
                f"  [SKIP] Already exists: org={row.organization_id}, module={row.module_name}"
            )
            continue

        module = OrganizationModule(
            organization_id=row.organization_id,
            module_name=ModuleName(row.module_name),
            enabled=row.enabled,
            created_at=row.created_at,
            updated_at=row.updated_at,
        )
        target_session.add(module)
        migrated += 1
        print(f"  [OK] Migrated: org={row.organization_id}, module={row.module_name}")

    return migrated


def migrate_organization_feature_flags(
    source_session, target_session, dry_run: bool = False
):
    """Migrate organization_feature_flags table."""
    print("\n=== Migrating organization_feature_flags ===")

    result = source_session.execute(
        text(
            """
            SELECT id, organization_id, flag, enabled, enabled_at, config, created_at, updated_at
            FROM organization_feature_flags
            ORDER BY id
            """
        )
    )
    rows = result.fetchall()
    print(f"Found {len(rows)} organization_feature_flags in source")

    if dry_run:
        for row in rows:
            print(
                f"  [DRY-RUN] Would migrate: id={row.id}, org={row.organization_id}, flag={row.flag}"
            )
        return len(rows)

    # Check existing in target (by unique constraint: org_id + flag)
    existing = {
        (r[0], r[1])
        for r in target_session.execute(
            text(
                f"SELECT organization_id, flag FROM {GLOBAL_SCHEMA}.organization_feature_flags"
            )
        ).fetchall()
    }

    migrated = 0
    for row in rows:
        key = (row.organization_id, row.flag)
        if key in existing:
            print(
                f"  [SKIP] Already exists: org={row.organization_id}, flag={row.flag}"
            )
            continue

        feature_flag = OrganizationFeatureFlag(
            organization_id=row.organization_id,
            flag=FeatureFlag(row.flag),
            enabled=row.enabled,
            enabled_at=row.enabled_at,
            config=row.config,
            created_at=row.created_at,
            updated_at=row.updated_at,
        )
        target_session.add(feature_flag)
        migrated += 1
        print(f"  [OK] Migrated: org={row.organization_id}, flag={row.flag}")

    return migrated


def main():
    parser = argparse.ArgumentParser(
        description="Migrate organization data from backend to global-service"
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Show what would be migrated without making changes",
    )
    args = parser.parse_args()

    print("=" * 60)
    print("Organization Data Migration")
    print(f"Started at: {datetime.now().isoformat()}")
    print(f"Dry run: {args.dry_run}")
    print("=" * 60)

    # Create source session (backend database)
    source_engine = get_source_engine()
    SourceSession = sessionmaker(bind=source_engine)
    source_session = SourceSession()

    # Create target session (global-service database)
    target_session = GlobalSessionLocal()

    try:
        # Migrate in order (respecting foreign keys)
        stats = {
            "organizations": migrate_organizations(
                source_session, target_session, args.dry_run
            ),
            "token_transactions": migrate_token_transactions(
                source_session, target_session, args.dry_run
            ),
            "organization_modules": migrate_organization_modules(
                source_session, target_session, args.dry_run
            ),
            "organization_feature_flags": migrate_organization_feature_flags(
                source_session, target_session, args.dry_run
            ),
        }

        if not args.dry_run:
            target_session.commit()
            print("\n[COMMIT] All changes committed successfully")
        else:
            print("\n[DRY-RUN] No changes were made")

        print("\n" + "=" * 60)
        print("Migration Summary")
        print("=" * 60)
        for table, count in stats.items():
            status = "would migrate" if args.dry_run else "migrated"
            print(f"  {table}: {count} rows {status}")

    except Exception as e:
        target_session.rollback()
        print(f"\n[ERROR] Migration failed: {e}")
        raise
    finally:
        source_session.close()
        target_session.close()

    print(f"\nCompleted at: {datetime.now().isoformat()}")


if __name__ == "__main__":
    main()
