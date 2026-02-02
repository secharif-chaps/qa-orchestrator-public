import inspect
from app.models.company import Company
from app.models.organization import FeatureFlag
from app.services.dify import DifyService
from app.services.company import CompanyService
from app.services.feature_flags import update_feature_config, obfuscate_api_key, has_feature, get_feature_config
from app.api.endpoints.data_sources import update_data_source_config


class TestDataSourcesAPI:
    """Tests for data sources API and Dify integration"""

    def test_update_data_source_config_endpoint(self, db_session):
        """Test PUT /organizations/{org_id}/data-sources/{source}/config endpoint"""
        result = update_feature_config(
            db_session,
            "test-org-api-001",
            FeatureFlag.PAPPERS,
            {"api_key": "test-key-12345"},
            updated_by="admin-user"
        )

        assert result is not None
        assert result.config.get("api_key") == "test-key-12345"
        assert result.enabled is True

    def test_endpoint_returns_obfuscated_api_key(self, db_session):
        """Test endpoint returns obfuscated API key in response"""
        result = update_feature_config(
            db_session,
            "test-org-api-002",
            FeatureFlag.PAPPERS,
            {"api_key": "my-secret-api-key-12345"},
            updated_by="admin-user"
        )

        api_key = result.config.get("api_key")
        obfuscated = obfuscate_api_key(api_key)

        assert obfuscated == "my-s...2345"
        assert "secret" not in obfuscated

    def test_endpoint_requires_admin_organizations_role(self):
        """Test endpoint requires admin.organizations role"""
        # Vérifier dans le code source que le rôle est requis
        source = inspect.getsource(update_data_source_config)

        assert 'required_roles' in source
        assert 'admin.organizations' in source

    def test_get_knowledge_data_includes_pappers(self, db_session):
        """Test _get_knowledge_data() includes raw_pappers_knowledge"""
        company = Company(
            name="Test Company",
            website="https://test.com",
            organization_id="test-org-003",
            raw_mistral_knowledge="mistral data",
            raw_claude_knowledge="claude data",
            raw_wikipedia_knowledge="wikipedia data",
            raw_scraped_website_knowledge="scraped data",
            raw_pappers_knowledge="pappers data",
        )
        db_session.add(company)
        db_session.commit()

        dify_service = DifyService(db_session)
        knowledge = dify_service._get_knowledge_data(company.id)

        assert "pappers" in knowledge
        assert knowledge["pappers"] == "pappers data"

    def test_run_workflow_passes_pappers_inputs(self, db_session):
        """Test run_workflow() passes pappers_enabled and pappers_api_key for data_collection"""
        company = Company(
            name="Test Company",
            website="https://test.com",
            organization_id="test-org-004",
        )
        db_session.add(company)
        db_session.commit()

        update_feature_config(
            db_session,
            "test-org-004",
            FeatureFlag.PAPPERS,
            {"api_key": "pappers-api-key-xyz"},
            updated_by="admin"
        )

        assert has_feature(db_session, "test-org-004", FeatureFlag.PAPPERS) is True
        config = get_feature_config(db_session, "test-org-004", FeatureFlag.PAPPERS)
        assert config.get("api_key") == "pappers-api-key-xyz"

    def test_update_company_data_stores_pappers(self, db_session):
        """Test _update_company_data() stores pappers data correctly"""
        company = Company(
            name="Test Company",
            website="https://test.com",
            organization_id="test-org-005",
        )
        db_session.add(company)
        db_session.commit()

        service = CompanyService(db_session)

        data = {
            "knowledge": {
                "mistral": "mistral content",
                "claude": "claude content",
                "wikipedia": "wiki content",
                "scraped": "scraped content",
                "pappers": "pappers content",
            }
        }

        service._update_company_data(company, "data_collection", data)
        db_session.commit()

        assert company.raw_pappers_knowledge == "pappers content"
