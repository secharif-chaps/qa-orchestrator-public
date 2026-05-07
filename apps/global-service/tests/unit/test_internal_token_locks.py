"""Endpoint tests for the token lock lifecycle internal API (TAR-1569).

These tests exercise the three endpoints exposed under
``/api/internal/organizations/{org_id}/tokens/...`` :

- POST .../tokens/lock                  — reserve tokens
- POST .../tokens/{lock_id}/confirm     — debit + create transaction
- POST .../tokens/{lock_id}/release     — drop reservation

Acceptance scenarios covered (from the story):

1. Reserve OK
2. Confirm
3. Release
4. Insufficient
5. Expired lock
6. Double confirm/release
"""

from datetime import UTC, datetime, timedelta
from uuid import UUID

import pytest
from sqlalchemy import select

from app.core.internal_jwt import InternalTokenPayload, get_internal_token
from app.models.organization import (
    ModuleName,
    Organization,
    OrganizationModule,
    TokenLock,
    TokenLockStatus,
    TokenTransaction,
)

ORG_ID = "12345678-1234-4234-a234-bbbbbbbbbbbb"
USER_ID = "user-1"
USERNAME = "testuser"


def _payload(org_id: str = ORG_ID) -> InternalTokenPayload:
    return InternalTokenPayload(
        sub=USER_ID,
        username=USERNAME,
        org_id=org_id,
        org_name="Test Org",
        roles=["company.create"],
    )


@pytest.fixture
async def seed_org(global_db_session):
    """Seed an organization with 100 tokens and the Screen module enabled."""
    org = Organization(organization_id=ORG_ID, token_balance=100)
    global_db_session.add(org)
    module = OrganizationModule(
        organization_id=ORG_ID,
        module_name=ModuleName.SCREEN,
        enabled=True,
    )
    global_db_session.add(module)
    await global_db_session.commit()
    return org


# ── Scenario 1: Reserve OK ───────────────────────────────────────────────────


class TestLockEndpoint:
    """POST /api/internal/organizations/{org_id}/tokens/lock."""

    @pytest.mark.asyncio
    async def test_reserve_success_returns_201_and_available_balance(self, client, seed_org):
        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        response = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/lock",
            json={
                "amount": 5,
                "module_name": "screen",
                "correlation_id": "corr-reserve-ok",
                "reference_id": "company-7",
            },
        )

        assert response.status_code == 201
        body = response.json()
        assert body["amount"] == 5
        assert body["status"] == "locked"
        assert body["organization_id"] == ORG_ID
        assert body["available_balance"] == 95
        assert "lock_id" in body
        assert "expires_at" in body

    @pytest.mark.asyncio
    async def test_reserve_idempotent_on_correlation_id(self, client, seed_org):
        """Calling lock twice with the same correlation_id returns the same lock."""
        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        body = {
            "amount": 5,
            "module_name": "screen",
            "correlation_id": "corr-idempotent-endpoint",
        }
        first = await client.post(f"/api/internal/organizations/{ORG_ID}/tokens/lock", json=body)
        second = await client.post(f"/api/internal/organizations/{ORG_ID}/tokens/lock", json=body)

        assert first.status_code == 201
        assert second.status_code == 201
        assert first.json()["lock_id"] == second.json()["lock_id"]

    @pytest.mark.asyncio
    async def test_reserve_org_id_mismatch_is_403(self, client, seed_org):
        """Path organization_id != token org_id → 403."""
        client.app.dependency_overrides[get_internal_token] = lambda: _payload(
            org_id="00000000-0000-4000-a000-000000000999"
        )

        response = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/lock",
            json={
                "amount": 1,
                "module_name": "screen",
                "correlation_id": "corr-mismatch",
            },
        )
        assert response.status_code == 403


# ── Scenario 2: Confirm ──────────────────────────────────────────────────────


class TestConfirmEndpoint:
    """POST /api/internal/organizations/{org_id}/tokens/{lock_id}/confirm."""

    @pytest.mark.asyncio
    async def test_confirm_debits_balance_and_returns_transaction(self, client, global_db_session, seed_org):
        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        # Step 1: lock 5 tokens.
        lock_resp = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/lock",
            json={
                "amount": 5,
                "module_name": "screen",
                "correlation_id": "corr-confirm-flow",
                "reference_id": "company-9",
            },
        )
        assert lock_resp.status_code == 201
        lock_id = lock_resp.json()["lock_id"]

        # Step 2: confirm.
        confirm_resp = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/{lock_id}/confirm",
            json={"reference_type": "company"},
        )

        assert confirm_resp.status_code == 200
        body = confirm_resp.json()
        assert body["success"] is True
        assert body["lock_id"] == lock_id
        assert body["balance"] == 95
        assert body["transaction"]["amount"] == -5
        assert body["transaction"]["balance_after"] == 95
        assert body["transaction"]["reference_type"] == "company"
        assert body["transaction"]["reference_id"] == "company-9"

        # Verify lock status persisted.
        result = await global_db_session.execute(select(TokenLock).filter(TokenLock.id == UUID(lock_id)))
        lock = result.scalar_one()
        assert lock.status == TokenLockStatus.confirmed


# ── Scenario 3: Release ──────────────────────────────────────────────────────


