"""Unit tests for refactored TokenManager with global token balance.

NOTE: These tests are skipped because TokenManager has been migrated to global-service
as part of Story #870 (Phase 2 microservices migration). The token management functionality
is now tested in global-service/tests/test_token_manager.py.

Tests cover:
- get_balance() returns correct balance
- add_tokens() creates transaction and updates balance
- consume_tokens() with sufficient balance
- consume_tokens() with insufficient balance (InsufficientTokensException)
- consume_tokens() checks module enablement
- get_transaction_history() with filters
"""

from datetime import datetime, timedelta, timezone

import pytest

from app.models.organization import (
    ModuleName,
    Organization,
    OrganizationModule,
    ReferenceType,
    TokenTransaction,
    TransactionType,
)
from app.services.token_manager import (
    TOKENS_PER_COMPANY,
    InsufficientTokensException,
    ModuleNotEnabledException,
    TokenManager,
)

# Skip entire module - TokenManager migrated to global-service (Story #870)
pytestmark = pytest.mark.skip(
    reason="TokenManager migrated to global-service - see global-service/tests/test_token_manager.py"
)


class TestGetBalance:
    """Tests for TokenManager.get_balance() method."""

    def test_get_balance_returns_correct_balance(self, db_session):
        """Test get_balance() returns the correct token balance for an organization."""
        # Setup: Create an organization with a known balance
        org_id = "test-org-uuid-123"
        initial_balance = 500

        org = Organization(organization_id=org_id, token_balance=initial_balance)
        db_session.add(org)
        db_session.commit()

        # Execute
        token_manager = TokenManager(db_session)
        balance = token_manager.get_balance(org_id)

        # Verify
        assert balance == initial_balance

    def test_get_balance_returns_zero_for_new_organization(self, db_session):
        """Test get_balance() returns 0 for organization without token record (lazy init)."""
        org_id = "new-org-uuid-456"

        token_manager = TokenManager(db_session)
        balance = token_manager.get_balance(org_id)

        # New organization should have 0 balance via lazy initialization
        assert balance == 0

        # Verify organization record was created
        org = db_session.query(Organization).filter_by(organization_id=org_id).first()
        assert org is not None
        assert org.token_balance == 0


class TestAddTokens:
    """Tests for TokenManager.add_tokens() method."""

    def test_add_tokens_creates_transaction_and_updates_balance(self, db_session):
        """Test add_tokens() creates transaction record and updates balance."""
        org_id = "test-org-uuid-789"
        user_id = "admin-user-uuid-123"
        initial_balance = 100
        tokens_to_add = 350

        # Setup: Create organization with initial balance
        org = Organization(organization_id=org_id, token_balance=initial_balance)
        db_session.add(org)
        db_session.commit()

        # Execute
        token_manager = TokenManager(db_session)
        updated_org = token_manager.add_tokens(
            org_id=org_id,
            amount=tokens_to_add,
            user_id=user_id,
        )

        # Verify balance updated
        assert updated_org.token_balance == initial_balance + tokens_to_add

        # Verify transaction record created
        transaction = (
            db_session.query(TokenTransaction)
            .filter_by(organization_id=org_id)
            .order_by(TokenTransaction.created_at.desc())
            .first()
        )
        assert transaction is not None
        assert transaction.amount == tokens_to_add
        assert transaction.balance_after == initial_balance + tokens_to_add
        assert transaction.transaction_type == TransactionType.add
        assert transaction.reference_type == ReferenceType.manual
        assert transaction.created_by == user_id

    def test_add_tokens_creates_organization_if_not_exists(self, db_session):
        """Test add_tokens() creates organization record via lazy initialization."""
        org_id = "lazy-init-org-uuid"
        user_id = "admin-user-uuid"
        tokens_to_add = 175

        token_manager = TokenManager(db_session)
        updated_org = token_manager.add_tokens(
            org_id=org_id,
            amount=tokens_to_add,
            user_id=user_id,
        )

        # Verify organization created with correct balance
        assert updated_org.organization_id == org_id
        assert updated_org.token_balance == tokens_to_add

        # Verify transaction created
        transaction = (
            db_session.query(TokenTransaction).filter_by(organization_id=org_id).first()
        )
        assert transaction is not None
        assert transaction.amount == tokens_to_add
        assert transaction.balance_after == tokens_to_add

    def test_add_tokens_raises_on_invalid_amount(self, db_session):
        """Test add_tokens() raises ValueError for non-positive amounts."""
        org_id = "test-org"
        user_id = "admin-user"

        token_manager = TokenManager(db_session)

        with pytest.raises(ValueError, match="positive"):
            token_manager.add_tokens(org_id=org_id, amount=0, user_id=user_id)

        with pytest.raises(ValueError, match="positive"):
            token_manager.add_tokens(org_id=org_id, amount=-10, user_id=user_id)


