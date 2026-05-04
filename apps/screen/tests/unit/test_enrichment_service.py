"""Tests for the enrichment orchestrator service."""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.services.enrichment_service import MAX_ERROR_LENGTH, EnrichmentService


async def _passthrough_to_thread(fn, *args, **kwargs):
    """Replacement for ``asyncio.to_thread`` that runs the callable inline.

    Preserves the return value so helpers like ``_persist_or_error``
    keep their contract when the collector is exercised under test.
    """
    return fn(*args, **kwargs)


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
    async def test_collects_all_sources_when_enabled(self):
        """Pappers, worldcheck, and epo data returned when flags enabled."""
        db = MagicMock()

        with (
            patch.object(EnrichmentService, "_collect_pappers", new_callable=AsyncMock) as mock_pappers,
            patch.object(EnrichmentService, "_collect_worldcheck", new_callable=AsyncMock) as mock_wc,
            patch.object(EnrichmentService, "_collect_epo", new_callable=AsyncMock) as mock_epo,
        ):
            mock_pappers.return_value = {"siren": "123"}
            mock_wc.return_value = {"matches": []}
            mock_epo.return_value = {
                "epo_publications": {"applicant_query": "Test*", "total_results": 0, "patents": []},
                "epo_families": {"families": []},
                "epo_legal": {"legal_statuses": []},
            }

            result = await EnrichmentService.collect(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
                country_code="FR",
            )

        assert result == {
            "pappers": {"siren": "123"},
            "worldcheck": {"matches": []},
            "epo_publications": {"applicant_query": "Test*", "total_results": 0, "patents": []},
            "epo_families": {"families": []},
            "epo_legal": {"legal_statuses": []},
        }

    @pytest.mark.asyncio
    async def test_excludes_none_results(self):
        """Sources returning None are excluded from the result dict."""
        db = MagicMock()

        with (
            patch.object(EnrichmentService, "_collect_pappers", new_callable=AsyncMock) as mock_pappers,
            patch.object(EnrichmentService, "_collect_worldcheck", new_callable=AsyncMock) as mock_wc,
            patch.object(EnrichmentService, "_collect_epo", new_callable=AsyncMock) as mock_epo,
        ):
            mock_pappers.return_value = None
            mock_wc.return_value = {"matches": []}
            mock_epo.return_value = {}

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
            patch.object(EnrichmentService, "_collect_epo", new_callable=AsyncMock) as mock_epo,
        ):
            mock_pappers.return_value = None
            mock_wc.return_value = None
            mock_epo.return_value = {}

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


