---
name: jira-bug
description: >
  Creates Jira Bug reports for ChapsMind with reproduction steps, severity classification,
  and environment details. Use when user mentions "bug", "erreur", "probleme",
  "ne marche pas", "casse", or describes unexpected behavior. Activates when reporting
  defects, regressions, or production issues on the TAR project.
  CRITICAL - Module = where the bug is DETECTED, not where it is caused.
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Bash, Read, Grep
---

## When to use this skill

- When the user reports a bug or unexpected behavior
- When the user says "bug", "erreur", "probleme", "ne marche pas", "casse"
- When describing a regression after a deployment
- When sharing error logs or stack traces
- When reporting a Legacy (v9.3) production issue
- When triaging severity of a defect

# Bug Reporter - ChapsMind

**CRITICAL**: Module = where the bug is DETECTED (not where it is caused). Never create without PO validation.

## Key Rules

1. Title format: `[MODULE] Description courte et explicite du probleme`
2. Always include reproduction steps
3. Always include observed vs expected result
4. Never estimate in story points
5. Legacy bugs: label `Back` by default, targeted fixes only, no refactoring

## Severity Classification

| Level | Description | Example |
|-------|-------------|---------|
| **Critique** | System unusable, data loss, security | API token exposed |
| **Majeure** | Main feature broken | Search returns no results |
| **Mineure** | Secondary feature impacted | Filter doesn't reset pagination |
| **Cosmetique** | Display issue, no functional impact | Misaligned dropdown |

## Presentation Template

```markdown
## PROPOSITION BUG

**Titre** : [MODULE] Titre propose
**Composant** : [Deduit du module]
**Severite** : Critique / Majeure / Mineure / Cosmetique
**Labels** : Claude, Back / Front / Prompt

### Reproduction
**Preconditions** : [Etat initial]
1. [Etape 1]
2. [Etape 2]
3. [Etape declenchant le bug]

### Resultat Observe
[Ce qui se passe] - Message erreur: `[texte exact]`

### Resultat Attendu
[Ce qui devrait se passer]

### Environnement
| Element | Valeur |
|---------|--------|
| Env | DEV/STAGING/PROD |
| Navigateur | Chrome/Firefox + version |

### Impact
| Critere | Evaluation |
|---------|------------|
| Utilisateurs | Tous/Groupe/Isole |
| Workaround | [Solution contournement / Aucun] |

---
Valider ce Bug ? (oui / non / modifications)
```

## Examples

### Example 1 - Front bug (Screen)

```
[SCREEN] Tableau utilisateurs ne se rafraichit pas apres edition des permissions (TAR-1266)
Composant: Screen | Severite: Mineure | Labels: Front | Epic: TAR-951

Preconditions: Connecte en tant qu'admin sur la page Team Management
1. Modifier les permissions d'un utilisateur existant
2. Cliquer "Sauvegarder"
3. Observer le tableau

Observe: Le tableau affiche toujours les anciennes permissions
Attendu: Le tableau se rafraichit automatiquement avec les nouvelles permissions
```

### Example 2 - Security bug (Screen)

```
[SCREEN] Securite - Cle API SerpAPI exposee en clair (TAR-1204)
Composant: Screen | Severite: Critique | Labels: Back | Epic: TAR-1211

Observe: La cle API SerpAPI est visible en clair dans les logs Dify et dans la config du workflow
Attendu: La cle doit etre stockee dans les variables d'environnement et jamais loggee
Impact: Tous les environnements, risque d'abus de quota SerpAPI
```
