from app.models.company import Company
from app.models.organization import FeatureFlag, OrganizationFeatureFlag


class TestPappersFeatureFlag:
    """Tests for PAPPERS feature flag enum"""

    def test_pappers_enum_exists(self):
        """Test PAPPERS enum value exists in FeatureFlag"""
        assert hasattr(FeatureFlag, 'PAPPERS')


class TestCompanyPappersColumn:
    """Tests for raw_pappers_knowledge column"""

    def test_column_exists_on_model(self):
        """Test raw_pappers_knowledge column exists on Company model"""
        assert hasattr(Company, 'raw_pappers_knowledge')


class TestOrganizationFeatureFlagConfig:
    """Tests for OrganizationFeatureFlag config JSON"""

    def test_config_can_store_api_key(self, db_session):
        """Test OrganizationFeatureFlag config JSON can store api_key"""
        feature_flag = OrganizationFeatureFlag(
            organization_id="test-org-123",
            flag=FeatureFlag.PAPPERS,
            enabled=True,
            config={"api_key": "test-api-key-12345"}
        )
        db_session.add(feature_flag)
        db_session.commit()

        # Retrieve and verify
        saved_flag = db_session.query(OrganizationFeatureFlag).filter_by(
            organization_id="test-org-123",
            flag=FeatureFlag.PAPPERS
        ).first()

        assert saved_flag is not None
        assert saved_flag.config is not None
        assert saved_flag.config.get("api_key") == "test-api-key-12345"

    def test_enable_disable_preserves_api_key(self, db_session):
        """Test feature flag enable/disable with config preserves api_key"""
        # Create with api_key and enabled
        feature_flag = OrganizationFeatureFlag(
            organization_id="test-org-456",
            flag=FeatureFlag.PAPPERS,
            enabled=True,
            config={"api_key": "my-secret-key"}
        )
        db_session.add(feature_flag)
        db_session.commit()

        # Disable the flag
        feature_flag.enabled = False
        db_session.commit()

        # Verify api_key is preserved
        assert feature_flag.enabled is False
        assert feature_flag.config.get("api_key") == "my-secret-key"

        # Re-enable the flag
        feature_flag.enabled = True
        db_session.commit()

        # Verify api_key is still preserved
        assert feature_flag.enabled is True
        assert feature_flag.config.get("api_key") == "my-secret-key"
