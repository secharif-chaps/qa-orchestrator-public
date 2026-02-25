#!/usr/bin/env python3
"""Data migration script: Copy user preferences from backend to global-service.

This script migrates user_preferences data from the backend's mint_db to
global-service's global_db.global_schema for Phase 4.6 of the Global Service
API Gateway migration.

IMPORTANT: The monolith stores usernames in the `keycloak_user_id` column
(naming mismatch). The global-service uses actual Keycloak UUIDs (from JWT sub).
This script resolves usernames to UUIDs via the Keycloak Admin API.

Tables migrated:
- user_preferences

Usage:
    # From infra directory:
    docker compose exec global-service python scripts/migrate_user_preferences.py

    # With dry-run (no changes):
    docker compose exec global-service python scripts/migrate_user_preferences.py --dry-run

    # Skip Keycloak resolution (store usernames as-is for later mapping):
    docker compose exec global-service python scripts/migrate_user_preferences.py --skip-resolve

Environment variables:
    SOURCE_DATABASE_URL: Backend database URL (default: postgresql://postgres:postgres@db:5432/mint_db)
    DATABASE_URL: Global service database URL (uses app config)
    KEYCLOAK_URL or KEYCLOAK_SERVER_URL: Keycloak base URL for user resolution
    KEYCLOAK_REALM: Keycloak realm name (default: chapsmind)
    KEYCLOAK_ADMIN_CLIENT_ID: Admin client ID for Keycloak API
    KEYCLOAK_ADMIN_CLIENT_SECRET: Admin client secret for Keycloak API
"""

import argparse
import os
import sys
from datetime import datetime

import httpx
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker

# Add app to path for imports
sys.path.insert(0, "/app")

from app.database import GLOBAL_SCHEMA, GlobalSessionLocal
from app.models.user_preferences import UserPreferences


def get_source_engine():
    """Create engine for source database (backend/mint_db)."""
    source_url = os.getenv(
        "SOURCE_DATABASE_URL", "postgresql://postgres:postgres@db:5432/mint_db"
    )
    return create_engine(source_url)


def get_keycloak_token() -> str | None:
    """Get admin access token from Keycloak using client credentials."""
    keycloak_url = os.getenv("KEYCLOAK_URL", "") or os.getenv("KEYCLOAK_SERVER_URL", "")
    realm = os.getenv("KEYCLOAK_REALM", "chapsmind")
    client_id = os.getenv("KEYCLOAK_ADMIN_CLIENT_ID", "")
    client_secret = os.getenv("KEYCLOAK_ADMIN_CLIENT_SECRET", "")

    if not all([keycloak_url, client_id, client_secret]):
        print("  [WARN] Keycloak credentials not configured, cannot resolve usernames")
        return None

    token_url = f"{keycloak_url}/realms/{realm}/protocol/openid-connect/token"
    response = httpx.post(
        token_url,
        data={
            "grant_type": "client_credentials",
            "client_id": client_id,
            "client_secret": client_secret,
        },
        timeout=10,
    )

    if response.status_code != 200:
        print(f"  [WARN] Failed to get Keycloak token: {response.status_code}")
        return None

    return response.json().get("access_token")


def resolve_username_to_uuid(username: str, token: str) -> str | None:
    """Resolve a username to Keycloak user UUID via Admin API.

    Args:
        username: The username to look up
        token: Keycloak admin access token

    Returns:
        User UUID string or None if not found
    """
    keycloak_url = os.getenv("KEYCLOAK_URL", "") or os.getenv("KEYCLOAK_SERVER_URL", "")
    realm = os.getenv("KEYCLOAK_REALM", "chapsmind")

    users_url = f"{keycloak_url}/admin/realms/{realm}/users"
    response = httpx.get(
        users_url,
        params={"username": username, "exact": "true"},
        headers={"Authorization": f"Bearer {token}"},
        timeout=10,
    )

    if response.status_code != 200:
        return None

    users = response.json()
    if not users:
        return None

    return users[0].get("id")


