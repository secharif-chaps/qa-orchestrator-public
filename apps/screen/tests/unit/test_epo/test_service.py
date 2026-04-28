"""Tests for EpoService layer (services/epo.py).

Exercises credential resolution, applicant-query sanitization, result
sorting, and the biblio+abstract fan-out that produces the final
enrichment payload.
"""

from __future__ import annotations

from datetime import date
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.infrastructure.epo.exceptions import (
    EpoAuthError,
    EpoNotFoundError,
    EpoQuotaExceededError,
)
from app.infrastructure.epo.schemas import (
    PatentAbstract,
    PatentBiblio,
    PatentSearchEntry,
    PatentSearchResult,
)
from app.services.epo import (
    EpoCredentialsMissingError,
    EpoFeatureNotEnabledError,
    EpoService,
)


@pytest.fixture
def mock_db():
    return MagicMock()


def _entry(doc_id: str, pub: date | None) -> PatentSearchEntry:
    return PatentSearchEntry(doc_id=doc_id, publication_date=pub)


def _biblio(doc_id: str, pub: date, title: str = "Widget") -> PatentBiblio:
    return PatentBiblio(
        doc_id=doc_id,
        title=title,
        applicants=["ACME CORP"],
        inventors=["Doe, Jane"],
        publication_date=pub,
        application_date=date(pub.year - 1, 1, 1),
    )


def _abstract(doc_id: str, text: str = "An improved widget.") -> PatentAbstract:
    return PatentAbstract(doc_id=doc_id, text=text, lang="en")


class TestGetClient:
    """Credential resolution for EpoService._get_client."""

    def test_raises_when_feature_not_enabled(self, mock_db):
        with (
            patch("app.services.epo.has_feature", return_value=False),
            pytest.raises(EpoFeatureNotEnabledError),
        ):
            EpoService._get_client(mock_db, "org-123")

    def test_raises_when_no_config(self, mock_db):
        with (
            patch("app.services.epo.has_feature", return_value=True),
            patch("app.services.epo.get_feature_config", return_value=None),
            pytest.raises(EpoCredentialsMissingError),
        ):
            EpoService._get_client(mock_db, "org-123")

    def test_raises_when_consumer_key_missing(self, mock_db):
        with (
            patch("app.services.epo.has_feature", return_value=True),
            patch(
                "app.services.epo.get_feature_config",
                return_value={"api_secret": "sec"},
            ),
            pytest.raises(EpoCredentialsMissingError),
        ):
            EpoService._get_client(mock_db, "org-123")

    def test_raises_when_consumer_secret_missing(self, mock_db):
        with (
            patch("app.services.epo.has_feature", return_value=True),
            patch(
                "app.services.epo.get_feature_config",
                return_value={"api_key": "ck"},
            ),
            pytest.raises(EpoCredentialsMissingError),
        ):
            EpoService._get_client(mock_db, "org-123")

    def test_returns_client_with_valid_config(self, mock_db):
        with (
            patch("app.services.epo.has_feature", return_value=True),
            patch(
                "app.services.epo.get_feature_config",
                return_value={"api_key": "ck", "api_secret": "cs"},
            ),
        ):
            client = EpoService._get_client(mock_db, "org-123")
            assert client._consumer_key == "ck"
            assert client._consumer_secret == "cs"


class TestBuildApplicantQuery:
    def test_appends_wildcard(self):
        assert EpoService._build_applicant_query("Airbus") == "Airbus*"

    def test_strips_whitespace(self):
        assert EpoService._build_applicant_query("  Airbus  ") == "Airbus*"

    def test_strips_inner_quotes(self):
        """Inner double quotes would break the CQL ``pa="..."`` expression."""
        assert EpoService._build_applicant_query('Air"bus') == "Airbus*"


class TestSortEntries:
    def test_most_recent_first(self):
        e1 = _entry("A", date(2020, 1, 1))
        e2 = _entry("B", date(2025, 6, 1))
        e3 = _entry("C", date(2024, 3, 15))
        sorted_entries = EpoService._sort_entries_by_publication_desc([e1, e2, e3])
        assert [e.doc_id for e in sorted_entries] == ["B", "C", "A"]

    def test_undated_entries_pushed_to_end(self):
        e1 = _entry("A", date(2025, 1, 1))
        e2 = _entry("B", None)
        e3 = _entry("C", date(2024, 1, 1))
        sorted_entries = EpoService._sort_entries_by_publication_desc([e2, e1, e3])
        assert [e.doc_id for e in sorted_entries] == ["A", "C", "B"]


