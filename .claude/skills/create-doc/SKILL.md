---
name: create-doc
description: >
  Creates new documentation files in the correct location within chapsmind/docs/.
  Use when user wants to add documentation, write a guide, document a feature, or
  create operational docs. Ensures files are placed in the right directory per the
  documentation strategy (ADR-0016) and follow project conventions.
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Bash, Read, Write, Edit, Grep, Glob
---

## When to use this skill

- When the user asks to create new documentation
- When the user says "document", "write a guide", "add docs for"
- When a new feature needs documentation
- When creating operational runbooks or onboarding content
- When adding user-facing or compliance documentation

# Documentation Creator - ChapsMind

## Workflow

1. **Classify the document**: Determine which directory it belongs to (see Directory Map below)
2. **Check for duplicates**: Search `docs/` for existing documentation on the same topic
3. **Present proposal**: Show the proposed file path, title, and outline for validation BEFORE creating
4. **Create the file**: Write to the correct location with proper structure
5. **Update indexes**: If the parent directory has a README.md index, add the new file to it

## Directory Map

Based on ADR-0016, every document has a home. Use this decision tree:

```
Is it an architecture decision?
  YES -> docs/architecture/adr/          (use create-adr skill instead)

Is it about the arc42 architecture views?
  YES -> docs/architecture/0N-section/   (context, application, development, infrastructure, security, performance)

Is it specific to a single module (Target, Screen, Stream)?
  YES -> docs/modules/{module}/
    Backend-specific?     -> docs/modules/{module}/backend/
    Frontend-specific?    -> docs/modules/{module}/frontend/
    N8N workflows?        -> docs/modules/{module}/n8n/
    OpenSearch?           -> docs/modules/{module}/opensearch/
    AI/LLM?              -> docs/modules/{module}/ai/
    Workflow diagrams?    -> docs/modules/{module}/workflows/

Is it about deployment, security scanning, SSL, monitoring, or troubleshooting?
  YES -> docs/operations/

Is it for onboarding (getting started, glossary, DOR/DOD, dev guide)?
  YES -> docs/onboarding/

Is it user-facing (end-user guide, tutorial, FAQ)?
  YES -> docs/user/guides/

Is it a release note for users?
  YES -> docs/user/release-notes/

Is it commercial (sales deck, product sheet, demo script)?
  YES -> docs/user/commercial/

Is it compliance-related (CIR, certifications, audits)?
  YES -> docs/compliance/{category}/   (cir/, certifications/, internal/)

Is it about IDE setup or developer tooling?
  YES -> docs/tools/
```

## File Conventions

### Naming
- Use **kebab-case** for file names: `opensearch-migration-guide.md`
- Use descriptive names — the file name should make the content obvious without opening it
- Never use generic names like `notes.md`, `draft.md`, `misc.md`

### Structure
Every documentation file MUST start with:

```markdown
# [Clear, Descriptive Title]

[One-paragraph summary: what this document covers and who it's for]

## Prerequisites (if applicable)

- [What the reader needs to know or have set up before reading]

## [Main content sections...]
```

### Diagrams
- Use **Mermaid syntax** for all technical diagrams (architecture, workflows, data flows, sequences)
- Embed diagrams inline using fenced code blocks (` ```mermaid `)
- Add a brief text description above each diagram for accessibility
- Follow the color conventions (inherited from Basil diagrams, Material Design palette): blue (#2196F3) processing, purple (#8a2be2) AI/ML, green (#4CAF50) validation, orange (#FF9800) decisions, red (#F44336) errors, gray (#607D8B) storage/passive

### Cross-References
- Use relative links to reference other docs: `[ADR-0016](../architecture/adr/0016-documentation-strategy.md)`
- Never use absolute paths or URLs to link between docs in the same repo

## Rules

1. **ALWAYS** classify the document using the Directory Map before creating
2. **ALWAYS** present the proposed location and outline to the user for validation
3. **ALWAYS** check for existing documentation on the same topic (avoid duplicates)
4. **NEVER** create files outside the `docs/` directory tree
5. **NEVER** create empty placeholder files — every file must have meaningful content
6. **NEVER** use raw color values in text — only in Mermaid diagram definitions
7. If the parent directory has a README.md with an index, update it
8. If creating a new subdirectory, add a README.md index for that directory
9. For module-specific docs, always specify which module in the file or its location
10. Never add manual dates in the document body — rely on `git log` for update history

## Proposal Template

Present this to the user before creating:

```markdown
## Documentation Proposal

**Title**: [Proposed title]
**Location**: `docs/[path]/[filename].md`
**Category**: [architecture | module | operations | onboarding | user | compliance | tools]
**Module** (if applicable): [Target | Screen | Stream | N/A]
**Audience**: [developers | end-users | PO | compliance | all]

### Outline
1. [Section 1]
2. [Section 2]
3. [Section 3]

---
Create this document? (yes / no / modifications)
```

## Validation Checklist

Before marking the document as complete, verify:
- [ ] File is in the correct directory per the Directory Map
- [ ] Title is clear and descriptive
- [ ] Content is meaningful (no placeholders or TODOs left)
- [ ] Internal links use relative paths and are valid
- [ ] Diagrams (if any) use Mermaid syntax
- [ ] Parent README index is updated (if exists)
- [ ] No duplicate of existing documentation
