---
name: backend-migrations
description: Doctrine database migration best practices for PHP/Symfony. Use when creating migration files in api/migrations/, implementing up() and down() methods for reversibility, keeping migrations small and focused on single logical changes, separating schema changes from data migrations, or creating indexes on large tables. Activates when running `php bin/console doctrine:migrations:*` commands, modifying database schema, or handling zero-downtime deployments.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When creating new migration files in `api/migrations/`
- When implementing `up()` and `down()` methods for reversible migrations
- When keeping migrations focused on a single logical change
- When separating schema changes from data migrations
- When creating indexes on large tables using concurrent options
- When ensuring zero-downtime deployments with backwards-compatible changes
- When using clear, descriptive migration class names
- When running `doctrine:migrations:diff` or `doctrine:migrations:generate`
- When reviewing migration order and dependencies
- When never modifying already-deployed migrations

# Backend Migrations

## Commands

```bash
# Generate a new migration (from inside the container)
task screen:shell
alembic revision --autogenerate -m "add_source_status_column"

# Apply migrations
task migrate

# Check current version
task migrate:status

# ⚠️ After any schema change, reset the test environment:
task api:test:integration:setup
```

## Migration Example (Real Basil Pattern)

```python
# api/migrations/versions/20240315_add_source_status.py
"""add source status column

Revision ID: a1b2c3d4e5f6
Revises: 9z8y7x6w5v4u
Create Date: 2024-03-15 10:00:00
"""
from alembic import op
import sqlalchemy as sa

revision = 'a1b2c3d4e5f6'
down_revision = '9z8y7x6w5v4u'
branch_labels = None
depends_on = None


def upgrade() -> None:
    # One logical change per migration
    op.add_column('source', sa.Column(
        'status',
        sa.String(length=50),
        nullable=False,
        server_default='active',
    ))
    op.create_index('ix_source_status', 'source', ['status'])


def downgrade() -> None:
    # Always implement down() for safe rollback
    op.drop_index('ix_source_status', table_name='source')
    op.drop_column('source', 'status')
```

## Rules

- **Reversible Migrations**: Always implement `downgrade()` to enable safe rollbacks
- **Small, Focused Changes**: One migration = one logical change
- **Zero-Downtime Deployments**: Add nullable columns before making them NOT NULL; add index before enforcing constraint
- **Separate Schema and Data**: Schema changes in one migration, data backfills in another
- **Never Modify Deployed Migrations**: Create a new migration to correct a mistake
- **Reset Test Environment**: Always run `task api:test:integration:setup` after schema changes
