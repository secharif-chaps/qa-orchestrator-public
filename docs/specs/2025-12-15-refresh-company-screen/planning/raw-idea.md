# Raw Idea: Refresh Company Screen Feature

## User Description

**Feature Name**: Refresh Company Screen Feature

**Description**:
- When the owner of a company screen accesses their company screen, they should find a refresh button next to the export and delete action buttons in the company header
- Clicking this button opens a modal explaining that refreshing will consume 35 tokens (same as creating a new company)
- The refresh will override current company data with new data
- This feature is for when users want fresh data without duplicating the company
- Display current token count like on the create new company page
- Same logic to disable refresh if not enough tokens
- Refreshing should restart all tasks (data collection tasks first, then others)
- Task statuses should be set accordingly

## Context

**Roadmap Phase**: Phase 1: Screen Production & First Client [CURRENT]
**Related Roadmap Item**: #15 - Company Data Refresh — Ability to re-run data collection tasks for updated information

**Current State**:
- Companies can be created with initial data collection consuming 35 tokens
- Tasks are triggered upon company creation (datacollector first, then specialized workflows)
- Export and delete action buttons already exist in company header
- Token management system is in place for company creation

**Motivation**:
- Users need a way to update company information without creating duplicate entries
- Data becomes stale over time and needs to be refreshed
- Should maintain the same token consumption model as initial creation (35 tokens)
- Only company owners should have access to this functionality

## Spec Metadata

- **Date Created**: 2025-12-15
- **Spec Name**: refresh-company-screen
- **Status**: Raw Idea Captured
