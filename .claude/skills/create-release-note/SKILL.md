---
name: create-release-note
description: >
  Creates user-facing release notes for ChapsMind versions.
  Use when user mentions "release note", "changelog", "version", "release",
  "what's new", or when preparing a new version for deployment.
  Generates structured release notes from git history and manual input,
  targeting both technical and non-technical audiences.
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Bash, Read, Write, Edit, Grep, Glob
---

## When to use this skill

- When the user asks to create a release note
- When preparing a new version for deployment
- When the user says "release note", "changelog", "what's new", "version X.Y.Z"
- When summarizing changes for users or stakeholders after a sprint

# Release Note Creator - ChapsMind

## Workflow

1. **Determine version**: Ask the user for the version number (semver) or infer from context
2. **Gather changes**: Analyze git history since last release/tag + user-provided highlights
3. **Classify changes**: Group by audience (user-visible vs. internal) and category
4. **Present proposal**: Show the release note outline for validation BEFORE creating
5. **Create the file**: Write to `docs/user/release-notes/vX.Y.Z.md`
6. **Update index**: Add entry to `docs/user/release-notes/README.md` (create if missing)

## Gathering Changes

### From git history

```bash
# Find the last tag/release
git tag --sort=-version:refname | head -5

# List commits since last tag (or since a specific commit/date)
git log <last-tag>..HEAD --oneline --no-merges

# Group by scope from gitmoji commits
git log <last-tag>..HEAD --oneline --no-merges | grep "feat("
git log <last-tag>..HEAD --oneline --no-merges | grep "fix("
```

### From user input

Always ask the user:
- Are there highlights or key features to emphasize?
- Any known issues or breaking changes?
- Any deprecation notices?

## Release Note Template

```markdown
# ChapsMind vX.Y.Z — [Short Release Title]

**Date**: YYYY-MM-DD

---

## Highlights

[1-3 paragraph summary of the most important changes, written for a non-technical audience.
Focus on user value, not implementation details.]

---

## What's New

### [Feature Category 1]

- **[Feature name]**: Brief description of what the user can now do ([MODULE])

### [Feature Category 2]

- **[Feature name]**: Brief description ([MODULE])

## Improvements

- [Description of improvement] ([MODULE])

## Bug Fixes

- [Description of what was broken and is now fixed] ([MODULE])

## Breaking Changes

> **Action required**: [What the user/admin needs to do]

- [Description of breaking change and migration path]

## Known Issues

- [Description of known issue and workaround if any]

## Internal Changes

<details>
<summary>Technical changes (for developers)</summary>

- [Internal change 1]
- [Internal change 2]

</details>
```

## File Conventions

### Location
- **User-facing release notes**: `docs/user/release-notes/vX.Y.Z.md`
- **Index**: `docs/user/release-notes/README.md`

### Naming
- File: `vX.Y.Z.md` (e.g., `v1.3.0.md`, `v2.0.0-rc1.md`)
- Title: `ChapsMind vX.Y.Z — Short Descriptive Title`

### README Index Format

```markdown
# Release Notes

| Version | Date | Title |
|---------|------|-------|
| [vX.Y.Z](./vX.Y.Z.md) | YYYY-MM-DD | Short title |
```

Most recent version at the top.

## Writing Guidelines

### Audience-aware language

Release notes serve **multiple audiences**. Write accordingly:

| Audience | What they care about | Tone |
|----------|---------------------|------|
| End users | What can I do now? What changed in my workflow? | Simple, benefit-oriented |
| Admins/DevOps | Breaking changes, migration steps, config changes | Precise, actionable |
| Developers | API changes, new endpoints, deprecations | Technical but concise |
| PO/Stakeholders | Feature delivery, business value | High-level, value-driven |

### Rules

1. **Highlights first**: Lead with the 1-3 most impactful changes in plain language
2. **User value over implementation**: "You can now filter companies by date" not "Added date filter query parameter to GET /companies"
3. **Module tags**: Add `([TARGET])`, `([SCREEN])`, `([STREAM])`, `([GLOBAL])` after each item
4. **Breaking changes are prominent**: Use a blockquote with "Action required" for anything that needs user intervention
5. **Internal changes are collapsed**: Use `<details>` for technical changes that don't affect users
6. **No Jira ticket numbers in user-facing sections**: Keep `TAR-xxx` references in the internal section only
7. **Consistent tense**: Use past tense for what changed ("Added", "Fixed", "Improved")
8. **Link to docs**: If a new feature has documentation, link to it

### Categories (Keep a Changelog inspired)

Use these categories, removing any that are empty:

| Category | When to use |
|----------|-------------|
| **What's New** | New features and capabilities |
| **Improvements** | Enhancements to existing features |
| **Bug Fixes** | Things that were broken and are now fixed |
| **Breaking Changes** | Changes that require user action |
| **Deprecations** | Features that will be removed in a future version |
| **Known Issues** | Problems we're aware of |
| **Internal Changes** | Technical changes not visible to users (collapsed) |

## Proposal Template

Present this to the user before creating:

```markdown
## Release Note Proposal

**Version**: vX.Y.Z
**Title**: [Proposed short title]
**Date**: YYYY-MM-DD
**Type**: Major / Minor / Patch

### Summary
[2-3 sentences describing the release]

### Changes Found
- X new features
- X improvements
- X bug fixes
- X breaking changes

### Highlights
1. [Main highlight]
2. [Second highlight]

---
Create this release note? (yes / no / modifications)
```

## Validation Checklist

Before marking the release note as complete, verify:
- [ ] File exists at `docs/user/release-notes/vX.Y.Z.md`
- [ ] Version number follows semver
- [ ] Highlights section is written in non-technical language
- [ ] All items have module tags
- [ ] Breaking changes are clearly marked with action required
- [ ] Internal changes are in a collapsed `<details>` block
- [ ] No Jira ticket numbers in user-facing sections
- [ ] README index is updated with new entry at the top
- [ ] No empty categories left in the document
