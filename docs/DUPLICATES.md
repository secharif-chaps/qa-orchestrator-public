# Documentation duplicates flagged during Basil → ChapsMind migration

This list captures documents migrated from `basil/docs/` (now under `docs/modules/target/`, `docs/onboarding/`, etc.) that share a filename with a pre-existing ChapsMind document. Both copies are kept for now — a follow-up needs to compare them and produce a single canonical version.

Tracked under TAR-1532 (Basil tech-doc migration). Resolve before the documentation gap-analysis story (TAR-1535) starts.

| Topic               | Pre-existing ChapsMind doc                                            | Migrated Basil doc                                                                                        | Notes                                                                                                                                                                                                                                                                     |
| ------------------- | --------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Getting started     | [`docs/getting-started.md`](./getting-started.md) (251 lines)         | [`docs/onboarding/getting-started.md`](./onboarding/getting-started.md) (589 lines)                       | Different content. ChapsMind version is a short monorepo bootstrap; basil version is the long-form target setup. Likely: keep both, but rename the chapsmind one to a clearer scope or merge the basil-specific parts into a `modules/target/` setup page.                |
| Create agent skills | [`docs/create-agent-skills.md`](./create-agent-skills.md) (463 lines) | [`docs/modules/target/ai/create-agent-skills.md`](./modules/target/ai/create-agent-skills.md) (463 lines) | Same line count, byte-different. Probably the same source with minor edits on one side. Diff them and pick one home; the basil version is colocated with `agent-skills-strategy.md`, which suggests keeping the `modules/target/ai/` copy and removing the top-level one. |

## How this list was produced

`find apps/target/docs -name '*.md'` was cross-referenced against the existing tree under `docs/` before the move. Only filename collisions are listed; semantic overlaps (e.g. n8n quick-start vs. existing n8n notes) are out of scope for this list and belong in the gap analysis.
