---
name: jira-story
description: >
  Creates Jira User Stories for ChapsMind with Given/When/Then acceptance criteria.
  Use when user mentions "story", "fonctionnalite", "feature", "user story", or
  describes a user need to implement. Activates when discussing new functionality,
  a specific deliverable for the TAR project, or splitting front/back work.
  CRITICAL - One story = one module. Never create multi-module stories.
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Bash, Read, Grep
---

## When to use this skill

- When the user asks to create a Story in Jira
- When describing a new feature or functionality to implement
- When the user says "story", "fonctionnalite", "feature", "besoin utilisateur"
- When splitting a feature into front/back stories
- When creating a Foundation story (architecture, base components)
- When creating a Legacy story (targeted fix on ST9_3 branch)

# Story Creator - ChapsMind

**CRITICAL**: One story = one module. Never create multi-module stories. If multi-module detected, propose separate linked stories.

## Workflow

1. **Analyze**: identify module, labels, epic, front/back split needed
2. **Present** complete proposal for PO validation BEFORE creating in Jira
3. **Create** in Jira ONLY after explicit validation

## Key Rules

1. Title format: `[MODULE] Description claire de la fonctionnalite`
2. Always include Given/When/Then acceptance criteria
3. Never estimate in story points
4. Never include technical details (PHP, Vue) - focus on user need
5. Legacy stories: simplified technical template, Back label by default

## Front/Back Split

```
SI travail Front ET Back significatif
ET developpables independamment
-> Proposer 2 Stories distinctes (avec validation PO)

Pattern:
1. [MODULE] API gestion des sources (Back)
2. [MODULE] Interface gestion des sources (Front) - bloquee par #1
```

## Presentation Template

```markdown
## PROPOSITION STORY

**Titre** : [MODULE] Titre propose
**Composant** : [Deduit du module]
**Labels** : Claude, Back / Front / IA / DevOps / Data
**Epic parente** : [Lien ou "Aucune"]

### User Story
**En tant que** [persona ChapsMind]
**Je veux** [action/capacite]
**Afin de** [valeur metier]

### Perimetre
**Inclus** : [liste]
**Exclus** : [hors scope]

### Criteres d'Acceptance
**Given** [contexte initial]
**When** [action utilisateur]
**Then** [resultat attendu]

### Dependances
- Bloquee par : [Stories]
- Bloque : [Stories]

---
Valider cette Story ? (oui / non / modifications)
```

## Examples

### Example 1 - Back story with IA label

```
[TARGET] Ajouter un tool permettant a Chaps-e de demarrer la collecte du WatchFile (TAR-1087)
Composant: Target | Labels: Back, IA | Epic: TAR-1127

En tant que utilisateur de Chaps-e
Je veux que l'assistant puisse demarrer automatiquement la collecte
Afin de ne pas avoir a quitter la conversation pour lancer manuellement la collecte

Given un WatchFile avec sujet de reference complet
When Chaps-e propose le demarrage de la collecte et l'utilisateur accepte
Then la collecte est lancee via le tool et l'utilisateur recoit une confirmation
```

### Example 2 - Front/Back split

```
Story 1: [TARGET] Endpoint POST /conversations/{id}/cancel (TAR-1092)
Composant: Target | Labels: Back | Epic: TAR-1096

Story 2: [TARGET] Bouton annuler et feedback timeout sur la conversation agent (TAR-1094)
Composant: Target | Labels: Front | Epic: TAR-1096
Dependance: Bloquee par TAR-1092
```
