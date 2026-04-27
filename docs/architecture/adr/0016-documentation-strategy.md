# ADR-0016: Centralized Technical Documentation and Tooling Strategy

## Status

**Status:** Proposed

**Date:** 2026-03-30

**Decision Makers:** ChapsMind Engineering Team

**Tags:** documentation, architecture, migration, adr, onboarding, tooling, mermaid, compliance, workflow

---

## Context

ChapsMind is the result of an ongoing migration from the Target/Basil platform. During this transition, technical documentation has grown organically across three separate sources:

| Source              | Location            | Content                                                                                                                                                                                      | State                                  |
| ------------------- | ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------- |
| **basil/docs/**     | basil repository    | 30+ files (backend, frontend, n8n, security, OpenSearch, deployment) + 11 ADRs (numbered `YYYY-NNN` from 2025-001 to 2026-011)                                                               | Active, maintained alongside migration |
| **chapsmind/docs/** | chapsmind monorepo  | arc42 architecture documentation (6 sections: context, application, development, infrastructure, security, performance) + 15 ADRs (numbered `NNNN` from 0001 to 0015) + module documentation | Active, primary target                 |
| **basiltools/**     | separate repository | PO-facing documentation (prompts, workflows) + legacy Sprint Review app (static HTML/JS, JSON data for sprints 1–31)                                                                         | Audited                                |

### Problems

1. **No single entry point** — New team members must know which repo to look in for which topic. There is no index or cross-referencing between sources.
2. **Incompatible ADR numbering** — Basil uses `YYYY-NNN` (date-prefixed), ChapsMind uses `NNNN` (sequential). Both series are authoritative, neither references the other.
3. **No shared ADR template** — Basil ADRs use a lighter format (Context/Decision/Consequences). ChapsMind has a richer template (Options Considered with pros/cons, tags, decision makers) but it was not enforced across projects.
4. **Isolated PO documentation** — Product-oriented docs live in a separate tooling repo, disconnected from technical documentation.
5. **Difficult onboarding** — A new developer must navigate 3 repos, 2 ADR conventions, and undocumented tribal knowledge to get a full picture of the system.
6. **Documentation gaps** — Some areas of the application likely have no documentation at all. There is no inventory of what _should_ be documented vs. what _is_ documented.

---

## Decision

### D1. Single documentation location: `chapsmind/docs/`

All technical documentation converges into the ChapsMind monorepo. Basil documentation is migrated as part of the Target → ChapsMind transition. basiltools content is audited and either migrated or archived.

**Why this option:** ChapsMind is the migration target. Its documentation already has the most mature structure (arc42). Colocating documentation with code ensures it stays in sync and benefits from the same review process. See _Options Considered — O1_.

### D2. Hybrid directory structure (by concern, with module subdivisions)

```
chapsmind/docs/
├── architecture/              # arc42 views (existing, unchanged)
│   ├── 01-context/            # Stakeholders, constraints, business goals
│   ├── 02-application/        # System context, containers, data flows, modules
│   ├── 03-development/        # Backend/frontend architecture, coding standards, testing
│   ├── 04-infrastructure/     # Deployment, hosting, CI/CD (placeholder)
│   ├── 05-security/           # Authentication, authorization, data protection, threat model
│   ├── 06-performance/        # Scalability, caching, monitoring
│   └── adr/                   # Unified ADR registry
├── modules/                   # Per-module operational docs
│   ├── target/                # WatchFiles, OpenSearch, n8n workflows, AI prompts
│   ├── screen/                # Company cards, Dify/LangGraph integration
│   └── stream/                # Distribution channels
├── operations/                # Deployment, security scanning, SSL, troubleshooting
├── onboarding/                # Getting started, glossary, dev guide, DOR/DOD
├── user/                      # User-facing & commercial documentation
│   ├── guides/                # End-user guides, tutorials, FAQ
│   ├── release-notes/         # User-visible release notes per version
│   └── commercial/            # Sales decks, product sheets, demo scripts
├── compliance/                # Internal compliance & certification docs
│   ├── cir/                   # CIR (Credit Impot Recherche) documentation
│   ├── certifications/        # Security certifications (ISO 27001, SOC2, etc.)
│   └── internal/              # Other internal process docs (audits, reviews)
└── tools/                     # Sprint review, PO tooling (if migrated)
```

**Why this option:** Purely type-based (all ADRs together, all guides together) loses module context. Purely module-based duplicates cross-cutting concerns. The hybrid approach groups by concern at the top level while allowing module-specific depth where needed. The `user/` and `compliance/` directories address documentation audiences beyond the development team — end-users, sales, and internal compliance — ensuring these artifacts are versioned and colocated with the rest of the documentation rather than scattered in shared drives or wikis. See _Options Considered — O2_.

### D3. Unified sequential ADR numbering (`NNNN`)

All ADRs use a single four-digit sequential numbering scheme. The 11 Basil ADRs are renumbered starting at 0017 (this ADR is 0016). Each migrated ADR includes a note: `Migrated from basil ADR-YYYY-NNN`.

A mapping table is maintained in the ADR README during the transition period.

**Why this option:** Sequential numbering is simpler, industry-standard, and the date is already captured in ADR metadata. See _Options Considered — O3_.

### D4. Common ADR template

All ADRs follow the existing ChapsMind template (`docs/architecture/adr/template.md`), which includes:

- Status, Date, Decision Makers, Tags
- Context
- Decision
- Options Considered (with pros/cons for each)
- Consequences (positive, negative, neutral)
- References

Basil ADRs migrated without an "Options Considered" section are migrated as-is, with a note that the section was not part of the original format.

**Why this option:** The ChapsMind template is already richer and more structured. Retroactively reconstructing options for old decisions would be speculative. See _Options Considered — O4_.

### D5. Plain Markdown, no static site generator

Documentation remains as Markdown files, navigable via GitLab's built-in rendering. No static site generator (MkDocs, Docusaurus) is introduced at this stage. CI linting (markdown lint, dead link checks) may be added as a follow-up.

**Why this option:** The team is small, the doc volume is manageable, and adding a build pipeline for docs introduces maintenance cost without proportional benefit today. See _Options Considered — O5_.

### D6. basiltools — audit results and migration plan

The basiltools repository has been audited. It contains three blocks:

| Block              | Content                                                                                                                | Decision                                                                                                                                                                                             |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Prompts/**       | 10 Claude Code skill prompts (Jira-_, CodeReview, N8N-_, Optimization-Guide, SprintReview-\*)                          | 8/10 already migrated to `chapsmind/.claude/skills/`. The 2 SprintReview-specific prompts stay in the sprintreview repo. **Optimization-Guide** is still relevant and should be migrated as a skill. |
| **Prompts N8N/**   | ~35 versioned prompt files (ChatAssistant v2.1→v3.6, DeepSearch, ClassifyWatchfile, etc.)                              | Historical prompt versions. Active versions live in `basil/api/templates/prompts/` (Twig). **Archive — no migration needed.**                                                                        |
| **SprintReviews/** | Legacy HTML/JS sprint review app + data for sprints 1–32                                                               | **Deprecated** — replaced by the standalone sprintreview Vue app (sprints 31–36). Archive.                                                                                                           |
| **Workflow/**      | ~30 Mermaid diagrams in 3 series (2025-05: product vision, 2025-11: N8N architecture v1, 2025-12: N8N architecture v2) | **Migrate to `docs/modules/target/workflows/`**, preserving the date-based subdirectories. See _Options Considered — O7_.                                                                            |

**Why `docs/modules/target/workflows/`:** These diagrams document Target-specific N8N workflows (orchestrator, DeepSearch pipeline, RabbitMQ, document validation). They are not cross-cutting architecture diagrams. See _Options Considered — O7_.

### D7. CI linting with markdownlint-cli2 and lychee

Prettier already handles Markdown formatting (wrapping, tables, indentation). Two additional tools are introduced for what Prettier does not cover:

- **markdownlint-cli2** — structural linting (heading level increments, no duplicate headings, consistent list markers). Installed as npm devDependency, configured via `.markdownlint-cli2.jsonc` (with Prettier-overlapping rules disabled). Available locally via Taskfile and in GitLab CI.
- **lychee** — dead link detection. Checks both internal links (relative paths between Markdown files) and external URLs, including anchor fragments (`#section-name`). Rust-based, used via Docker image `lycheeverse/lychee` (no local install required). Configured via `lychee.toml` (exclusion patterns, timeout, retry). Available locally via Taskfile and in GitLab CI. Particularly critical after migrating 60+ files with renumbered ADRs and reorganized directories.

Both checks run on MRs that modify files under `docs/` and fail the pipeline on errors.

**Why these tools:** markdownlint-cli2 is the industry standard for Markdown structural linting with minimal setup (Node.js already in the stack). lychee is the fastest and most feature-rich link checker available, with native Docker support for CI. See _Options Considered — O6_.

### D8. Documentation maintenance workflow

Documentation must stay in sync with the codebase. Rather than relying on periodic audits, documentation updates are integrated into the development workflow:

1. **Definition of Done includes documentation** — Any MR that changes behavior visible to users, modifies an API contract, adds/removes a feature, or changes infrastructure must update or create the relevant documentation. The MR template includes a "Documentation" checkbox.
2. **Claude Code skills enforce placement** — Two dedicated skills (`create-adr` and `create-doc`) guide contributors to the correct location and format when creating new documentation. This eliminates "where do I put this?" friction.
3. **CI linting catches drift** — markdownlint-cli2 and lychee (see D7) run on every MR touching `docs/`, catching broken links from renamed files or moved sections.
4. **Quarterly gap review** — Once per quarter, the tech lead runs the gap analysis from Phase 5 to identify undocumented areas. Results are converted into user stories for the next sprint. A recurring Jira ticket is created via Jira Automation to ensure this review is not forgotten (rule: create ticket "Documentation gap review QX YYYY" at the start of each quarter, assigned to the tech lead).

**Why this approach:** Documentation debt accumulates silently. Embedding documentation in the Definition of Done catches it at the source (the MR), while quarterly reviews catch what slips through. The Claude Code skills reduce friction by automating the boilerplate and directory decisions. See _Options Considered — O8_.

### D9. Mermaid as the standard for technical diagrams

All technical diagrams (architecture, workflows, data flows, sequence diagrams) are authored in Mermaid syntax, embedded directly in Markdown files using fenced code blocks (` ```mermaid `).

Basil already uses ~30 Mermaid diagrams extensively for workflow documentation. This practice is preserved and extended to all ChapsMind documentation.

**Conventions:**

- Diagrams are embedded inline in the Markdown file they illustrate (not in separate `.mmd` files)
- Use consistent color coding across diagrams. These colors are inherited from the ~30 Mermaid diagrams already in production in Basil (`docs/workflow-diagrams.md`), ensuring visual continuity after migration:
  - Blue (#2196F3) — Processing and analysis steps (Material Blue 500)
  - Purple (#8a2be2) — AI/ML agents and LLM calls
  - Green (#4CAF50) — Validation and quality control (Material Green 500)
  - Orange (#FF9800) — Decision points and branching logic (Material Orange 500)
  - Red (#F44336) — Errors, alerts, and escalation paths (Material Red 500)
  - Gray (#607D8B) — Storage, documentation, and passive elements (Material Blue Grey 500)
- Each diagram includes a brief text description above it for accessibility
- Complex diagrams (>50 nodes) may be split into sub-diagrams with cross-references
- GitLab renders Mermaid natively — no additional tooling required

**Why Mermaid:** Diagrams as code are versioned alongside documentation, diffable in MRs, and don't require external tools (Lucidchart, draw.io) or binary image files. GitLab renders Mermaid natively, and a future MkDocs migration would also support it via `pymdownx.superfences`. The team already has Mermaid expertise from Basil. See _Options Considered — O9_.

---

## Options Considered

### O1. Documentation location

#### Option A: `chapsmind/docs/` (chosen)

**Description:** All documentation lives in the monorepo alongside the code.

**Pros:**

- Single source of truth, single search scope
- Documentation changes can be part of the same MR as code changes
- Benefits from existing CI/CD and review process
- Natural fit since ChapsMind is the migration target

**Cons:**

- Monorepo docs directory grows large over time
- PO-facing docs mixed with developer docs (mitigated by directory structure)

#### Option B: Dedicated documentation repository

**Description:** A standalone `chapsmind-docs` repository for all documentation.

**Pros:**

- Clean separation of concerns
- Easier to give non-developer access (POs, stakeholders)
- Independent versioning from code

**Cons:**

- Documentation drifts from code (no atomic commits)
- Additional repo to maintain, clone, and search
- Cross-referencing between code and docs is harder
- Duplicates the fragmentation problem we are trying to solve

#### Option C: Keep per-project docs, add cross-references

**Description:** Each repo keeps its own docs, with a central index linking them.

**Pros:**

- No migration effort
- Docs stay close to their respective code

**Cons:**

- Does not solve the core problem (fragmentation)
- Central index becomes stale quickly
- Onboarding still requires navigating multiple repos

### O2. Directory structure

#### Option A: Hybrid by concern (chosen)

**Description:** Top-level directories by concern (architecture, modules, operations, onboarding), with module subdivisions where needed.

**Pros:**

- Cross-cutting docs (architecture, operations) are not duplicated
- Module-specific docs have a clear home
- Scales well as modules are added
- arc42 structure is preserved as-is

**Cons:**

- Some docs could fit in multiple places (judgment calls needed)

#### Option B: Purely by module

**Description:** `docs/target/`, `docs/screen/`, `docs/stream/`, each containing all doc types.

**Pros:**

- Everything about a module is in one place
- Simple mental model for module teams

**Cons:**

- Cross-cutting docs (deployment, security, architecture) are duplicated or awkwardly placed
- arc42 structure would need to be broken up or duplicated

#### Option C: Purely by type

**Description:** `docs/adr/`, `docs/guides/`, `docs/runbooks/`, `docs/reference/`

**Pros:**

- Consistent structure, easy to find by doc type
- Familiar pattern from many open-source projects

**Cons:**

- Module context is lost (a Target guide sits next to a Screen guide)
- Scales poorly as the number of modules and doc types grows

### O3. ADR numbering scheme

#### Option A: Sequential `NNNN` (chosen)

**Description:** Simple four-digit sequential numbering, continuing ChapsMind's existing series.

**Pros:**

- Industry standard
- Simple, no ambiguity
- Date is already in ADR metadata
- Continues existing ChapsMind convention without disruption

**Cons:**

- No date visibility in file listing (mitigated by metadata and git history)
- Basil ADRs need renumbering (one-time cost)

#### Option B: Date-prefixed `YYYY-NNN`

**Description:** Keep Basil's convention, renumber ChapsMind ADRs.

**Pros:**

- Date visible in file name
- Chronological sorting in file explorer

**Cons:**

- Non-standard format
- Would require renumbering the larger set (14 ChapsMind ADRs vs. 11 Basil ADRs)
- Year prefix is redundant with metadata
- Awkward for ADRs that span year boundaries

#### Option C: Module-prefixed `MODULE-NNNN`

**Description:** ADRs prefixed by module (e.g., `TARGET-001`, `SCREEN-001`).

**Pros:**

- Immediate module context in file name

**Cons:**

- Cross-cutting ADRs don't belong to a single module
- Fragments the sequence, harder to get a chronological view
- Non-standard

### O4. ADR template

#### Option A: Adopt ChapsMind template as-is (chosen)

**Description:** Use the existing `template.md` from ChapsMind for all new and migrated ADRs.

**Pros:**

- Already exists and is in use
- Richer format (Options Considered, tags, decision makers)
- No effort to create a new template
- Migrated ADRs that lack "Options Considered" are accepted as-is with a note

**Cons:**

- Slightly heavier than Basil's format (more sections to fill)

#### Option B: Create a new merged template

**Description:** Design a new template combining the best of both formats.

**Pros:**

- Could be tailored to exact team needs

**Cons:**

- Effort to design, agree on, and adopt
- Existing ChapsMind ADRs would not match the new format
- Premature optimization — the current template works

### O5. Documentation tooling

#### Option A: Plain Markdown (chosen)

**Description:** Markdown files rendered by GitLab, no static site generator.

**Pros:**

- Zero setup and maintenance cost
- GitLab renders Markdown natively (including Mermaid diagrams)
- No build pipeline to maintain
- Easy to start writing immediately
- Can always add a generator later without restructuring

**Cons:**

- No full-text search across docs (only GitLab's file search)
- No custom navigation, theming, or versioning
- No generated site to share with non-GitLab users

#### Option B: MkDocs Material

**Description:** Static site generator with Material theme, built from the same Markdown files.

**Pros:**

- Full-text search, navigation sidebar, theming
- Native Mermaid support
- Industry standard for technical docs
- Built from the same Markdown — can be added non-disruptively

**Cons:**

- CI pipeline to build and deploy
- Another dependency to maintain
- Premature if the team navigates docs fine via GitLab

#### Option C: Docusaurus

**Description:** React-based documentation framework.

**Pros:**

- Rich plugin ecosystem, versioning built-in
- Good for public-facing documentation

**Cons:**

- Heavier stack (React/Node.js) for internal docs
- More opinionated directory structure
- Overkill for a small team's internal documentation

### O6. CI linting tools

#### Structural linting

##### Option A: markdownlint-cli2 (chosen)

**Description:** Node.js CLI with ~55 built-in rules, successor to markdownlint-cli. Config via `.markdownlint-cli2.jsonc`.

**Pros:**

- Low setup, good defaults out of the box
- Auto-fix mode for some rules
- VS Code extension for local feedback
- Node.js already in the stack (npm devDependency)
- Per-directory config overrides (useful for monorepos)

**Cons:**

- Some rules overlap with Prettier (must be explicitly disabled)
- Auto-fix is limited to a subset of rules

##### Option B: remark-lint

**Description:** Modular Markdown linter from the unified/remark ecosystem. Each rule is a separate npm package.

**Pros:**

- Extremely configurable via AST-based plugins
- Can also transform Markdown (not just lint)
- Rich ecosystem (unified, rehype, retext)

**Cons:**

- Higher setup complexity (assemble rules from many small packages)
- Heavier dependency tree
- Less "just works" experience than markdownlint

##### Option C: No structural linting (Prettier only)

**Description:** Rely on Prettier for Markdown formatting and skip structural linting entirely.

**Pros:**

- Zero additional tooling
- Already in place

**Cons:**

- Does not catch structural issues (heading order, duplicate headings, missing alt text)
- Does not enforce consistency across 60+ migrated files from different sources

#### Dead link detection

##### Option A: lychee (chosen)

**Description:** Rust-based async link checker. Available as Docker image `lycheeverse/lychee`.

**Pros:**

- Very fast (async Rust, concurrent requests)
- Checks internal and external links, including anchors/fragments
- Supports Markdown and HTML
- Docker image for CI (no install required), Taskfile-friendly for local use
- Configurable via `lychee.toml` (exclusions, retries, timeouts)

**Cons:**

- Not an npm package (requires Docker or binary install)
- Some false positives on sites with aggressive bot protection (mitigated by exclusion list)

##### Option B: markdown-link-check

**Description:** Node.js link checker for Markdown files.

**Pros:**

- Simple to use, npm package
- Widely adopted

**Cons:**

- Significantly slower than lychee (single-threaded)
- No anchor/fragment checking
- Less actively maintained

##### Option C: No link checking

**Description:** Skip automated link checking, rely on manual review.

**Pros:**

- Zero tooling overhead

**Cons:**

- Broken links accumulate silently, especially after a migration of 60+ files
- Manual review does not scale

### O8. Documentation maintenance strategy

#### Option A: Integrated workflow (chosen)

**Description:** Documentation updates are part of the Definition of Done for MRs, enforced by MR templates, Claude Code skills, CI linting, and quarterly gap reviews.

**Pros:**

- Catches documentation drift at the source (the MR that introduces the change)
- Low overhead — developers write docs alongside code, not as a separate task
- Claude Code skills eliminate placement/format friction
- Quarterly reviews catch what slips through

**Cons:**

- Relies on discipline and code review enforcement
- Quarterly reviews require a dedicated timebox

#### Option B: Dedicated documentation sprints

**Description:** Schedule periodic sprints (or sprint portions) dedicated entirely to documentation catch-up.

**Pros:**

- Focused time for documentation
- Can address large backlogs

**Cons:**

- Documentation drifts between sprints
- Feels like a chore rather than part of the development flow
- Knowledge context is lost between writing code and documenting it later

#### Option C: Documentation champion role

**Description:** Assign a rotating "documentation champion" per sprint who is responsible for reviewing and updating docs.

**Pros:**

- Clear ownership
- Spreads documentation knowledge across the team

**Cons:**

- Single point of failure each sprint
- Champion may lack context on changes they didn't write
- Does not scale — bottleneck grows with team size

### O9. Diagram format

#### Option A: Mermaid (chosen)

**Description:** All diagrams authored in Mermaid syntax, embedded in Markdown fenced code blocks.

**Pros:**

- Diagrams as code — versioned, diffable, reviewable in MRs
- No external tools or accounts needed
- GitLab renders natively, MkDocs supports via plugin
- Team already has Mermaid expertise from Basil (~30 existing diagrams)
- Text-based — searchable and accessible

**Cons:**

- Limited layout control compared to GUI tools
- Complex diagrams can become hard to read in source
- Some diagram types (freeform, pixel-precise) are not well supported

#### Option B: draw.io / diagrams.net

**Description:** Use draw.io for diagrams, storing `.drawio` XML files in the repo.

**Pros:**

- Full layout control, pixel-perfect positioning
- Rich diagram types (network, UML, freeform)
- VS Code extension available

**Cons:**

- Binary-like XML files — not meaningfully diffable in MRs
- Requires a GUI tool to edit
- Rendered images must be exported separately for Markdown embedding
- Not rendered natively by GitLab

#### Option C: External tool (Lucidchart, Miro)

**Description:** Use a SaaS tool for diagrams, linking to them from documentation.

**Pros:**

- Best-in-class UX for diagram creation
- Real-time collaboration

**Cons:**

- Not versioned with the code
- Links break, diagrams drift from reality
- Requires paid licenses
- Not accessible offline or in CI

### O7. Workflow diagrams location

#### Option A: `docs/modules/target/workflows/` (chosen)

**Description:** Place Mermaid workflow diagrams under the Target module documentation, organized by date (2025-05/, 2025-11/, 2025-12/).

**Pros:**

- Diagrams are Target-specific (N8N orchestrator, DeepSearch, RabbitMQ, document validation)
- Colocated with other Target module documentation
- Clear ownership — Target team maintains them

**Cons:**

- If future cross-module workflows emerge, they won't fit under a single module

#### Option B: `docs/architecture/diagrams/`

**Description:** Place diagrams in the existing (empty) arc42 diagrams directory alongside architecture documentation.

**Pros:**

- Central location for all diagrams
- Already exists in the arc42 structure (currently empty with `.gitkeep`)

**Cons:**

- These are not architecture-level diagrams — they document module-specific N8N workflows
- Mixes cross-cutting architecture diagrams with module-specific operational flows
- As more modules add diagrams, the directory becomes a grab-bag without clear ownership

---

## Consequences

### Positive

- Single entry point for all technical documentation
- Unified ADR registry with consistent numbering and template
- Simplified onboarding — one repo, one structure, one convention
- Documentation changes are reviewed alongside code changes
- arc42 architecture documentation is preserved and extended
- basiltools audit revealed most content is deprecated — only workflow diagrams and one skill need migration
- User-facing and compliance documentation have a clear home, preventing sprawl into shared drives or wikis
- Mermaid diagrams are versioned, diffable, and renderable without external tools — preserving the team's existing expertise from Basil
- Documentation maintenance workflow embeds docs into the development process rather than treating them as an afterthought
- Claude Code skills for ADR and doc creation reduce friction and enforce correct placement

### Negative

- One-time migration effort for Basil docs (11 ADRs + 30+ files)
- Risk of broken internal links during migration (mitigated by CI lint as follow-up)
- Team presentation requires a dedicated timeslot and preparation effort
- Quarterly gap reviews require recurring time investment

### Neutral

- Plain Markdown is a deliberate starting point, not a permanent constraint. The directory structure is designed to be compatible with MkDocs or similar generators if the need arises.
- The mapping table for renumbered Basil ADRs is a transitional artifact that can be removed once the team is familiar with the new numbers.
- The `docs/user/` and `docs/compliance/` directories may start empty and grow organically as those documentation needs materialize.
- Mermaid's layout limitations are acceptable for the team's current diagram complexity. If pixel-precise diagrams are needed in the future, they can be added as exported images alongside the Mermaid source.

---

## Migration Plan

The migration is sequenced into phases that can be broken down into user stories.

### Phase 1 — Migrate Basil ADRs

**Dependencies:** This ADR accepted

Renumber the 11 Basil ADRs into the ChapsMind sequence (0017–0027):

| New ID | Old ID   | Title                                        |
| ------ | -------- | -------------------------------------------- |
| 0017   | 2025-001 | Mercure Scalable Topics and Tokens           |
| 0018   | 2025-002 | Multi-Tenant Architecture                    |
| 0019   | 2026-003 | Document Processing Pipeline Architecture    |
| 0020   | 2026-004 | Document Quality Scoring Processors Phase 1  |
| 0021   | 2026-005 | Document Quality Scoring Processors Phase 2  |
| 0022   | 2026-006 | Document Deduplication Strategy              |
| 0023   | 2026-007 | Migration Target to ChapsMind                |
| 0024   | 2026-008 | Multi-Provider Collection Architecture       |
| 0025   | 2026-009 | Provider-Agnostic Post-Collection Processing |
| 0026   | 2026-010 | Migration Backend Target API vers ChapsMind  |
| 0027   | 2026-011 | Elasticsearch to OpenSearch Migration        |

For each ADR:

- Rename file to `NNNN-adr-new-title.md`
- Update title to `ADR-NNNN: Adr New Title`
- Add note: `Migrated from basil ADR-YYYY-NNN`
- Add to the ADR README index
- Adapt metadata to ChapsMind template (add Status, Date, Decision Makers, Tags fields)
- **Options Considered**: already present in 2025-001, 2026-003, 2026-007, 2026-008, 2026-009 — reformat to match template (pros/cons per option). For 2026-011, an "Explored Alternatives" appendix exists and can be adapted. The remaining ADRs (2025-002, 2026-004, 2026-005, 2026-006, 2026-010) have no alternatives section — leave empty with a note "Not documented in original ADR"
- **Consequences**: already present in all ADRs — reformat into Positive/Negative/Neutral where needed

### Phase 2 — Migrate Basil technical docs

**Dependencies:** Phase 1 (for ADR cross-references)

Move ~60 Basil doc files into the target structure. Proposed mapping:

**`modules/target/`** — Module-specific docs:

- `backend/` — chat-performance-baseline, collect-data-events, collect-system
- `frontend/` — api-patterns, components, internationalization, keyboard-shortcuts, pinia-colada-migration-\*, state-management, styling, vuellar
- `n8n/` — best-practices, concepts, naming-convention, quick-start, setup, testing-quick-reference, upgrade, workflow-testing, workflow-validation, n8n-auto-rename-workflow, document-summary-workflow, watchfile-classification-workflow, workflow-diagrams, workflow-overview
- `opensearch/` — opensearch-api, opensearch-dashboards, opensearch-migrations, opensearch-security-setup, opensearch-watchfile-events
- `ai/` — ai-validation, api-messages, agent-skills-strategy, create-agent-skills

**`operations/`** — Cross-cutting operational docs:

- deployment-policy, staging-deployment, ssl-certificates, security-scanning, security/index, security/rate-limiting, keycloak, keycloak-theme, mailpit, renovate-setup, renovate-workflow, cron-watchfile-document-quota, usage-limits, troubleshooting

**`onboarding/`** — Getting started and team references:

- getting-started, development-guide, debugging, testing, glossary, index, definition-of-ready, definition-of-done, code-review-reference, ux-writing, web-accessibility-guide

**`tools/`** — IDE and developer tooling:

- tools/phpstorm-setup, tools/xdebug

For each file:

- Move to target directory
- Update internal links (relative paths, ADR references)
- Flag any content that appears duplicated with existing ChapsMind docs for manual review

### Phase 3 — Migrate basiltools content

**Dependencies:** None (can run in parallel with Phases 1–2)

Audit completed (see Decision D6). Actions:

- **Workflow diagrams**: copy `Workflow/2025-05/`, `Workflow/2025-11/`, `Workflow/2025-12/` into `docs/modules/target/workflows/`, preserving date subdirectories
- **Optimization-Guide**: migrate as a Claude Code skill to `chapsmind/.claude/skills/`
- **Prompts/, Prompts N8N/, SprintReviews/**: no migration — deprecated or already migrated
- Archive the basiltools repository

### Phase 4 — CI checks

**Dependencies:** Phase 2

- Install markdownlint-cli2 as npm devDependency, add `.markdownlint-cli2.jsonc` config
- Add `lychee.toml` config, add Taskfile commands for both tools (local usage)
- Add GitLab CI jobs for both checks on MRs modifying `docs/`
- See Decision D7 for tool rationale

### Phase 5 — Gap analysis

**Dependencies:** Phase 2

- Inventory what _should_ be documented (each module, each infrastructure component, each workflow)
- Compare against what _is_ documented after migration
- Identify missing user-facing documentation (`docs/user/`) and compliance documentation (`docs/compliance/`)
- Prioritize missing documentation as follow-up user stories
- Create a Jira Automation rule to generate a recurring ticket "Documentation gap review QX YYYY" at the start of each quarter, assigned to the tech lead (see D8, point 4)

### Phase 6 — Team presentation and onboarding

**Dependencies:** Phases 1–3 completed (documentation is in place to demonstrate)

Present the new documentation architecture and best practices to the entire engineering team. This is critical for adoption.

**Content:**

- Overview of the new `docs/` directory structure and the rationale behind each top-level directory
- How to find documentation: navigation patterns, search, README indexes
- How to create new documentation: live demo of the `create-doc` and `create-adr` Claude Code skills
- Mermaid diagram conventions: color coding, embedding, examples from migrated Basil diagrams
- Documentation maintenance workflow: Definition of Done checklist, MR template, CI linting feedback
- Q&A and team feedback collection

**Format:**

- 45-minute session (30 min presentation + 15 min Q&A)
- Record the session for future onboarding
- Create a one-page cheat sheet summarizing the key conventions (placed in `docs/onboarding/`)

---

## References

- [ChapsMind ADR template](./template.md)
- [ChapsMind architecture documentation](../README.md)
- [Basil documentation](https://git.mediaspeech.com/basil/basil/-/tree/main/docs) (source, pre-migration)
- [Michael Nygard — Documenting Architecture Decisions](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions)
- [arc42 documentation template](https://arc42.org/)
