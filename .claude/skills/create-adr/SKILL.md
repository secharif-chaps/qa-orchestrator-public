---
name: create-adr
description: >
  Creates Architecture Decision Records (ADRs) following the ChapsMind template and conventions.
  Use when user mentions "ADR", "decision record", "architecture decision", or wants to document
  a technical decision. Ensures correct numbering, template compliance, and README index update.
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Bash, Read, Write, Edit, Grep, Glob
---

## When to use this skill

- When the user asks to create a new ADR
- When the user wants to document an architecture decision
- When the user says "ADR", "decision record", "decision technique"
- When migrating a Basil ADR to ChapsMind format

# ADR Creator - ChapsMind

## Workflow

1. **Determine next ADR number**: Read `docs/architecture/adr/README.md` to find the last numbered ADR, increment by 1
2. **Gather information**: Ask the user for the decision context if not already provided
3. **Present proposal**: Show the ADR outline for validation BEFORE creating the file
4. **Create ADR file**: Write to `docs/architecture/adr/NNNN-short-descriptive-name.md`
5. **Update README index**: Add a row to the ADR index table in `docs/architecture/adr/README.md`

## ADR Template

Every ADR MUST follow this exact structure:

```markdown
# ADR-NNNN: [Short Title]

## Status

**Status:** Proposed

**Date:** YYYY-MM-DD

**Decision Makers:** [List of people involved]

**Tags:** [tag1, tag2, tag3]

---

## Context

[Problem statement and background. What forces are at play?]

---

## Decision

[Clear statement of what was decided. For multiple sub-decisions, use D1/D2/D3 numbering.]

---

## Options Considered

### Option 1: [Name]

**Description:** [Brief description]

**Pros:**
- [Pro 1]

**Cons:**
- [Con 1]

### Option 2: [Name]

**Description:** [Brief description]

**Pros:**
- [Pro 1]

**Cons:**
- [Con 1]

---

## Consequences

### Positive

- [Positive consequence]

### Negative

- [Negative consequence]

### Neutral

- [Neutral trade-off]

---

## References

- [Related documentation or external resources]
```

## Naming Convention

- File: `NNNN-short-descriptive-name.md` (four-digit zero-padded, kebab-case)
- Title: `ADR-NNNN: Short Descriptive Title` (title case)
- Example: `0028-celery-task-routing.md` -> `ADR-0028: Celery Task Routing`

## README Index Entry Format

Add a row to the table in `docs/architecture/adr/README.md`:

```markdown
| [ADR-NNNN](./NNNN-short-descriptive-name.md) | Short Descriptive Title | Proposed | tag1, tag2 |
```

## Rules

1. **ALWAYS** use four-digit sequential numbering (check the README index for the current max)
2. **ALWAYS** start with status "Proposed" — the team accepts ADRs through review
3. **ALWAYS** include at least 2 options in "Options Considered" (the chosen one + at least one alternative)
4. **ALWAYS** include pros AND cons for each option — no option is perfect
5. **ALWAYS** update the README index after creating the ADR file
6. **NEVER** skip the "Options Considered" section — documenting alternatives is the core value of ADRs
7. **NEVER** duplicate an existing ADR — check the README index first
8. Use today's date for the Date field
9. Tags should be lowercase, comma-separated, relevant to the decision domain
10. For diagrams, use Mermaid syntax (see ADR-0016 D9)
11. If the ADR is migrated from Basil, add a note: `Migrated from basil ADR-YYYY-NNN`

## Validation Checklist

Before marking the ADR as complete, verify:
- [ ] File exists at `docs/architecture/adr/NNNN-short-descriptive-name.md`
- [ ] All template sections are filled (no placeholders left)
- [ ] At least 2 options documented with pros/cons
- [ ] README index updated with new row
- [ ] Tags are relevant and lowercase
- [ ] No duplicate of an existing ADR