def migrate_user_preferences(
    source_session,
    target_session,
    dry_run: bool = False,
    skip_resolve: bool = False,
):
    """Migrate user_preferences table.

    Args:
        source_session: SQLAlchemy session for source (backend) database
        target_session: SQLAlchemy session for target (global-service) database
        dry_run: If True, show what would be migrated without making changes
        skip_resolve: If True, store usernames as-is without Keycloak resolution
    """
    print("\n=== Migrating user_preferences ===")

    # Read from source
    result = source_session.execute(
        text(
            """
            SELECT keycloak_user_id, preferences, created_at, updated_at
            FROM user_preferences
            ORDER BY id
            """
        )
    )
    rows = result.fetchall()
    print(f"Found {len(rows)} user_preferences in source")

    if not rows:
        return 0

    # Get Keycloak token for username resolution
    keycloak_token = None
    if not skip_resolve:
        print("  Attempting Keycloak username resolution...")
        keycloak_token = get_keycloak_token()
        if keycloak_token:
            print("  [OK] Keycloak token acquired")

    if dry_run:
        for row in rows:
            print(f"  [DRY-RUN] Would migrate: user={row.keycloak_user_id}")
        return len(rows)

    # Check existing in target (by user_id)
    existing = {
        r[0]
        for r in target_session.execute(
            text(f"SELECT user_id FROM {GLOBAL_SCHEMA}.user_preferences")
        ).fetchall()
    }

    migrated = 0
    skipped_resolve = 0
    for row in rows:
        username = row.keycloak_user_id

        # Resolve username to UUID
        user_id = username  # Default: keep as-is
        if keycloak_token and not skip_resolve:
            resolved_uuid = resolve_username_to_uuid(username, keycloak_token)
            if resolved_uuid:
                user_id = resolved_uuid
                print(f"  [RESOLVED] {username} -> {user_id}")
            else:
                print(f"  [WARN] Could not resolve username: {username}, storing as-is")
                skipped_resolve += 1

        if user_id in existing:
            print(f"  [SKIP] Already exists: user_id={user_id}")
            continue

        user_prefs = UserPreferences(
            user_id=user_id,
            preferences=row.preferences or {},
            created_at=row.created_at,
            updated_at=row.updated_at,
        )
        target_session.add(user_prefs)
        migrated += 1
        print(f"  [OK] Migrated: {username} -> user_id={user_id}")

    if skipped_resolve > 0:
        print(f"\n  [WARN] {skipped_resolve} usernames could not be resolved to UUIDs")

    return migrated


def main():
    parser = argparse.ArgumentParser(
        description="Migrate user preferences from backend to global-service"
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Show what would be migrated without making changes",
    )
    parser.add_argument(
        "--skip-resolve",
        action="store_true",
        help="Skip Keycloak username-to-UUID resolution (store usernames as-is)",
    )
    args = parser.parse_args()

    print("=" * 60)
    print("User Preferences Data Migration")
    print(f"Started at: {datetime.now().isoformat()}")
    print(f"Dry run: {args.dry_run}")
    print(f"Skip resolve: {args.skip_resolve}")
    print("=" * 60)

    # Create source session (backend database)
    source_engine = get_source_engine()
    SourceSession = sessionmaker(bind=source_engine)
    source_session = SourceSession()

    # Create target session (global-service database)
    target_session = GlobalSessionLocal()

    try:
        migrated = migrate_user_preferences(
            source_session, target_session, args.dry_run, args.skip_resolve
        )

        if not args.dry_run:
            target_session.commit()
            print("\n[COMMIT] All changes committed successfully")
        else:
            print("\n[DRY-RUN] No changes were made")

        print("\n" + "=" * 60)
        print("Migration Summary")
        print("=" * 60)
        status_label = "would migrate" if args.dry_run else "migrated"
        print(f"  user_preferences: {migrated} rows {status_label}")

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