class TestEnrichCompany:
    """Full orchestration of search → sort → biblio+abstract fan-out."""

    @pytest.mark.asyncio
    async def test_happy_path_returns_sorted_patents_with_biblio_and_abstract(self, mock_db):
        mock_client = AsyncMock()
        mock_client.search_patents = AsyncMock(
            return_value=PatentSearchResult(
                total_results=3,
                entries=[
                    _entry("EP1", date(2020, 1, 1)),
                    _entry("EP2", date(2025, 6, 1)),
                    _entry("EP3", date(2024, 3, 15)),
                ],
            )
        )
        mock_client.get_biblio = AsyncMock(
            side_effect=lambda doc_id: _biblio(
                doc_id,
                date(2025, 6, 1) if doc_id == "EP2" else date(2024, 3, 15) if doc_id == "EP3" else date(2020, 1, 1),
            )
        )
        mock_client.get_abstract = AsyncMock(side_effect=lambda doc_id: _abstract(doc_id))

        with patch.object(EpoService, "_get_client", return_value=mock_client):
            data = await EpoService.enrich_company(mock_db, "org-1", "Acme")

        assert data["applicant_query"] == "Acme*"
        assert data["total_results"] == 3
        assert [p["doc_id"] for p in data["patents"]] == ["EP2", "EP3", "EP1"]
        assert all(p["abstract"] == "An improved widget." for p in data["patents"])
        assert data["patents"][0]["publication_date"] == "2025-06-01"

    @pytest.mark.asyncio
    async def test_no_patents_returns_empty_list(self, mock_db):
        mock_client = AsyncMock()
        mock_client.search_patents = AsyncMock(return_value=PatentSearchResult(total_results=0, entries=[]))

        with patch.object(EpoService, "_get_client", return_value=mock_client):
            data = await EpoService.enrich_company(mock_db, "org-1", "Ghost Corp")

        assert data == {"applicant_query": "Ghost Corp*", "total_results": 0, "patents": []}
        mock_client.get_biblio.assert_not_called()
        mock_client.get_abstract.assert_not_called()

    @pytest.mark.asyncio
    async def test_max_patents_caps_selection(self, mock_db):
        mock_client = AsyncMock()
        mock_client.search_patents = AsyncMock(
            return_value=PatentSearchResult(
                total_results=10,
                entries=[_entry(f"EP{i}", date(2025, 1, i + 1)) for i in range(10)],
            )
        )
        mock_client.get_biblio = AsyncMock(side_effect=lambda doc_id: _biblio(doc_id, date(2025, 1, 1)))
        mock_client.get_abstract = AsyncMock(side_effect=lambda doc_id: _abstract(doc_id))

        with patch.object(EpoService, "_get_client", return_value=mock_client):
            data = await EpoService.enrich_company(mock_db, "org-1", "Acme", max_patents=3)

        assert len(data["patents"]) == 3
        assert mock_client.get_biblio.await_count == 3

    @pytest.mark.asyncio
    async def test_biblio_failure_drops_patent(self, mock_db):
        mock_client = AsyncMock()
        mock_client.search_patents = AsyncMock(
            return_value=PatentSearchResult(
                total_results=2,
                entries=[_entry("EP_OK", date(2025, 1, 1)), _entry("EP_MISS", date(2024, 1, 1))],
            )
        )

        async def _get_biblio(doc_id: str):
            if doc_id == "EP_MISS":
                raise EpoNotFoundError()
            return _biblio(doc_id, date(2025, 1, 1))

        mock_client.get_biblio = AsyncMock(side_effect=_get_biblio)
        mock_client.get_abstract = AsyncMock(side_effect=lambda doc_id: _abstract(doc_id))

        with patch.object(EpoService, "_get_client", return_value=mock_client):
            data = await EpoService.enrich_company(mock_db, "org-1", "Acme")

        assert [p["doc_id"] for p in data["patents"]] == ["EP_OK"]

    @pytest.mark.asyncio
    async def test_abstract_failure_keeps_patent_without_abstract(self, mock_db):
        mock_client = AsyncMock()
        mock_client.search_patents = AsyncMock(
            return_value=PatentSearchResult(
                total_results=1,
                entries=[_entry("EP1", date(2025, 1, 1))],
            )
        )
        mock_client.get_biblio = AsyncMock(return_value=_biblio("EP1", date(2025, 1, 1)))
        mock_client.get_abstract = AsyncMock(side_effect=EpoNotFoundError())

        with patch.object(EpoService, "_get_client", return_value=mock_client):
            data = await EpoService.enrich_company(mock_db, "org-1", "Acme")

        assert len(data["patents"]) == 1
        assert data["patents"][0]["doc_id"] == "EP1"
        assert data["patents"][0]["abstract"] is None
        assert data["patents"][0]["abstract_lang"] is None

    @pytest.mark.asyncio
    async def test_auth_error_propagates(self, mock_db):
        mock_client = AsyncMock()
        mock_client.search_patents = AsyncMock(side_effect=EpoAuthError())

        with (
            patch.object(EpoService, "_get_client", return_value=mock_client),
            pytest.raises(EpoAuthError),
        ):
            await EpoService.enrich_company(mock_db, "org-1", "Acme")

    @pytest.mark.asyncio
    async def test_quota_error_propagates(self, mock_db):
        mock_client = AsyncMock()
        mock_client.search_patents = AsyncMock(side_effect=EpoQuotaExceededError())

        with (
            patch.object(EpoService, "_get_client", return_value=mock_client),
            pytest.raises(EpoQuotaExceededError),
        ):
            await EpoService.enrich_company(mock_db, "org-1", "Acme")

    @pytest.mark.asyncio
    async def test_feature_disabled_raises(self, mock_db):
        with (
            patch("app.services.epo.has_feature", return_value=False),
            pytest.raises(EpoFeatureNotEnabledError),
        ):
            await EpoService.enrich_company(mock_db, "org-123", "Acme")
