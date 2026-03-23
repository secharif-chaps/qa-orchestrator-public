"""Tests for state definitions and reducers."""

from app.agents.state import AgentResult, CompanyAnalysisState, _merge_agent_results


class TestMergeAgentResults:
    """Tests for _merge_agent_results append-only reducer."""

    def test_appends_new_results(self):
        existing = [
            AgentResult(
                agent_name="profile",
                status="success",
                data={},
                sources=[],
                error=None,
                input_tokens=0,
                output_tokens=0,
                duration_ms=0,
            )
        ]
        new = [
            AgentResult(
                agent_name="digital",
                status="success",
                data={},
                sources=[],
                error=None,
                input_tokens=0,
                output_tokens=0,
                duration_ms=0,
            )
        ]

        result = _merge_agent_results(existing, new)
        assert len(result) == 2
        assert result[0]["agent_name"] == "profile"
        assert result[1]["agent_name"] == "digital"

    def test_empty_existing(self):
        new = [
            AgentResult(
                agent_name="profile",
                status="success",
                data={},
                sources=[],
                error=None,
                input_tokens=0,
                output_tokens=0,
                duration_ms=0,
            )
        ]
        result = _merge_agent_results([], new)
        assert len(result) == 1

    def test_empty_new(self):
        existing = [
            AgentResult(
                agent_name="profile",
                status="success",
                data={},
                sources=[],
                error=None,
                input_tokens=0,
                output_tokens=0,
                duration_ms=0,
            )
        ]
        result = _merge_agent_results(existing, [])
        assert len(result) == 1

    def test_both_empty(self):
        result = _merge_agent_results([], [])
        assert result == []

    def test_does_not_deduplicate(self):
        """Retried agents accumulate without overwriting."""
        item = AgentResult(
            agent_name="profile",
            status="success",
            data={},
            sources=[],
            error=None,
            input_tokens=0,
            output_tokens=0,
            duration_ms=0,
        )
        result = _merge_agent_results([item], [item])
        assert len(result) == 2


class TestAgentResultTypedDict:
    """Tests for AgentResult TypedDict structure."""

    def test_has_all_required_fields(self):
        result = AgentResult(
            agent_name="test",
            status="success",
            data={"key": "value"},
            sources=["https://example.com"],
            error=None,
            input_tokens=100,
            output_tokens=50,
            duration_ms=1000,
        )

        assert result["agent_name"] == "test"
        assert result["status"] == "success"
        assert result["data"] == {"key": "value"}
        assert result["sources"] == ["https://example.com"]
        assert result["error"] is None
        assert result["input_tokens"] == 100
        assert result["output_tokens"] == 50
        assert result["duration_ms"] == 1000


class TestCompanyAnalysisStateFields:
    """Tests for CompanyAnalysisState TypedDict fields."""

    def test_state_has_all_fields(self):
        state: CompanyAnalysisState = {
            "company_id": 1,
            "company_name": "Test",
            "website": "https://test.com",
            "organization_id": "org-1",
            "owner_id": "user-1",
            "country_code": None,
            "company_brief": None,
            "agents_to_run": [],
            "agent_results": [],
            "quality_issues": [],
            "agents_to_retry": [],
            "total_tokens": 0,
            "total_cost": 0.0,
        }

        assert state["company_id"] == 1
        assert state["total_cost"] == 0.0
        assert state["agent_results"] == []
