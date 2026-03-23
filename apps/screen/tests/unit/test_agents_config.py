"""Tests for agent system configuration and constants."""

from app.agents.config import (
    AGENT_PROMPTS,
    AGENT_TIMEOUT_SECONDS,
    ALL_AGENT_TYPES,
    GLOBAL_ANALYSIS_TIMEOUT_SECONDS,
    INPUT_PRICE_PER_MILLION,
    MAX_AGENT_RETRIES,
    MAX_CONCURRENT_API_CALLS,
    OUTPUT_PRICE_PER_MILLION,
)


class TestAgentConfig:
    """Tests for config.py constants."""

    def test_all_agent_types_has_8_agents(self):
        assert len(ALL_AGENT_TYPES) == 8

    def test_every_agent_has_prompt(self):
        for agent_name in ALL_AGENT_TYPES:
            assert agent_name in AGENT_PROMPTS, f"Missing prompt for {agent_name}"
            assert len(AGENT_PROMPTS[agent_name]) > 0, f"Empty prompt for {agent_name}"

    def test_no_extra_prompts_beyond_agent_types(self):
        assert set(AGENT_PROMPTS.keys()) == set(ALL_AGENT_TYPES)

    def test_timeout_is_positive(self):
        assert AGENT_TIMEOUT_SECONDS > 0

    def test_global_timeout_greater_than_agent_timeout(self):
        assert GLOBAL_ANALYSIS_TIMEOUT_SECONDS > AGENT_TIMEOUT_SECONDS

    def test_pricing_constants_positive(self):
        assert INPUT_PRICE_PER_MILLION > 0
        assert OUTPUT_PRICE_PER_MILLION > 0

    def test_max_concurrent_calls_reasonable(self):
        assert 1 <= MAX_CONCURRENT_API_CALLS <= 20

    def test_max_retries_reasonable(self):
        assert 0 <= MAX_AGENT_RETRIES <= 5

    def test_agent_types_are_expected(self):
        expected = {"profile", "digital", "press", "jobs", "products", "timeline", "csr", "team"}
        assert set(ALL_AGENT_TYPES) == expected