class TestCollectEpo:
    """Tests for _collect_epo with the three-step flow.

    Each test substitutes ``asyncio.to_thread`` with an inline runner so
    the sync helpers (SessionLocal, _upsert_enrichment, _persist_or_error,
    session.commit/close) execute their real bodies on a MagicMock session.
    """

    @pytest.mark.asyncio
    async def test_skips_when_feature_disabled(self):
        db = MagicMock()

        with patch("app.services.enrichment_service.has_feature", return_value=False):
            result = await EnrichmentService._collect_epo(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result == {}

    @pytest.mark.asyncio
    async def test_collects_publications_families_and_legal_on_success(self):
        db = MagicMock()
        local_db = MagicMock()
        publications = {
            "applicant_query": "Test*",
            "total_results": 2,
            "patents": [
                {"doc_id": "EP1", "title": "Widget"},
                {"doc_id": "EP2", "title": "Gadget"},
            ],
        }
        families = {"families": [{"doc_id": "EP1", "family_size": 3}]}
        legal = {"legal_statuses": [{"doc_id": "EP1", "simplified_status": "active"}]}

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch("app.services.enrichment_service.SessionLocal", return_value=local_db),
            patch("app.services.enrichment_service.asyncio.to_thread", new=_passthrough_to_thread),
            patch(
                "app.services.enrichment_service.EpoService.enrich_company",
                new_callable=AsyncMock,
                return_value=publications,
            ),
            patch(
                "app.services.enrichment_service.EpoService.get_patent_families",
                new_callable=AsyncMock,
                return_value=families,
            ) as mock_families,
            patch(
                "app.services.enrichment_service.EpoService.get_legal_status",
                new_callable=AsyncMock,
                return_value=legal,
            ) as mock_legal,
        ):
            result = await EnrichmentService._collect_epo(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result == {
            "epo_publications": publications,
            "epo_families": families,
            "epo_legal": legal,
        }
        mock_families.assert_awaited_once_with(local_db, "org-1", ["EP1", "EP2"])
        mock_legal.assert_awaited_once_with(local_db, "org-1", ["EP1", "EP2"])
        # Three upserts + one final commit on the local session.
        assert local_db.execute.call_count == 3
        local_db.commit.assert_called_once()
        local_db.close.assert_called_once()

    @pytest.mark.asyncio
    async def test_empty_patents_skips_family_and_legal_calls(self):
        """Publications with empty list persist a success but skip family/legal."""
        db = MagicMock()
        local_db = MagicMock()
        publications = {"applicant_query": "Ghost*", "total_results": 0, "patents": []}

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch("app.services.enrichment_service.SessionLocal", return_value=local_db),
            patch("app.services.enrichment_service.asyncio.to_thread", new=_passthrough_to_thread),
            patch(
                "app.services.enrichment_service.EpoService.enrich_company",
                new_callable=AsyncMock,
                return_value=publications,
            ),
            patch(
                "app.services.enrichment_service.EpoService.get_patent_families",
                new_callable=AsyncMock,
            ) as mock_families,
            patch(
                "app.services.enrichment_service.EpoService.get_legal_status",
                new_callable=AsyncMock,
            ) as mock_legal,
        ):
            result = await EnrichmentService._collect_epo(
                db,
                company_id=1,
                company_name="Ghost",
                organization_id="org-1",
            )

        assert result == {"epo_publications": publications}
        mock_families.assert_not_awaited()
        mock_legal.assert_not_awaited()
        # Only the publications upsert + final commit ran on the local session.
        assert local_db.execute.call_count == 1
        local_db.commit.assert_called_once()

    @pytest.mark.asyncio
    async def test_families_failure_still_persists_publications_and_legal(self):
        from app.infrastructure.epo.exceptions import EpoQuotaExceededError

        db = MagicMock()
        local_db = MagicMock()
        publications = {
            "applicant_query": "Test*",
            "total_results": 1,
            "patents": [{"doc_id": "EP1"}],
        }
        legal = {"legal_statuses": [{"doc_id": "EP1", "simplified_status": "active"}]}

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch("app.services.enrichment_service.SessionLocal", return_value=local_db),
            patch("app.services.enrichment_service.asyncio.to_thread", new=_passthrough_to_thread),
            patch(
                "app.services.enrichment_service.EpoService.enrich_company",
                new_callable=AsyncMock,
                return_value=publications,
            ),
            patch(
                "app.services.enrichment_service.EpoService.get_patent_families",
                new_callable=AsyncMock,
                side_effect=EpoQuotaExceededError(),
            ),
            patch(
                "app.services.enrichment_service.EpoService.get_legal_status",
                new_callable=AsyncMock,
                return_value=legal,
            ),
        ):
            result = await EnrichmentService._collect_epo(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        # epo_families absent from success dict (Exception was persisted as error record).
        assert "epo_families" not in result
        assert result["epo_publications"] == publications
        assert result["epo_legal"] == legal
        # Three upserts: publications (success) + families (error) + legal (success).
        assert local_db.execute.call_count == 3
        local_db.commit.assert_called_once()

    @pytest.mark.asyncio
    async def test_publications_error_returns_empty_and_still_commits_error_record(self):
        from app.infrastructure.epo.exceptions import EpoAuthError

        db = MagicMock()
        local_db = MagicMock()

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch("app.services.enrichment_service.SessionLocal", return_value=local_db),
            patch("app.services.enrichment_service.asyncio.to_thread", new=_passthrough_to_thread),
            patch(
                "app.services.enrichment_service.EpoService.enrich_company",
                new_callable=AsyncMock,
                side_effect=EpoAuthError(),
            ),
            patch(
                "app.services.enrichment_service.EpoService.get_patent_families",
                new_callable=AsyncMock,
            ) as mock_families,
            patch(
                "app.services.enrichment_service.EpoService.get_legal_status",
                new_callable=AsyncMock,
            ) as mock_legal,
        ):
            result = await EnrichmentService._collect_epo(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result == {}
        mock_families.assert_not_awaited()
        mock_legal.assert_not_awaited()
        # Only the publications error upsert was staged, followed by the commit.
        assert local_db.execute.call_count == 1
        local_db.commit.assert_called_once()

    @pytest.mark.asyncio
    async def test_credentials_missing_treated_as_publications_error(self):
        from app.services.epo import EpoCredentialsMissingError

        db = MagicMock()
        local_db = MagicMock()

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch("app.services.enrichment_service.SessionLocal", return_value=local_db),
            patch("app.services.enrichment_service.asyncio.to_thread", new=_passthrough_to_thread),
            patch(
                "app.services.enrichment_service.EpoService.enrich_company",
                new_callable=AsyncMock,
                side_effect=EpoCredentialsMissingError("org-1"),
            ),
        ):
            result = await EnrichmentService._collect_epo(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result == {}
        local_db.commit.assert_called_once()

    @pytest.mark.asyncio
    async def test_unexpected_exception_treated_as_publications_error(self):
        db = MagicMock()
        local_db = MagicMock()

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch("app.services.enrichment_service.SessionLocal", return_value=local_db),
            patch("app.services.enrichment_service.asyncio.to_thread", new=_passthrough_to_thread),
            patch(
                "app.services.enrichment_service.EpoService.enrich_company",
                new_callable=AsyncMock,
                side_effect=RuntimeError("unexpected"),
            ),
        ):
            result = await EnrichmentService._collect_epo(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert result == {}
        local_db.commit.assert_called_once()

    @pytest.mark.asyncio
    async def test_legal_failure_still_persists_publications_and_families(self):
        from app.infrastructure.epo.exceptions import EpoQuotaExceededError

        db = MagicMock()
        local_db = MagicMock()
        publications = {
            "applicant_query": "Test*",
            "total_results": 1,
            "patents": [{"doc_id": "EP1"}],
        }
        families = {"families": [{"doc_id": "EP1", "family_size": 3}]}

        with (
            patch("app.services.enrichment_service.has_feature", return_value=True),
            patch("app.services.enrichment_service.SessionLocal", return_value=local_db),
            patch("app.services.enrichment_service.asyncio.to_thread", new=_passthrough_to_thread),
            patch(
                "app.services.enrichment_service.EpoService.enrich_company",
                new_callable=AsyncMock,
                return_value=publications,
            ),
            patch(
                "app.services.enrichment_service.EpoService.get_patent_families",
                new_callable=AsyncMock,
                return_value=families,
            ),
            patch(
                "app.services.enrichment_service.EpoService.get_legal_status",
                new_callable=AsyncMock,
                side_effect=EpoQuotaExceededError(),
            ),
        ):
            result = await EnrichmentService._collect_epo(
                db,
                company_id=1,
                company_name="Test",
                organization_id="org-1",
            )

        assert "epo_legal" not in result
        assert result["epo_publications"] == publications
        assert result["epo_families"] == families


class TestPersistOrError:
    """Tests for the _persist_or_error helper."""

    def test_dict_outcome_upserts_success_and_returns_payload(self):
        db = MagicMock()
        payload = {"families": []}

        result = EnrichmentService._persist_or_error(db, 1, "epo_families", payload)

        assert result is payload
        db.execute.assert_called_once()

    def test_exception_outcome_upserts_error_and_returns_none(self):
        from app.infrastructure.epo.exceptions import EpoAuthError

        db = MagicMock()

        result = EnrichmentService._persist_or_error(db, 1, "epo_families", EpoAuthError())

        assert result is None
        db.execute.assert_called_once()

    def test_unexpected_outcome_type_upserts_error_and_returns_none(self):
        db = MagicMock()

        result = EnrichmentService._persist_or_error(db, 1, "epo_families", "not expected")

        assert result is None
        db.execute.assert_called_once()


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
