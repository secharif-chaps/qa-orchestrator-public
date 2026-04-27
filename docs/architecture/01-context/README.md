# Context View

## Project Presentation

<!-- AUTO-GENERATED from agent-os/product/mission.md -->

**ChapsMind** is an AI-powered market and economic intelligence platform that helps private companies and market intelligence professionals discover, monitor, and analyze information online. The platform provides modular, AI-first tools with intelligent guidance through **Chaps-e**, the integrated AI assistant.

### Vision

Reinvent market intelligence products with AI capabilities. ChapsMind represents a complete rebuild from scratch of traditional market intelligence tools, leveraging modern AI architecture (Dify) to deliver smarter, faster, and more actionable insights.

### Business Model

- **Single-auth multi-tenant application** with Keycloak Organizations for client isolation
- **Modular architecture**: Sell one or multiple modules to clients as independent products with synergies
- **Parent company**: Chapsvision (market intelligence software catalog)
- **Goal**: Rework existing Chapsvision products into ChapsMind modules

### Module Portfolio

| Module       | Purpose                                             | Status                                                 |
| ------------ | --------------------------------------------------- | ------------------------------------------------------ |
| **Screen**   | Get information about companies online              | Production (early stage)                               |
| **Target**   | Setup watchfiles on topics for automated monitoring | Q1 2026 early adopters, First Release by the end of Q2 |
| **Explore**  | Graph-based relationship discovery                  | Q4 2026                                                |
| **Stream**   | Intelligence distribution (newsletter, Slack, RSS)  | Future                                                 |
| **Discover** | Additional dashboard capabilities                   | Future                                                 |

### Key Differentiators

1. **AI-First Architecture**: Built from the ground up with AI at its core, using Dify for intelligent analysis and automated workflows
2. **Chaps-e AI Assistant**: Global conversational AI accessible from sidebar, adapts context to current page
3. **Chaps-e Smart Assist**: Goal-based smart action buttons computed from user's AI preferences and data context
4. **Modular Independence with Synergies**: Each module works standalone but gains power when combined
5. **Generic Platform, Specialized Guidance**: Industry-agnostic codebase with deep market intelligence knowledge in AI layers

---

## Roadmap Summary

The ChapsMind platform is evolving through several key phases. Detailed specifications for each feature are maintained in `agent-os/specs/`.

### Current Phase: Screen Production (Phase 1)

The Screen module is in production with first client access. Current focus areas include:

- Datacollector task architecture improvements
- Section-based workflow implementation
- Task status monitoring enhancements

### Key Milestones

#### Target Module Migration (Q1 2025)

Migrate the Target module functionality into the ChapsMind platform, enabling watchfile-based automated monitoring.

**Related specs:**

- [Global Token System](../../../agent-os/specs/2025-12-16-global-token-system/) - Centralized token management
- [Team Manager Role](../../../agent-os/specs/2025-12-18-team-manager-role/) - Enhanced role-based access

#### Global Service Extraction (Q2 2025)

Extract global mechanics (folders, tokens, organization settings) from the Screen FastAPI backend to a new FastAPI gRPC Python service as an API gateway.

**Related specs:**

- [Global Organization Service](../../../agent-os/specs/global-organization-service/) - Service extraction architecture
- [Private Folders Sharing](../../../agent-os/specs/private-folders-sharing/) - Folder system enhancements

#### Module Service Architecture (Q2 2025)

Each module's specific mechanics in dedicated services, enabling true multi-module architecture.

**Architectural pattern:**

- Global services handle cross-cutting concerns (identity, tokens, folders)
- Module services handle domain-specific logic (Screen, Target, Explore)
- API gateway pattern with gRPC + REST communication

### Ongoing Features

- [Company Card Translation](../../../agent-os/specs/2025-12-19-company-card-translation/) - i18n support
- [Vuellar UI Migration](../../../agent-os/specs/vuellar-ui-migration/) - Component library migration
- [Security Settings Keycloak Integration](../../../agent-os/specs/2026-01-08-security-settings-keycloak-integration/) - Enhanced security

### Future Phases

- **Phase 4**: Stream Module - Intelligence distribution
- **Phase 5**: Explore Module - Graph-based relationship discovery
- **Phase 6**: Discover Module - Advanced dashboard capabilities

---

## Related Documents

- [Stakeholders](./stakeholders.md) - Primary users and their needs
- [Constraints](./constraints.md) - Business, legal, and technical constraints
- [Product Mission](../../../agent-os/product/mission.md) - Full product mission document
- [Product Roadmap](../../../agent-os/product/roadmap.md) - Detailed phase breakdown
