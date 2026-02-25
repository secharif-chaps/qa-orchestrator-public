"""Tests for Organization and TokenTransaction models.

NOTE: These tests are skipped because the token/organization models have been migrated
to global-service as part of Story #870 (Phase 2 microservices migration). The models
are now tested in global-service/tests/test_organization_models.py.

These tests verify the core functionality of the global token system models:
1. Organization model creation with defaults
2. TokenTransaction model with required fields
3. TransactionType and ReferenceType enum validation
4. Relationship between Organization and TokenTransaction
"""

import pytest

# Skip entire module - Models migrated to global-service (Story #870)
pytestmark = pytest.mark.skip(
    reason="Token/Organization models migrated to global-service - see global-service/tests/test_organization_models.py"
)
from datetime import datetime, timezone
from sqlalchemy.exc import IntegrityError

from app.models.organization import (
    Organization,
    TokenTransaction,
    TransactionType,
    ReferenceType,
    OrganizationModule,
    ModuleName,
)


@pytest.fixture
def sample_organization(db_session):
    """Create a sample organization for testing."""
    org = Organization(
        organization_id="org-uuid-123",
        token_balance=100,
    )
    db_session.add(org)
    db_session.commit()
    db_session.refresh(org)
    return org


class TestOrganizationCreation:
    """Test Organization model creation with defaults."""

    def test_create_organization_with_defaults(self, db_session):
        """Test creating an organization with default values."""
        org = Organization(
            organization_id="org-uuid-456",
        )
        db_session.add(org)
        db_session.commit()
        db_session.refresh(org)

        assert org.organization_id == "org-uuid-456"
        assert org.token_balance == 0  # Default value
        assert org.created_at is not None
        # updated_at may be None until first update

    def test_create_organization_with_initial_balance(self, db_session):
        """Test creating an organization with an initial token balance."""
        org = Organization(
            organization_id="org-uuid-789",
            token_balance=500,
        )
        db_session.add(org)
        db_session.commit()
        db_session.refresh(org)

        assert org.organization_id == "org-uuid-789"
        assert org.token_balance == 500

    def test_organization_id_is_primary_key(self, db_session):
        """Test that organization_id is the primary key (unique)."""
        org1 = Organization(organization_id="org-uuid-unique")
        db_session.add(org1)
        db_session.commit()

        org2 = Organization(organization_id="org-uuid-unique")
        db_session.add(org2)

        with pytest.raises(IntegrityError):
            db_session.commit()


class TestTokenTransactionCreation:
    """Test TokenTransaction model with required fields."""

    def test_create_token_transaction_add_type(self, db_session, sample_organization):
        """Test creating a token transaction with add type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=100,
            balance_after=200,
            transaction_type=TransactionType.add,
            reference_type=ReferenceType.manual,
            created_by="admin-uuid-123",
        )
        db_session.add(transaction)
        db_session.commit()
        db_session.refresh(transaction)

        assert transaction.id is not None
        assert transaction.organization_id == sample_organization.organization_id
        assert transaction.amount == 100
        assert transaction.balance_after == 200
        assert transaction.transaction_type == TransactionType.add
        assert transaction.reference_type == ReferenceType.manual
        assert transaction.reference_id is None  # Optional field
        assert transaction.created_at is not None
        assert transaction.created_by == "admin-uuid-123"

    def test_create_token_transaction_consume_type(self, db_session, sample_organization):
        """Test creating a token transaction with consume type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=-35,  # Negative for consumption
            balance_after=65,
            transaction_type=TransactionType.consume,
            reference_type=ReferenceType.company,
            reference_id="company-123",
            created_by="user-uuid-456",
        )
        db_session.add(transaction)
        db_session.commit()
        db_session.refresh(transaction)

        assert transaction.amount == -35
        assert transaction.transaction_type == TransactionType.consume
        assert transaction.reference_type == ReferenceType.company
        assert transaction.reference_id == "company-123"

    def test_create_token_transaction_adjustment_type(self, db_session, sample_organization):
        """Test creating a token transaction with adjustment type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=50,
            balance_after=150,
            transaction_type=TransactionType.adjustment,
            reference_type=ReferenceType.system,
            created_by="system",
        )
        db_session.add(transaction)
        db_session.commit()
        db_session.refresh(transaction)

        assert transaction.transaction_type == TransactionType.adjustment
        assert transaction.reference_type == ReferenceType.system


class TestTransactionTypeEnum:
    """Test TransactionType enum values."""

    def test_transaction_type_add(self, db_session, sample_organization):
        """Test add transaction type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=100,
            balance_after=200,
            transaction_type=TransactionType.add,
            reference_type=ReferenceType.manual,
            created_by="admin",
        )
        db_session.add(transaction)
        db_session.commit()

        assert transaction.transaction_type == TransactionType.add
        assert transaction.transaction_type.value == "add"

    def test_transaction_type_consume(self, db_session, sample_organization):
        """Test consume transaction type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=-35,
            balance_after=65,
            transaction_type=TransactionType.consume,
            reference_type=ReferenceType.company,
            created_by="user",
        )
        db_session.add(transaction)
        db_session.commit()

        assert transaction.transaction_type == TransactionType.consume
        assert transaction.transaction_type.value == "consume"

    def test_transaction_type_adjustment(self, db_session, sample_organization):
        """Test adjustment transaction type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=0,
            balance_after=100,
            transaction_type=TransactionType.adjustment,
            reference_type=ReferenceType.system,
            created_by="system",
        )
        db_session.add(transaction)
        db_session.commit()

        assert transaction.transaction_type == TransactionType.adjustment
        assert transaction.transaction_type.value == "adjustment"


