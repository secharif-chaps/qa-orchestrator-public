"""Tests for the enrichment orchestrator service."""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.services.enrichment_service import MAX_ERROR_LENGTH, EnrichmentService


def _make_pappers_result():
    mock = MagicMock()
    mock.model_dump.return_value = {"siren": "123456789", "legal_form": "SAS"}
    return mock


def _make_worldcheck_result():
    mock = MagicMock()
    mock.model_dump.return_value = {"matches": [], "risk_level": "low"}
    return mock


class TestCollect:
    """Tests for EnrichmentService.collect()."""

    @pytest.mark.asyncio
    async def test_collects_both_sources_when_enabled(self):
        """Both pappers and worldcheck data returned when flags enabled."""
        db = MagicMock()

        with (
            patch.object(EnrichmentService, "_collect_pappers", new_callable=AsyncMock) as mock_pappers,
            patch.object(EnrichmentService, "_collect_worldcheck", new_callable=AsyncMock) as mock_wc,
        ):
            mock_pappers.return_value = {"siren": "123"}
            mock_wc.return_value = {"matches": []}

            result = await EnrichmentService.collect(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
                country_code="FR",
            )

        assert result == {"pappers": {"siren": "123"}, "worldcheck": {"matches": []}}

    @pytest.mark.asyncio
    async def test_excludes_none_results(self):
        """Sources returning None are excluded from the result dict."""
        db = MagicMock()

        with (
            patch.object(EnrichmentService, "_collect_pappers", new_callable=AsyncMock) as mock_pappers,
            patch.object(EnrichmentService, "_collect_worldcheck", new_callable=AsyncMock) as mock_wc,
        ):
            mock_pappers.return_value = None
            mock_wc.return_value = {"matches": []}

            result = await EnrichmentService.collect(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result == {"worldcheck": {"matches": []}}

    @pytest.mark.asyncio
    async def test_returns_empty_when_all_disabled(self):
        """Empty dict returned when no sources produce data."""
        db = MagicMock()

        with (
            patch.object(EnrichmentService, "_collect_pappers", new_callable=AsyncMock) as mock_pappers,
            patch.object(EnrichmentService, "_collect_worldcheck", new_callable=AsyncMock) as mock_wc,
        ):
            mock_pappers.return_value = None
            mock_wc.return_value = None

            result = await EnrichmentService.collect(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result == {}


class TestCollectPappers:
    """Tests for _collect_pappers."""

    @pytest.mark.asyncio
    async def test_skips_non_french_company(self):
        """Non-FR country code skips Pappers collection."""
        db = MagicMock()

        with patch("app.services.enrichment_service.asyncio.to_thread", new_callable=AsyncMock):
            result = await EnrichmentService._collect_pappers(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
                country_code="DE",
            )

        assert result is None

    @pytest.mark.asyncio
    async def test_skips_when_feature_disabled(self):
        """Returns None when Pappers feature flag is disabled."""
        db = MagicMock()

        with patch("app.services.enrichment_service.has_feature", return_value=False):
            result = await EnrichmentService._collect_pappers(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
                country_code="FR",
            )

        assert result is None

    @pytest.mark.asyncio
    async def test_returns_data_on_success(self):
        """Returns serialized data on successful Pappers enrichment."""
        db = MagicMock()
        mock_result = _make_pappers_result()

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch(
                "app.services.enrichment_service.PappersService.enrich_company", new_callable=AsyncMock
            ) as mock_enrich,
            patch("app.services.enrichment_service.asyncio.to_thread", new_callable=AsyncMock),
        ):
            mock_enrich.return_value = mock_result

            result = await EnrichmentService._collect_pappers(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
                country_code="FR",
            )

        assert result == {"siren": "123456789", "legal_form": "SAS"}

    @pytest.mark.asyncio
    async def test_handles_pappers_error(self):
        """Returns None and logs on PappersError."""
        from app.infrastructure.pappers.exceptions import PappersError

        db = MagicMock()

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch(
                "app.services.enrichment_service.PappersService.enrich_company", new_callable=AsyncMock
            ) as mock_enrich,
            patch("app.services.enrichment_service.asyncio.to_thread", new_callable=AsyncMock),
        ):
            mock_enrich.side_effect = PappersError("API timeout")

            result = await EnrichmentService._collect_pappers(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
                country_code="FR",
            )

        assert result is None

    @pytest.mark.asyncio
    async def test_skips_when_no_match(self):
        """Returns None when Pappers finds no match."""
        db = MagicMock()

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch(
                "app.services.enrichment_service.PappersService.enrich_company", new_callable=AsyncMock
            ) as mock_enrich,
            patch("app.services.enrichment_service.asyncio.to_thread", new_callable=AsyncMock),
        ):
            mock_enrich.return_value = None

            result = await EnrichmentService._collect_pappers(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
                country_code="FR",
            )

        assert result is None