class TestConsumeTokens:
    """Tests for TokenManager.consume_tokens() method."""

    def test_consume_tokens_with_sufficient_balance(self, db_session):
        """Test consume_tokens() succeeds when balance is sufficient."""
        org_id = "consume-test-org"
        user_id = "user-uuid-123"
        initial_balance = 100
        company_id = "company-123"

        # Setup: Create organization with balance and enabled module
        org = Organization(organization_id=org_id, token_balance=initial_balance)
        db_session.add(org)

        module = OrganizationModule(
            organization_id=org_id,
            module_name=ModuleName.SCREEN,
            enabled=True,
        )
        db_session.add(module)
        db_session.commit()

        # Execute
        token_manager = TokenManager(db_session)
        updated_org = token_manager.consume_tokens(
            org_id=org_id,
            amount=TOKENS_PER_COMPANY,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.company,
            reference_id=company_id,
            user_id=user_id,
        )

        # Verify balance decreased
        assert updated_org.token_balance == initial_balance - TOKENS_PER_COMPANY

        # Verify transaction record created
        transaction = (
            db_session.query(TokenTransaction)
            .filter_by(organization_id=org_id)
            .order_by(TokenTransaction.created_at.desc())
            .first()
        )
        assert transaction is not None
        assert transaction.amount == -TOKENS_PER_COMPANY  # Negative for consume
        assert transaction.balance_after == initial_balance - TOKENS_PER_COMPANY
        assert transaction.transaction_type == TransactionType.consume
        assert transaction.reference_type == ReferenceType.company
        assert transaction.reference_id == company_id
        assert transaction.created_by == user_id

    def test_consume_tokens_with_insufficient_balance(self, db_session):
        """Test consume_tokens() raises InsufficientTokensException when balance too low."""
        org_id = "insufficient-balance-org"
        user_id = "user-uuid"
        initial_balance = 10  # Less than TOKENS_PER_COMPANY (35)

        # Setup: Create organization with low balance and enabled module
        org = Organization(organization_id=org_id, token_balance=initial_balance)
        db_session.add(org)

        module = OrganizationModule(
            organization_id=org_id,
            module_name=ModuleName.SCREEN,
            enabled=True,
        )
        db_session.add(module)
        db_session.commit()

        # Execute and verify exception
        token_manager = TokenManager(db_session)
        with pytest.raises(InsufficientTokensException) as exc_info:
            token_manager.consume_tokens(
                org_id=org_id,
                amount=TOKENS_PER_COMPANY,
                module_name=ModuleName.SCREEN,
                reference_type=ReferenceType.company,
                reference_id="company-456",
                user_id=user_id,
            )

        # Verify exception details
        assert exc_info.value.status_code == 402
        error_detail = exc_info.value.detail
        assert error_detail["current_balance"] == initial_balance
        assert error_detail["required_tokens"] == TOKENS_PER_COMPANY

        # Verify balance was not changed
        db_session.refresh(org)
        assert org.token_balance == initial_balance

        # Verify no transaction was created
        transaction_count = (
            db_session.query(TokenTransaction).filter_by(organization_id=org_id).count()
        )
        assert transaction_count == 0

    def test_consume_tokens_checks_module_enablement(self, db_session):
        """Test consume_tokens() raises ModuleNotEnabledException for disabled module."""
        org_id = "disabled-module-org"
        user_id = "user-uuid"
        initial_balance = 500

        # Setup: Create organization with balance but disabled module
        org = Organization(organization_id=org_id, token_balance=initial_balance)
        db_session.add(org)

        module = OrganizationModule(
            organization_id=org_id,
            module_name=ModuleName.SCREEN,
            enabled=False,  # Module is disabled
        )
        db_session.add(module)
        db_session.commit()

        # Execute and verify exception
        token_manager = TokenManager(db_session)
        with pytest.raises(ModuleNotEnabledException) as exc_info:
            token_manager.consume_tokens(
                org_id=org_id,
                amount=TOKENS_PER_COMPANY,
                module_name=ModuleName.SCREEN,
                reference_type=ReferenceType.company,
                reference_id="company-789",
                user_id=user_id,
            )

        assert exc_info.value.status_code == 403
        assert "not enabled" in str(exc_info.value.detail).lower()

        # Verify balance was not changed
        db_session.refresh(org)
        assert org.token_balance == initial_balance

    def test_consume_tokens_module_not_found_creates_disabled(self, db_session):
        """Test consume_tokens() raises exception when module record doesn't exist."""
        org_id = "no-module-org"
        user_id = "user-uuid"
        initial_balance = 500

        # Setup: Create organization without any module records
        org = Organization(organization_id=org_id, token_balance=initial_balance)
        db_session.add(org)
        db_session.commit()

        # Execute - should raise because module doesn't exist (and thus not enabled)
        token_manager = TokenManager(db_session)
        with pytest.raises(ModuleNotEnabledException):
            token_manager.consume_tokens(
                org_id=org_id,
                amount=TOKENS_PER_COMPANY,
                module_name=ModuleName.SCREEN,
                reference_type=ReferenceType.company,
                reference_id="company-xyz",
                user_id=user_id,
            )