class TestReferenceTypeEnum:
    """Test ReferenceType enum values."""

    def test_reference_type_company(self, db_session, sample_organization):
        """Test company reference type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=-35,
            balance_after=65,
            transaction_type=TransactionType.consume,
            reference_type=ReferenceType.company,
            reference_id="123",
            created_by="user",
        )
        db_session.add(transaction)
        db_session.commit()

        assert transaction.reference_type == ReferenceType.company
        assert transaction.reference_type.value == "company"

    def test_reference_type_csv_import(self, db_session, sample_organization):
        """Test csv_import reference type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=-350,
            balance_after=50,
            transaction_type=TransactionType.consume,
            reference_type=ReferenceType.csv_import,
            reference_id="import-batch-001",
            created_by="admin",
        )
        db_session.add(transaction)
        db_session.commit()

        assert transaction.reference_type == ReferenceType.csv_import
        assert transaction.reference_type.value == "csv_import"

    def test_reference_type_manual(self, db_session, sample_organization):
        """Test manual reference type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=100,
            balance_after=200,
            transaction_type=TransactionType.add,
            reference_type=ReferenceType.manual,
            created_by="admin",
        )
        db_session.add(transaction)
        db_session.commit()

        assert transaction.reference_type == ReferenceType.manual
        assert transaction.reference_type.value == "manual"

    def test_reference_type_system(self, db_session, sample_organization):
        """Test system reference type."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=500,
            balance_after=500,
            transaction_type=TransactionType.adjustment,
            reference_type=ReferenceType.system,
            created_by="system",
        )
        db_session.add(transaction)
        db_session.commit()

        assert transaction.reference_type == ReferenceType.system
        assert transaction.reference_type.value == "system"


class TestOrganizationModuleWithoutTokenCount:
    """Test that OrganizationModule works correctly without token_count.

    The token_count column has been removed from OrganizationModule.
    Token management is now handled globally in the Organization table.
    """

    def test_organization_module_creation(self, db_session):
        """Test creating an organization module without token_count."""
        module = OrganizationModule(
            organization_id="org-uuid-123",
            module_name=ModuleName.SCREEN,
            enabled=True,
        )
        db_session.add(module)
        db_session.commit()
        db_session.refresh(module)

        assert module.id is not None
        assert module.organization_id == "org-uuid-123"
        assert module.module_name == ModuleName.SCREEN
        assert module.enabled is True
        # token_count no longer exists on the model
        assert not hasattr(module, 'token_count') or getattr(module, 'token_count', None) is None

    def test_module_name_enum_values(self, db_session):
        """Test that only valid module names are accepted (screen, target, explore)."""
        # Test all valid module names
        for module_name in [ModuleName.SCREEN, ModuleName.TARGET, ModuleName.EXPLORE]:
            module = OrganizationModule(
                organization_id=f"org-{module_name.value}",
                module_name=module_name,
                enabled=False,
            )
            db_session.add(module)
            db_session.commit()

            assert module.module_name == module_name

    def test_module_enabled_disabled_toggle(self, db_session):
        """Test that module enabled state can be toggled."""
        module = OrganizationModule(
            organization_id="org-toggle-test",
            module_name=ModuleName.TARGET,
            enabled=False,
        )
        db_session.add(module)
        db_session.commit()

        assert module.enabled is False

        # Toggle enabled state
        module.enabled = True
        db_session.commit()
        db_session.refresh(module)

        assert module.enabled is True


class TestOrganizationTransactionRelationship:
    """Test relationship between Organization and TokenTransaction."""

    def test_organization_can_access_transactions(self, db_session, sample_organization):
        """Test that an organization can access its transactions."""
        # Create transactions
        tx1 = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=100,
            balance_after=200,
            transaction_type=TransactionType.add,
            reference_type=ReferenceType.manual,
            created_by="admin",
        )
        tx2 = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=-35,
            balance_after=165,
            transaction_type=TransactionType.consume,
            reference_type=ReferenceType.company,
            reference_id="company-1",
            created_by="user",
        )
        db_session.add_all([tx1, tx2])
        db_session.commit()
        db_session.refresh(sample_organization)

        # Access transactions from organization
        assert len(sample_organization.transactions) == 2
        amounts = {tx.amount for tx in sample_organization.transactions}
        assert 100 in amounts
        assert -35 in amounts

    def test_transaction_can_access_organization(self, db_session, sample_organization):
        """Test that a transaction can access its organization."""
        transaction = TokenTransaction(
            organization_id=sample_organization.organization_id,
            amount=50,
            balance_after=150,
            transaction_type=TransactionType.add,
            reference_type=ReferenceType.manual,
            created_by="admin",
        )
        db_session.add(transaction)
        db_session.commit()
        db_session.refresh(transaction)

        # Navigate from transaction to organization
        assert transaction.organization is not None
        assert transaction.organization.organization_id == sample_organization.organization_id
        assert transaction.organization.token_balance == 100
