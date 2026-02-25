# System Context

<!-- TODO: To be completed - detailed C4 Context diagram and description -->

This document provides the detailed system context view showing how ChapsMind interacts with external systems and actors.

## C4 Context Diagram

```mermaid
C4Context
    title ChapsMind System Context

    Person(analyst, "Intelligence Analyst", "Researches companies and markets")
    Person(bd, "BD Manager", "Tracks prospects and competitors")
    Person(exec, "Strategy Executive", "Makes strategic decisions")
    Person(admin, "Org Admin", "Manages organization users")

    Enterprise_Boundary(enterprise, "ChapsMind Ecosystem") {
        System(chapsmind, "ChapsMind Platform", "AI-powered market intelligence platform")
    }

    System_Ext(keycloak, "Keycloak SSO", "Authentication and authorization")
    System_Ext(dify, "Dify Platform", "LLM orchestration and AI workflows")
    System_Ext(pappers, "Pappers API", "French company data provider")
    System_Ext(web, "Public Web", "Company websites and public information")

    Rel(analyst, chapsmind, "Creates company cards, runs analysis")
    Rel(bd, chapsmind, "Monitors prospects")
    Rel(exec, chapsmind, "Views intelligence reports")
    Rel(admin, chapsmind, "Manages team members")

    Rel(chapsmind, keycloak, "OIDC authentication")
    Rel(chapsmind, dify, "AI workflows")
    Rel(chapsmind, pappers, "Company data")
    Rel(chapsmind, web, "Web scraping")
```

## External Systems

| System | Purpose | Integration |
|--------|---------|-------------|
| **Keycloak** | Identity management, SSO | OIDC protocol |
| **Dify** | AI/LLM workflow orchestration | REST API with callbacks |
| **Pappers** | French company data | REST API |
| **Public Web** | Company information | Web scraping |

## Related Documentation

- [Containers](./containers.md) - Internal container architecture
- [Auth Service](./modules/auth-service.md) - Keycloak integration details
- [AI Orchestration](./modules/ai-orchestration.md) - Dify integration
