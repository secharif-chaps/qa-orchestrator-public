import pytest

from app.core.encryption import decrypt, encrypt
from app.models.organization import FeatureFlag, OrganizationFeatureFlag
from app.services.feature_flags import (
    enable_feature,
    get_feature_config,
    update_feature_config,
)

pytestmark = pytest.mark.integration


class TestFeatureFlagsService:
    """Tests for feature flags service extensions"""

    def test_update_feature_config_without_changing_enabled_state(self, db_session):
        """Test update_feature_config() updates config without changing enabled state"""
        # Create a feature flag that is already enabled
        feature = OrganizationFeatureFlag(
            organization_id="test-org-001",
            flag=FeatureFlag.PAPPERS,
            enabled=True,
            config={"api_key": "existing-key-12345"},
        )
        db_session.add(feature)
        db_session.commit()

        # Update config with a new api_key (should stay enabled)
        updated = update_feature_config(
            db_session, "test-org-001", FeatureFlag.PAPPERS, {"api_key": "new-key-67890"}, updated_by="admin-user"
        )

        assert updated.enabled is True
        encrypted_key = updated.config.get("api_key")
        decrypted_key = decrypt(encrypted_key)
        assert decrypted_key == "new-key-67890"

    def test_update_feature_config_creates_record_if_not_exists(self, db_session):
        """Test update_feature_config() creates record if not exists"""
        updated = update_feature_config(
            db_session, "test-org-002", FeatureFlag.PAPPERS, {"api_key": "brand-new-key"}, updated_by="admin-user"
        )

        assert updated is not None
        assert updated.organization_id == "test-org-002"
        assert updated.flag == FeatureFlag.PAPPERS
        encrypted_key = updated.config.get("api_key")
        decrypted_key = decrypt(encrypted_key)
        assert decrypted_key == "brand-new-key"
        assert updated.enabled is True

    def test_enable_feature_with_config_sets_api_key(self, db_session):
        """Test enable_feature() with config parameter sets api_key"""
        feature = enable_feature(
            db_session,
            "test-org-003",
            FeatureFlag.PAPPERS,
            enabled_by="admin-user",
            config={"api_key": "my-api-key-abc"},
        )

        assert feature.enabled is True
        assert feature.config is not None
        encrypted_key = feature.config.get("api_key")
        decrypted_key = decrypt(encrypted_key)
        assert decrypted_key == "my-api-key-abc"

    def test_get_feature_config_retrieves_api_key(self, db_session):
        """Test get_feature_config() retrieves api_key from config"""
        feature = OrganizationFeatureFlag(
            organization_id="test-org-004",
            flag=FeatureFlag.PAPPERS,
            enabled=True,
            config={"api_key": encrypt("secret-key-xyz")},
        )
        db_session.add(feature)
        db_session.commit()

        config = get_feature_config(db_session, "test-org-004", FeatureFlag.PAPPERS)

        assert config is not None
        assert config.get("api_key") == "secret-key-xyz"

    def test_auto_enable_when_api_key_set(self, db_session):
        """Test auto-enable when api_key is set via update_feature_config()"""
        feature = OrganizationFeatureFlag(
            organization_id="test-org-005", flag=FeatureFlag.PAPPERS, enabled=False, config={}
        )
        db_session.add(feature)
        db_session.commit()

        assert feature.enabled is False

        updated = update_feature_config(
            db_session, "test-org-005", FeatureFlag.PAPPERS, {"api_key": "new-api-key"}, updated_by="admin-user"
        )

        assert updated.enabled is True
        encrypted_key = updated.config.get("api_key")
        decrypted_key = decrypt(encrypted_key)
        assert decrypted_key == "new-api-key"
