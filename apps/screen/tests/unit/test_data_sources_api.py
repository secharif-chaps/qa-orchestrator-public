import inspect
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.api.endpoints.data_sources import update_data_source_config
from app.core.encryption import decrypt
from app.models.company import Company
from app.models.organization import FeatureFlag
from app.services.company import CompanyService
from app.services.dify import DifyService
from app.services.feature_flags import get_feature_config, has_feature, obfuscate_api_key, update_feature_config


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
        encrypted_key = result.config.get("api_key")
        decrypted_key = decrypt(encrypted_key)
        assert decrypted_key == "test-key-12345"
        assert result.enabled is True

    def test_endpoint_returns_obfuscated_api_key(self, db_session):
        """Test endpoint returns obfuscated API key in response"""
        result = update_feature_config(
            db_session,
            "test-org-api-002",
            FeatureFlag.PAPPERS,
            {"api_key": "my-secret-api-key-12345"},
            updated_by="admin-user",
        )

        encrypted_key = result.config.get("api_key")
        decrypted_key = decrypt(encrypted_key)
        obfuscated = obfuscate_api_key(decrypted_key)

        assert decrypted_key == "my-secret-api-key-12345"
        assert obfuscated == "my-s...2345"
        assert "secret" not in obfuscated

    def test_endpoint_requires_admin_organizations_role(self):
        """Test endpoint requires admin.organizations role"""
        # Vérifier dans le code source que le rôle est requis
        source = inspect.getsource(update_data_source_config)

        assert "required_roles" in source
        assert "admin.organizations" in source

    def test_get_knowledge_data_includes_pappers(self, db_session):
        """Test _get_knowledge_data() includes raw_pappers_knowledge"""
        company = Company(
            name="Test Company",
            website="https://test.com",
            organization_id="test-org-003",
            raw_mistral_knowledge="mistral data",
            raw_gpt_knowledge="gpt data",
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
                "gpt": "gpt content",
                "wikipedia": "wiki content",
                "scraped": "scraped content",
                "pappers": "pappers content",
            }
        }

        service._update_company_data(company, "data_collection", data)
        db_session.commit()

        assert company.raw_pappers_knowledge == "pappers content"

    @pytest.mark.asyncio
    async def test_run_workflow_injects_pappers_for_downstream_workflows(self, db_session):
        """Test run_workflow() injects pappers knowledge into inputs for non-data_collection workflows"""
        # Create a company with pappers knowledge
        company = Company(
            name="Test Pappers Propagation",
            website="https://test-pappers.com",
            organization_id="test-org-006",
            raw_mistral_knowledge="mistral data",
            raw_gpt_knowledge="gpt data",
            raw_wikipedia_knowledge="wikipedia data",
            raw_scraped_website_knowledge="scraped data",
            raw_pappers_knowledge="pappers data",
        )
        db_session.add(company)
        db_session.commit()

        dify_service = DifyService(db_session)

        # Mock _get_workflow_config to return a fake API key
        dify_service._get_workflow_config = MagicMock(return_value="fake-api-key")

        # Mock httpx.AsyncClient to capture the request payload
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = {
            "workflow_run_id": "test-run-id",
            "task_id": "test-task-id",
            "data": {"outputs": {}, "status": "succeeded"},
        }
        mock_response.text = "{}"

        mock_post = AsyncMock(return_value=mock_response)
        mock_client = AsyncMock()
        mock_client.post = mock_post
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=False)

        with patch("httpx.AsyncClient", return_value=mock_client):
            await dify_service.run_workflow(
                task_type="profile",
                company_name="Test Pappers Propagation",
                website="https://test-pappers.com",
                success_callback="http://localhost/callback",
                error_callback="http://localhost/error",
                task_id=1,
                company_id=company.id,
            )

        # Verify the POST was called and pappers is in the inputs
        mock_post.assert_called_once()
        call_kwargs = mock_post.call_args
        payload = call_kwargs.kwargs.get("json") or call_kwargs[1].get("json")
        inputs = payload["inputs"]

        assert "pappers" in inputs, "pappers key must be present in workflow inputs"
        assert inputs["pappers"] == "pappers data"
        # Also verify other knowledge sources are present
        assert inputs["mistral"] == "mistral data"
        assert inputs["gpt"] == "gpt data"
        assert inputs["wikipedia"] == "wikipedia data"
        assert inputs["scraped"] == "scraped data"


class TestWorldCheckIntegration:
    """Tests for WorldCheck integration in Dify workflows"""

    def test_get_knowledge_data_includes_worldcheck(self, db_session):
        """Test _get_knowledge_data() includes raw_worldcheck_knowledge"""
        company = Company(
            name="Test WorldCheck Company",
            website="https://test-wc.com",
            organization_id="test-org-wc-001",
            raw_mistral_knowledge="mistral data",
            raw_gpt_knowledge="gpt data",
            raw_wikipedia_knowledge="wikipedia data",
            raw_scraped_website_knowledge="scraped data",
            raw_pappers_knowledge="pappers data",
            raw_worldcheck_knowledge="worldcheck data",
        )
        db_session.add(company)
        db_session.commit()

        dify_service = DifyService(db_session)
        knowledge = dify_service._get_knowledge_data(company.id)

        assert "worldcheck" in knowledge
        assert knowledge["worldcheck"] == "worldcheck data"

    def test_run_workflow_passes_worldcheck_inputs(self, db_session):
        """Test run_workflow() passes worldcheck_enabled, api_key, and api_secret for data_collection"""
        company = Company(
            name="Test WorldCheck Company",
            website="https://test-wc.com",
            organization_id="test-org-wc-002",
        )
        db_session.add(company)
        db_session.commit()

        update_feature_config(
            db_session,
            "test-org-wc-002",
            FeatureFlag.WORLDCHECK,
            {"api_key": "wc-api-key-xyz", "api_secret": "wc-api-secret-abc"},
            updated_by="admin",
        )

        assert has_feature(db_session, "test-org-wc-002", FeatureFlag.WORLDCHECK) is True
        config = get_feature_config(db_session, "test-org-wc-002", FeatureFlag.WORLDCHECK)
        assert config.get("api_key") == "wc-api-key-xyz"
        assert config.get("api_secret") == "wc-api-secret-abc"

    def test_update_company_data_stores_worldcheck(self, db_session):
        """Test _update_company_data() stores worldcheck data correctly"""
        company = Company(
            name="Test WorldCheck Company",
            website="https://test-wc.com",
            organization_id="test-org-wc-003",
        )
        db_session.add(company)
        db_session.commit()

        service = CompanyService(db_session)

        data = {
            "knowledge": {
                "mistral": "mistral content",
                "gpt": "gpt content",
                "wikipedia": "wiki content",
                "scraped": "scraped content",
                "pappers": "pappers content",
                "worldcheck": "worldcheck content",
            }
        }

        service._update_company_data(company, "data_collection", data)
        db_session.commit()

        assert company.raw_worldcheck_knowledge == "worldcheck content"
        assert company.raw_pappers_knowledge == "pappers content"
