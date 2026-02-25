# Product Roadmap

## Phase 0: Screen Module Foundation [COMPLETE]

1. [x] Authentication System — Keycloak integration with role-based permissions and organization-based multi-tenancy `M`
2. [x] Company Card Creation — Create company profiles with basic information and trigger initial data collection `S`
3. [x] Task System Foundation — Backend task management with status tracking for workflow execution `M`
4. [x] Dify Integration — Connect to Dify AI platform for intelligent data processing `M`
5. [x] Dify Workflow Integration — Orchestrate automated data collection workflows `M`
6. [x] Company List View — Browse and search all companies within an organization `S`
7. [x] Company Detail View — Display comprehensive company information organized by sections `M`
8. [x] Folder System — Organize companies into folders for better management `S`
9. [x] Team Management — Invite users, assign roles, manage organization members `M`
10. [x] Permission System — Granular permissions for company and organization actions `S`

## Phase 1: Screen Production & First Client [CURRENT]

11. [x] Production Deployment — Kubernetes deployment with first client access `L`
12. [ ] Datacollector Task Architecture — Dify datacollector runs first to gather raw data before specialized workflows `M`
13. [ ] Section-Based Workflows — Individual Dify workflows for jobs, products, team, CSR, and other company sections `L`
14. [ ] Task Status Improvements — Enhanced monitoring and error handling for multi-step workflow execution `S`
15. [ ] Company Data Refresh — Ability to re-run data collection tasks for updated information `S`
16. [ ] Dashboard Analytics — Organization-level statistics and recent activity overview `M`
17. [ ] Search Improvements — Advanced filtering and search capabilities across companies `S`

## Phase 2: Target Early Adopters & UI Migration [Q1 2025]

18. [ ] Vuellar UI Migration — Switch frontend to @owlint/feathers-vue component library `L`
19. [ ] Target Module Backend — Core services for watchfile management and monitoring `L`
20. [ ] Watchfile Configuration — UI for setting up automated topic monitoring `M`
21. [ ] Alert System — Notification infrastructure for monitoring events `M`
22. [ ] Target Early Adopter Integration — Merge Target functionality with Screen frontend `M`
23. [ ] Chaps-e Smart Assist v1 — Goal-based smart actions on company cards (Settings → AI Preferences) `L`
24. [ ] Chaps-e AI Chatbot — Conversational UI for querying data and watchfile setup `M`

## Phase 3: Unified Multi-Module Platform [Q2 2025]

25. [ ] Nuxt.js Migration — Migrate frontend from Vue 3 to Nuxt.js for SSR and improved architecture `XL`
26. [ ] Multi-Service Backend — Split backend into global services and module-specific services `XL`
27. [ ] Token Management Service — Centralized token handling across all modules `M`
28. [ ] User/Team/Organization Service — Global service for identity and access management `L`
29. [ ] Screen Core Service — Dedicated backend service for Screen module `L`
30. [ ] Target Core Service — Dedicated backend service for Target module `L`
31. [ ] Module Switcher UI — Interface for navigating between modules within unified frontend `M`
32. [ ] Cross-Module Data Sharing — Companies and entities accessible across modules with proper permissions `M`
33. [ ] Target Production Release — Full production deployment of Target module `L`

## Phase 4: Explore Module [Future]

34. [ ] Graph Database Integration — Infrastructure for relationship data storage `L`
35. [ ] Relationship Discovery Engine — Algorithms for identifying company connections `XL`
36. [ ] Graph Visualization — Interactive UI for exploring company networks `L`
37. [ ] Network Analysis Tools — Metrics and insights from relationship graphs `M`
38. [ ] Explore-Screen Integration — Link graph discoveries to Screen company profiles `M`

## Phase 5: Stream Module [Future]

39. [ ] Newsletter Generation Engine — Automated digest creation from intelligence data `L`
40. [ ] Slack Integration — Push intelligence updates to Slack channels `M`
41. [ ] RSS Feed Generation — Create RSS feeds from monitoring data `S`
42. [ ] Email Distribution — Scheduled email delivery of intelligence reports `M`
43. [ ] Feed Customization — User preferences for content filtering and formatting `M`

## Phase 6: Discover Module [Future]

44. [ ] Discovery Requirements — Define scope and capabilities for Discover module `M`
45. [ ] Discover Core Implementation — Build out discovery functionality `XL`
46. [ ] Module Integration — Connect Discover with existing modules `L`

---

> Notes
> - Effort scale: XS (1 day), S (2-3 days), M (1 week), L (2 weeks), XL (3+ weeks)
> - Each item represents a functional, testable feature
> - Order reflects technical dependencies and business priorities
> - Phases may overlap as different teams work in parallel
> - UI migration (Vuellar, then Nuxt.js) is strategic for long-term maintainability
