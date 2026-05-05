"""Tests for agent system configuration and constants."""

from app.agents.config import (
    AGENT_TIMEOUT_SECONDS,
    ALL_AGENT_TYPES,
    GLOBAL_ANALYSIS_TIMEOUT_SECONDS,
    INPUT_PRICE_PER_MILLION,
    MAX_AGENT_RETRIES,
    MAX_CONCURRENT_API_CALLS,
    OUTPUT_PRICE_PER_MILLION,
    PROMPTS_REGISTRY,
)


class TestAgentConfig:
    """Tests for config.py constants."""

    def test_all_agent_types_count(self):
        assert len(ALL_AGENT_TYPES) == 12

    def test_agent_types_are_expected(self):
        expected = {
            "profile",
            "digital",
            "press",
            "jobs",
            "products",
            "timeline",
            "csr",
            "team",
            "corporate_structure",
            "sanctions",
            "financial",
            "patents",
        }
        assert set(ALL_AGENT_TYPES) == expected

    def test_every_standard_agent_has_prompt(self):
        # financial is excluded because it uses multi-step prompts
        # (financial_classify / financial_synthesize) instead of a single PROMPT.
        standard_agents = [a for a in ALL_AGENT_TYPES if a != "financial"]
        for agent_name in standard_agents:
            assert agent_name in PROMPTS_REGISTRY, f"Missing prompt for {agent_name}"
            assert len(PROMPTS_REGISTRY[agent_name]) > 0, f"Empty prompt for {agent_name}"

    def test_financial_multistep_prompts_in_registry(self):
        assert "financial_classify" in PROMPTS_REGISTRY, "Missing financial_classify prompt"
        assert "financial_synthesize" in PROMPTS_REGISTRY, "Missing financial_synthesize prompt"
        assert len(PROMPTS_REGISTRY["financial_classify"]) > 0
        assert len(PROMPTS_REGISTRY["financial_synthesize"]) > 0

    def test_planner_node_prompt_in_registry(self):
        assert PROMPTS_REGISTRY.get("planner"), "planner node prompt is missing or empty"

    def test_all_prompts_are_strings(self):
        for key, value in PROMPTS_REGISTRY.items():
            assert isinstance(value, str), f"Prompt '{key}' must be str, got {type(value)}"

    def test_registry_contains_expected_keys(self):
        expected = {
            "profile",
            "digital",
            "press",
            "jobs",
            "products",
            "timeline",
            "csr",
            "team",
            "corporate_structure",
            "sanctions",
            "patents",
            "financial_classify",
            "financial_synthesize",
            "planner",
        }
        assert set(PROMPTS_REGISTRY.keys()) == expected

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
