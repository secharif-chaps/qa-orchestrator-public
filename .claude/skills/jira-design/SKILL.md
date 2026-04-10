---
name: jira-design
description: >
  Creates Jira Design items for ChapsMind UX/UI work including Figma mockups, wireframes
  and UI specs. Use when user mentions "design", "maquette", "figma", "UX", "UI",
  "wireframe", "mockup", or "specs". Activates when planning UI deliverables, defining
  screen layouts, or tracking Figma work for the TAR project.
  CRITICAL - Design items always belong to Phase 0 of an Epic and block Phase 3 Front stories.
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Bash, Read, Grep
---

## When to use this skill

- When the user asks to create a Design item in Jira
- When planning Figma deliverables (mockups, wireframes, prototypes)
- When the user says "design", "maquette", "figma", "UX", "UI", "wireframe"
- When defining UI specifications or screen layouts
- When a Phase 0 deliverable is needed before Front development

# UX Designer - ChapsMind

**CRITICAL**: Design items always belong to Phase 0 of an Epic and block Phase 3 (Front) stories. Never create without PO validation.

## Key Rules

1. Title format: `[MODULE] Design - Description du livrable`
2. Always linked to a parent Epic
3. Auto-assign to current user (use `atlassianUserInfo` to get account ID)
4. Cover all UI states: Nominal, Empty, Loading, Error, Interactive
5. Responsive: Desktop (>=1280px), Tablet (768-1279px) if relevant. Mobile NOT supported.

## Deliverable Types

| Type | Usage |
|------|-------|
| Wireframe | Structure/layout validation |
| Mockup | Final visual validation |
| Prototype | Journey/interactions validation |
| Specs UI | Dimensions, spacing, behaviors |

## Presentation Template

```markdown
## PROPOSITION DESIGN

**Titre** : [MODULE] Design - Titre propose
**Composant** : [Deduit du module]
**Labels** : Claude, Front
**Type** : Wireframe / Mockup / Prototype / Specs UI
**Epic parente** : [Lien]

### Brief
**Probleme** : [Besoin UX/UI]
**Objectifs** : [liste]

### Perimetre
- [ ] [Ecran/Composant 1]
- [ ] [Ecran/Composant 2]
- Etats: Nominal, Vide, Loading, Erreur, Interactifs

### Livrables
- [ ] [Nom] - [Type]
- Figma: [lien projet]

### Criteres d'Acceptation
- [ ] Parcours fluide et intuitif
- [ ] Coherence avec les patterns existants
- [ ] Accessibilite (contraste, zones cliquables)
- [ ] Tous les etats couverts

### Dependances
- Ce Design bloque : [Stories Front]

---
Valider ce Design ? (oui / non / modifications)
```

## Examples

### Example 1 - DataSourceCard WorldCheck

```
[SCREEN] Design - DataSourceCard WorldCheck
Composant: Screen | Type: Mockup | Epic: TAR-1108

Brief: Concevoir la carte de visualisation des resultats WorldCheck
dans la fiche entreprise Screen.

Perimetre:
- Carte WorldCheck avec indicateur de risque (High/Medium/Low)
- Etat nominal (resultats WorldCheck presents)
- Etat vide (aucun resultat, pas encore scanne)
- Etat erreur (API WorldCheck indisponible)

Bloque: TAR-1239 (Frontend DataSourceCard WorldCheck)
```

### Example 2 - Conversation agent cancel/retry

```
[TARGET] Design - Bouton annuler et feedback timeout conversation agent
Composant: Target | Type: Specs UI | Epic: TAR-1096

Brief: Definir le comportement UI du bouton cancel pendant une conversation
Chaps-e et le feedback visuel lors d'un timeout n8n.

Perimetre:
- Bouton cancel pendant l'attente de reponse agent
- Message de timeout avec option retry
- Animation de transition entre etats
- Etats: attente, annulation en cours, timeout, retry

Bloque: TAR-1094 (Bouton annuler frontend)
```