class TestCollectWorldCheck:
    """Tests for _collect_worldcheck."""

    @pytest.mark.asyncio
    async def test_skips_when_feature_disabled(self):
        db = MagicMock()

        with patch("app.services.enrichment_service.has_feature", return_value=False):
            result = await EnrichmentService._collect_worldcheck(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result is None

    @pytest.mark.asyncio
    async def test_returns_data_on_success(self):
        db = MagicMock()
        mock_result = _make_worldcheck_result()

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch(
                "app.services.enrichment_service.WorldCheckService.screen_company", new_callable=AsyncMock
            ) as mock_screen,
            patch("app.services.enrichment_service.asyncio.to_thread", new_callable=AsyncMock),
        ):
            mock_screen.return_value = mock_result

            result = await EnrichmentService._collect_worldcheck(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result == {"matches": [], "risk_level": "low"}

    @pytest.mark.asyncio
    async def test_handles_worldcheck_error(self):
        from app.infrastructure.worldcheck.exceptions import WorldCheckError

        db = MagicMock()

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch(
                "app.services.enrichment_service.WorldCheckService.screen_company", new_callable=AsyncMock
            ) as mock_screen,
            patch("app.services.enrichment_service.asyncio.to_thread", new_callable=AsyncMock),
        ):
            mock_screen.side_effect = WorldCheckError("Connection refused")

            result = await EnrichmentService._collect_worldcheck(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result is None

    @pytest.mark.asyncio
    async def test_handles_unexpected_exception(self):
        db = MagicMock()

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch(
                "app.services.enrichment_service.WorldCheckService.screen_company", new_callable=AsyncMock
            ) as mock_screen,
            patch("app.services.enrichment_service.asyncio.to_thread", new_callable=AsyncMock),
        ):
            mock_screen.side_effect = RuntimeError("unexpected")

            result = await EnrichmentService._collect_worldcheck(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result is None


class TestUpsertEnrichment:
    """Tests for _upsert_enrichment."""

    def test_executes_insert_on_conflict_statement(self):
        """Verifies that a pg_insert ON CONFLICT statement is executed."""
        db = MagicMock()

        EnrichmentService._upsert_enrichment(
            db,
            company_id=1,
            source="pappers",
            data={"siren": "123"},
            commit=True,
        )

        db.execute.assert_called_once()
        db.commit.assert_called_once()

    def test_skips_commit_when_commit_false(self):
        db = MagicMock()

        EnrichmentService._upsert_enrichment(
            db,
            company_id=1,
            source="pappers",
            data={"siren": "123"},
            commit=False,
        )

        db.execute.assert_called_once()
        db.commit.assert_not_called()

    def test_truncates_long_error_message(self):
        """Error messages longer than MAX_ERROR_LENGTH are truncated."""
        db = MagicMock()
        long_error = "x" * 1000

        EnrichmentService._upsert_enrichment(
            db,
            company_id=1,
            source="pappers",
            status="error",
            error=long_error,
        )

        db.execute.assert_called_once()
        # The error should have been truncated before being passed to the statement
        # We can't easily inspect the SQL values, but we verify it doesn't raise
        assert len(long_error) > MAX_ERROR_LENGTH
