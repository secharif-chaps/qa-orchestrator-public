---
name: jira-epic
description: >
  Creates Jira Epics for ChapsMind with phased delivery plan and story breakdown.
  Use when user mentions "epic", "roadmap", "vision", "initiative", or wants to
  group stories under a theme. Activates when discussing feature planning, multi-sprint
  scope, or story grouping for the TAR project.
  CRITICAL - Never create in Jira without explicit PO validation.
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Bash, Read, Grep
---

## When to use this skill

- When the user asks to create an Epic in Jira
- When discussing a roadmap initiative or multi-sprint feature
- When grouping multiple stories under a theme
- When the user says "epic", "vision", "initiative", "regroupement"
- When a feature spans multiple modules (multi-prefix like `[TARGET][SCREEN]`)
- When planning phased delivery (Design -> Foundation -> Back -> Front)

# Epic Owner - ChapsMind

**CRITICAL**: Never create in Jira without explicit PO validation ("oui"/"ok"/"go"/"valide").

## Workflow

1. **Clarify** scope if needed (modules, personas, phases)
2. **Present** complete proposal for PO validation BEFORE creating in Jira
3. **Create** in Jira ONLY after explicit validation

## Key Rules

1. Multi-modules OK for Epics (combined prefixes `[TARGET][SCREEN]`)
2. Child stories MUST be mono-module (1 component each)
3. Always define phases (Design -> Foundation -> Back -> Front)
4. Never estimate in story points
5. Legacy epics: no Design/Foundation phases, simplified structure

## Presentation Template

```markdown
## PROPOSITION EPIC

**Titre** : [MODULE] Titre propose
**Composant(s)** : [Deduit(s) du/des module(s)]
**Labels** : Claude [+ Back / Front / IA / DevOps / Data si applicable]

### Description
**Vision** : [Objectif et impact]
**Valeur metier** : [Benefice concret]
**Personas** : [Principal] / [Secondaires]

### Perimetre
**Inclus** : [liste]
**Exclus** : [liste + justification]

### Plan de Livraison
| # | Titre | Phase | Composant | Labels | Dependances |
|---|-------|-------|-----------|--------|-------------|
| 1 | [MODULE] Story 1 | Phase 0 | [Comp] | Front | - |
| 2 | [MODULE] Story 2 | Phase 1 | [Comp] | Back | Bloquee par #1 |

### Criteres d'Acceptation
- [ ] [Critere 1]
- [ ] Tests valides
- [ ] Documentation a jour

---
Valider cette proposition ? (oui / non / modifications)
```

## Examples

### Example 1 - WorldCheck Integration (multi-phase)

```
[SCREEN] WorldCheck One Integration (TAR-1108)

Phase 2 - Back:
  [SCREEN] Modele de donnees et migration WorldCheck (Back, 5pts)
  [SCREEN] Client API WorldCheck avec authentification HMAC-SHA256 (Back, 5pts)
  [SCREEN] Integration WorldCheck dans le workflow Dify data_collection (Back+IA, 8pts)
  [SCREEN] Propagation des donnees WorldCheck aux workflows (Back, 5pts)

Phase 3 - Front:
  [SCREEN] Frontend DataSourceCard WorldCheck (Front, 3pts)
```

### Example 2 - Migration ElasticSearch (5 phases techniques)

```
[TARGET] Migration ElasticSearch vers OpenSearch (TAR-652)

Phase 2 - Back (5 stories sequentielles):
  [TARGET] [Phase 1] Support OpenSearch cote application via composer patch (Back, 2pts)
  [TARGET] [Phase 2] Migration Docker et infrastructure vers OpenSearch (Back, 2pts)
  [TARGET] [Phase 3] Migration des index ES vers OpenSearch (Back, 1pt)
  [TARGET] [Phase 4] Mise a jour documentation projet (Back, 2pts)
  [TARGET] [Phase 5] Suivi PR upstream API Platform et cleanup (Back, 2pts)
```
