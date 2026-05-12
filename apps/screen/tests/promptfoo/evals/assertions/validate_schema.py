"""Shared structural assertion — validates LLM output against the agent's Pydantic schema.

For agents with production Pydantic schemas (profile, financial, etc.), imports them
directly from app.agents.schemas so validation stays in sync with structured output.

For agents without production schemas (financial_classify, planner, sanctions), defines
eval-only models that mirror the constraints in the agent prompts.

Usage in a config's defaultTest:
    defaultTest:
      vars:
        _agent_name: "financial_classify"
      assert:
        - type: python
          metric: structural
          weight: 10
          value: file://assertions/validate_schema.py
"""

import json
import sys
from pathlib import Path
from typing import Literal

from pydantic import BaseModel, ValidationError, field_validator

sys.path.insert(0, str(Path(__file__).parent.parent.parent.parent.parent))

from app.agents.schemas import AGENT_OUTPUT_SCHEMAS

# ---------------------------------------------------------------------------
# Eval-only schemas for agents without production Pydantic models
# ---------------------------------------------------------------------------


class FinancialClassifyOutput(BaseModel):
    companyType: Literal["public", "private"]
    isUSListed: bool
    tickerSymbol: str | None = None
    stockExchange: str | None = None
    country: str | None = None


class PlannerOutput(BaseModel):
    summary: str
    industry: str | None = None
    country: str | None = None
    notes: str | None = None
    discovered_pages: dict | None = None
    key_people: list | None = None
    brands: list | None = None
    subsidiaries: list | None = None

    @field_validator("summary")
    @classmethod
    def summary_not_empty(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("'summary' must be a non-empty string")
        return v


class SanctionsOutput(BaseModel):
    overall_risk_level: Literal["low", "medium", "high", "critical"]
    overall_risk_justification: str
    insights: str
    items: list


class _SourcedValue(BaseModel):
    value: str
    source: str


class _CorporateEntity(BaseModel):
    name: _SourcedValue
    type: Literal["subsidiary", "affiliate", "branch", "regional_entity", "parent_company"]
    country: _SourcedValue


class CorporateStructureOutput(BaseModel):
    entities: list[_CorporateEntity]


# ---------------------------------------------------------------------------
# Combined registry: eval-only + production schemas
# ---------------------------------------------------------------------------

_SCHEMAS: dict[str, type[BaseModel]] = {
    "financial_classify": FinancialClassifyOutput,
    "planner": PlannerOutput,
    "sanctions": SanctionsOutput,
    "corporate_structure": CorporateStructureOutput,
    **AGENT_OUTPUT_SCHEMAS,
}


def get_assert(output: str, context: dict) -> dict | bool:
    agent_name = context.get("vars", {}).get("_agent_name")
    schema_cls = _SCHEMAS.get(agent_name)

    if not schema_cls:
        return {
            "pass": False,
            "score": 0,
            "reason": f"No schema registered for agent: '{agent_name}'. Available: {sorted(_SCHEMAS)}",
        }

    try:
        data = json.loads(output)
        schema_cls.model_validate(data)
        return True
    except json.JSONDecodeError as e:
        return {"pass": False, "score": 0, "reason": f"Invalid JSON: {e}"}
    except ValidationError as e:
        return {"pass": False, "score": 0, "reason": str(e)}