class TestGetTransactionHistory:
    """Tests for TokenManager.get_transaction_history() method."""

    def test_get_transaction_history_returns_all_transactions(self, db_session):
        """Test get_transaction_history() returns transactions for organization."""
        org_id = "history-test-org"
        user_id = "admin-user"

        # Setup: Create organization
        org = Organization(organization_id=org_id, token_balance=500)
        db_session.add(org)
        db_session.commit()

        # Create several transactions
        transactions_data = [
            (100, 100, TransactionType.add, ReferenceType.manual),
            (200, 300, TransactionType.add, ReferenceType.manual),
            (-35, 265, TransactionType.consume, ReferenceType.company),
            (100, 365, TransactionType.add, ReferenceType.manual),
            (-35, 330, TransactionType.consume, ReferenceType.company),
        ]

        for amount, balance_after, trans_type, ref_type in transactions_data:
            transaction = TokenTransaction(
                organization_id=org_id,
                amount=amount,
                balance_after=balance_after,
                transaction_type=trans_type,
                reference_type=ref_type,
                created_by=user_id,
            )
            db_session.add(transaction)
        db_session.commit()

        # Execute
        token_manager = TokenManager(db_session)
        history = token_manager.get_transaction_history(org_id=org_id)

        # Verify all transactions returned
        assert len(history) == 5

    def test_get_transaction_history_with_type_filter(self, db_session):
        """Test get_transaction_history() filters by transaction_type."""
        org_id = "filter-test-org"
        user_id = "admin-user"

        # Setup
        org = Organization(organization_id=org_id, token_balance=500)
        db_session.add(org)
        db_session.commit()

        # Create mix of transaction types
        for i in range(3):
            db_session.add(
                TokenTransaction(
                    organization_id=org_id,
                    amount=100,
                    balance_after=100 * (i + 1),
                    transaction_type=TransactionType.add,
                    reference_type=ReferenceType.manual,
                    created_by=user_id,
                )
            )

        for i in range(2):
            db_session.add(
                TokenTransaction(
                    organization_id=org_id,
                    amount=-35,
                    balance_after=300 - 35 * (i + 1),
                    transaction_type=TransactionType.consume,
                    reference_type=ReferenceType.company,
                    created_by=user_id,
                )
            )
        db_session.commit()

        # Execute with filter
        token_manager = TokenManager(db_session)
        history = token_manager.get_transaction_history(
            org_id=org_id, transaction_type=TransactionType.consume
        )

        # Verify only consume transactions returned
        assert len(history) == 2
        assert all(t.transaction_type == TransactionType.consume for t in history)

    def test_get_transaction_history_with_reference_type_filter(self, db_session):
        """Test get_transaction_history() filters by reference_type."""
        org_id = "ref-filter-test-org"
        user_id = "admin-user"

        # Setup
        org = Organization(organization_id=org_id, token_balance=500)
        db_session.add(org)
        db_session.commit()

        # Create transactions with different reference types
        db_session.add(
            TokenTransaction(
                organization_id=org_id,
                amount=-35,
                balance_after=465,
                transaction_type=TransactionType.consume,
                reference_type=ReferenceType.company,
                created_by=user_id,
            )
        )
        db_session.add(
            TokenTransaction(
                organization_id=org_id,
                amount=-70,
                balance_after=395,
                transaction_type=TransactionType.consume,
                reference_type=ReferenceType.csv_import,
                created_by=user_id,
            )
        )
        db_session.add(
            TokenTransaction(
                organization_id=org_id,
                amount=100,
                balance_after=495,
                transaction_type=TransactionType.add,
                reference_type=ReferenceType.manual,
                created_by=user_id,
            )
        )
        db_session.commit()

        # Execute with filter
        token_manager = TokenManager(db_session)
        history = token_manager.get_transaction_history(
            org_id=org_id, reference_type=ReferenceType.company
        )

        # Verify only company reference type returned
        assert len(history) == 1
        assert history[0].reference_type == ReferenceType.company

    def test_get_transaction_history_with_date_filters(self, db_session):
        """Test get_transaction_history() filters by date range."""
        org_id = "date-filter-test-org"
        user_id = "admin-user"

        # Setup
        org = Organization(organization_id=org_id, token_balance=500)
        db_session.add(org)
        db_session.commit()

        # Create transactions with different dates
        now = datetime.now(timezone.utc)
        yesterday = now - timedelta(days=1)
        last_week = now - timedelta(days=7)

        for i, date in enumerate([last_week, yesterday, now]):
            trans = TokenTransaction(
                organization_id=org_id,
                amount=100,
                balance_after=100 * (i + 1),
                transaction_type=TransactionType.add,
                reference_type=ReferenceType.manual,
                created_by=user_id,
            )
            db_session.add(trans)
            db_session.flush()
            # Update created_at directly
            trans.created_at = date
        db_session.commit()

        # Execute with date filter - last 2 days
        token_manager = TokenManager(db_session)
        history = token_manager.get_transaction_history(
            org_id=org_id,
            date_from=now - timedelta(days=2),
            date_to=now + timedelta(hours=1),
        )

        # Verify only recent transactions returned
        assert len(history) == 2

    def test_get_transaction_history_pagination(self, db_session):
        """Test get_transaction_history() supports pagination."""
        org_id = "pagination-test-org"
        user_id = "admin-user"

        # Setup
        org = Organization(organization_id=org_id, token_balance=500)
        db_session.add(org)
        db_session.commit()

        # Create 10 transactions
        for i in range(10):
            db_session.add(
                TokenTransaction(
                    organization_id=org_id,
                    amount=100,
                    balance_after=100 * (i + 1),
                    transaction_type=TransactionType.add,
                    reference_type=ReferenceType.manual,
                    created_by=user_id,
                )
            )
        db_session.commit()

        # Execute with pagination
        token_manager = TokenManager(db_session)

        # Page 1
        page1 = token_manager.get_transaction_history(org_id=org_id, page=1, size=3)
        assert len(page1) == 3

        # Page 2
        page2 = token_manager.get_transaction_history(org_id=org_id, page=2, size=3)
        assert len(page2) == 3

        # Ensure different items
        page1_ids = {t.id for t in page1}
        page2_ids = {t.id for t in page2}
        assert page1_ids.isdisjoint(page2_ids)


class TestTokenConstants:
    """Tests for token-related constants."""

    def test_tokens_per_company_constant(self):
        """Verify TOKENS_PER_COMPANY constant is 35."""
        assert TOKENS_PER_COMPANY == 35
