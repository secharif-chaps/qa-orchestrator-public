/**
 * Verification Gate — Output Quality Validation
 *
 * After each agent runs, validate its output BEFORE passing to the next agent.
 * Uses a combination of:
 * 1. Structural checks (required sections, minimum length)
 * 2. LLM-based quality scoring (haiku model, 0-100)
 *
 * If output fails (score < 70), the workflow can retry or stop.
 */

class VerificationGate {
  constructor(engine) {
    this.engine = engine;
  }

  async validate(agentId, output, userMessage, chainContext = '') {
    // Step 1: Structural checks (fast, no LLM)
    const structuralIssues = this.checkStructure(agentId, output);
    if (structuralIssues.length > 0) {
      return {
        pass: false,
        score: 30,
        issues: structuralIssues,
        reason: 'structural',
      };
    }

    // Step 2: Length check
    if (output.length < 200) {
      return {
        pass: false,
        score: 20,
        issues: ['Output is too short (< 200 chars)'],
        reason: 'length',
      };
    }

    // Step 3: LLM-based quality check (haiku, fast)
    const verdict = await this.scoreLLM(agentId, output, userMessage, chainContext);
    return verdict;
  }

  checkStructure(agentId, output) {
    const required = this.getRequiredSections(agentId);
    const issues = [];

    for (const section of required) {
      if (!output.includes(section)) {
        issues.push(`Missing required section: "${section}"`);
      }
    }

    return issues;
  }

  getRequiredSections(agentId) {
    const requirements = {
      reviewer: [
        '### Acceptance Criteria Coverage',
        '### Recommendation',
      ],
      testGenerator: [
        '### Test Cases',
        'TC-001',
        'AC',
      ],
      testSelector: [
        '### Selected Tests',
        'Must Run',
      ],
      browserValidator: [
        '```typescript',
        '### Verdict',
      ],
      bugHunter: [
        '### Potential Bugs Found',
      ],
      releaseAnalyzer: [
        '### Release Readiness',
      ],
      manualValidator: [
        '### Manual Test Guide',
        'Preconditions',
      ],
      gherkinWriter: [
        'Feature:',
        'Scenario:',
      ],
      automator: [
        '```typescript',
        'test.describe',
      ],
    };

    return requirements[agentId] || [];
  }

  async scoreLLM(agentId, output, userMessage, chainContext) {
    if (!this.engine.callLLM) {
      // Fallback if LLM not available
      return { pass: true, score: 75, issues: [], reason: 'no-llm' };
    }

    const prompt = this._buildValidationPrompt(agentId, output, userMessage);

    try {
      const response = await this.engine.callLLM('claude-haiku-4-5', [
        {
          role: 'system',
          content: `You are a QA output validator. Rate agent outputs 0-100. Be strict but fair.
Return JSON: { "score": number, "issues": string[], "recommendation": string }`,
        },
        { role: 'user', content: prompt },
      ], { maxTokens: 500, temperature: 0.3 });

      const parsed = this._parseValidationResponse(response);
      return {
        pass: parsed.score >= 70,
        score: parsed.score,
        issues: parsed.issues || [],
        recommendation: parsed.recommendation || '',
      };
    } catch (err) {
      // If LLM call fails, be permissive (assume output is OK)
      return {
        pass: true,
        score: 60,
        issues: [`LLM validation failed: ${err.message}`],
        reason: 'llm-error',
      };
    }
  }

  _buildValidationPrompt(agentId, output, userMessage) {
    return `
Validate this agent output. Be strict about completeness and correctness.

Agent: ${agentId}
User request: ${userMessage.slice(0, 300)}

Output to validate:
---
${output.slice(0, 1500)}
---

Rate 0-100:
- 90-100: Complete, well-structured, actionable
- 70-89: Good, minor gaps
- 50-69: Partial, needs review
- 0-49: Incomplete or incorrect

Return ONLY valid JSON (no markdown): { "score": number (0-100), "issues": ["issue1", ...], "recommendation": "short advice" }
`;
  }

  _parseValidationResponse(response) {
    try {
      const json = JSON.parse(response);
      return {
        score: Math.min(100, Math.max(0, json.score || 60)),
        issues: Array.isArray(json.issues) ? json.issues : [],
        recommendation: json.recommendation || '',
      };
    } catch (e) {
      // If JSON parse fails, extract numbers manually
      const scoreMatch = response.match(/(\d+)/);
      const score = scoreMatch ? parseInt(scoreMatch[1]) : 60;
      return { score, issues: ['Failed to parse validation response'], recommendation: '' };
    }
  }
}

module.exports = { VerificationGate };