class TestReleaseEndpoint:
    """POST /api/internal/organizations/{org_id}/tokens/{lock_id}/release."""

    @pytest.mark.asyncio
    async def test_release_does_not_debit_balance(self, client, global_db_session, seed_org):
        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        lock_resp = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/lock",
            json={
                "amount": 5,
                "module_name": "screen",
                "correlation_id": "corr-release-flow",
            },
        )
        assert lock_resp.status_code == 201
        lock_id = lock_resp.json()["lock_id"]

        release_resp = await client.post(f"/api/internal/organizations/{ORG_ID}/tokens/{lock_id}/release")

        assert release_resp.status_code == 200
        body = release_resp.json()
        assert body["success"] is True
        assert body["status"] == "released"
        assert body["lock_id"] == lock_id

        # Token balance must be unchanged after release.
        org_result = await global_db_session.execute(
            select(Organization).filter(Organization.organization_id == ORG_ID)
        )
        org = org_result.scalar_one()
        assert org.token_balance == 100

        # No transaction should exist.
        tx_result = await global_db_session.execute(
            select(TokenTransaction).filter(TokenTransaction.organization_id == ORG_ID)
        )
        assert tx_result.scalar_one_or_none() is None


# ── Scenario 4: Insufficient ─────────────────────────────────────────────────


class TestInsufficientBalance:
    """POST .../tokens/lock with available_balance < amount → 402."""

    @pytest.mark.asyncio
    async def test_lock_insufficient_returns_402(self, client, global_db_session):
        # Seed an org with only 3 tokens.
        org = Organization(organization_id=ORG_ID, token_balance=3)
        module = OrganizationModule(
            organization_id=ORG_ID,
            module_name=ModuleName.SCREEN,
            enabled=True,
        )
        global_db_session.add_all([org, module])
        await global_db_session.commit()

        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        response = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/lock",
            json={
                "amount": 5,
                "module_name": "screen",
                "correlation_id": "corr-insufficient-endpoint",
            },
        )

        assert response.status_code == 402
        detail = response.json()["detail"]
        assert detail["current_balance"] == 3
        assert detail["required_tokens"] == 5

        # No lock should have been persisted.
        result = await global_db_session.execute(select(TokenLock).filter(TokenLock.organization_id == ORG_ID))
        assert result.scalar_one_or_none() is None


# ── Scenario 5: Expired lock ─────────────────────────────────────────────────


class TestExpiredLock:
    """Confirming/releasing an expired lock → 409 with status='expired'."""

    @pytest.mark.asyncio
    async def test_confirm_expired_returns_409(self, client, global_db_session, seed_org):
        # Create a lock that's already expired.
        now = datetime.now(UTC)
        expired = TokenLock(
            organization_id=ORG_ID,
            amount=5,
            module=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-expired-endpoint",
            status=TokenLockStatus.locked,
            locked_at=now - timedelta(minutes=10),
            expires_at=now - timedelta(seconds=1),
        )
        global_db_session.add(expired)
        await global_db_session.commit()
        await global_db_session.refresh(expired)
        lock_id = str(expired.id)

        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        response = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/{lock_id}/confirm",
            json={"reference_type": "company"},
        )

        assert response.status_code == 409
        detail = response.json()["detail"]
        assert detail["status"] == "expired"

        # Side effect: the lock has been auto-marked as 'expired'.
        await global_db_session.refresh(expired)
        assert expired.status == TokenLockStatus.expired


# ── Scenario 6: Double confirm/release ───────────────────────────────────────


class TestDoubleSettle:
    """Repeating a settle operation on the same lock → 409."""

    @pytest.mark.asyncio
    async def test_double_confirm_returns_409(self, client, seed_org):
        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        lock_resp = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/lock",
            json={
                "amount": 5,
                "module_name": "screen",
                "correlation_id": "corr-double-confirm-endpoint",
            },
        )
        lock_id = lock_resp.json()["lock_id"]

        first = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/{lock_id}/confirm",
            json={"reference_type": "company"},
        )
        second = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/{lock_id}/confirm",
            json={"reference_type": "company"},
        )

        assert first.status_code == 200
        assert second.status_code == 409
        detail = second.json()["detail"]
        assert detail["status"] == "confirmed"

    @pytest.mark.asyncio
    async def test_double_release_returns_409(self, client, seed_org):
        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        lock_resp = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/lock",
            json={
                "amount": 5,
                "module_name": "screen",
                "correlation_id": "corr-double-release-endpoint",
            },
        )
        lock_id = lock_resp.json()["lock_id"]

        first = await client.post(f"/api/internal/organizations/{ORG_ID}/tokens/{lock_id}/release")
        second = await client.post(f"/api/internal/organizations/{ORG_ID}/tokens/{lock_id}/release")

        assert first.status_code == 200
        assert second.status_code == 409
        detail = second.json()["detail"]
        assert detail["status"] == "released"


# ── Lock not found ───────────────────────────────────────────────────────────


class TestLockNotFound:
    """Confirming/releasing a missing lock → 404."""

    @pytest.mark.asyncio
    async def test_confirm_unknown_lock_returns_404(self, client, seed_org):
        import uuid

        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        random_lock = uuid.uuid4()
        response = await client.post(
            f"/api/internal/organizations/{ORG_ID}/tokens/{random_lock}/confirm",
            json={"reference_type": "company"},
        )
        assert response.status_code == 404

    @pytest.mark.asyncio
    async def test_release_unknown_lock_returns_404(self, client, seed_org):
        import uuid

        client.app.dependency_overrides[get_internal_token] = lambda: _payload()

        random_lock = uuid.uuid4()
        response = await client.post(f"/api/internal/organizations/{ORG_ID}/tokens/{random_lock}/release")
        assert response.status_code == 404
