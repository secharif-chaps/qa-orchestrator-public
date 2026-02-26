# Spec Initialization

## Status
Requirements gathering in progress

## Created
2025-12-12

## Description

**Feature: Global Organization Service (Microservices Architecture)**

The user wants to externalize common components from the current monolithic backend into a dedicated global service to prepare for a multi-module architecture.

**Current State:**
- 1 frontend (Vue.js), 1 backend (FastAPI Python), 1 DB, Keycloak with organizations

**Target Architecture:**
- Keycloak (organizations) - already exists
- **Global/Common Service (NEW)** - Python FastAPI - this is what we're specifying
- Screen Service (refactored from current backend) - Python FastAPI
- Target Service (new) - PHP Symphony
- Future: Explore, Stream, Discover services
- Unified Vue.js frontend calling different service APIs

**Global Service Responsibilities:**
1. Organization management
2. User management
3. Module enablement per organization (which modules are active)
4. Token management (shared pool across all modules)
5. Folders (shared resource - a folder can contain items from multiple modules)

**Key Behaviors:**
- Frontend calls global service on startup to get org data (enabled modules, token balance)
- Module services (Screen, Target) call global service to:
  - Verify module is enabled for org
  - Check/deduct tokens before actions
- Folders managed in global service but reference item IDs from module services

**Token System Change:**
- Current: per-module token counts
- New: shared token pool per org (e.g., org has 4000 tokens, screen creation = 35 tokens, watchfile = 500 tokens)

**Migration Phases (iterative):**
- Phase 1: Organization + module config
- Phase 2: Tokens (unified pool)
- Phase 3: Folders
