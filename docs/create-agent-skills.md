# Creer et maintenir un Agent Skill

> **Projet** : ChapsMind (monorepo) — s'applique aussi aux repos partages (basil, etc.)
> **Spec de reference** : https://agentskills.io/specification
> **Best practices** : https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices

---

## 1. Qu'est-ce qu'un Agent Skill ?

Un Agent Skill est un **dossier contenant un fichier SKILL.md** qui enseigne a un agent IA (Claude Code, Copilot, Cursor, etc.) comment effectuer une tache specifique. C'est un standard ouvert adopte par 30+ plateformes.

**Pourquoi on les utilise :**

- Garantir la coherence du code genere (memes patterns, memes conventions)
- Capitaliser les decisions d'architecture (DDD, API Platform, Vuellar, etc.)
- Partager les standards entre equipes et projets (chapsmind, basil, etc.)
- Eviter de repeter les memes instructions dans chaque prompt

**Ce que ce n'est pas :**

- Un fichier de documentation pour humains (c'est pour l'agent)
- Un cours exhaustif sur une techno (Claude connait deja Symfony, Vue, etc.)
- Un remplacant de CLAUDE.md (qui contient les instructions globales du projet)

**Pourquoi suivre la spec agentskills.io :**

- **Cross-plateforme** : Un skill conforme fonctionne avec Claude Code, Copilot, Cursor, Windsurf et 30+ outils. Si on change d'agent, les skills suivent.
- **Progressive disclosure** : La spec impose 3 niveaux (description → body → references) pour optimiser les tokens de contexte au lieu de tout charger d'un coup.
- **Activation fiable** : Les contraintes sur `description` ("Use when...", 1024c max, 3eme personne) permettent a l'agent de declencher le bon skill au bon moment.
- **Marketplace** : skills.sh indexe 73K+ skills conformes. On peut installer des skills tiers et publier les notres.
- **Validation automatique** : `skills-ref validate` verifie la conformite - impossible sans format standardise.

Pour le detail complet, voir la spec agentskills.io et les best practices Anthropic linkees ci-dessus.

---

## 2. Prerequis

Avant de creer ou modifier un skill :

1. Lire la spec agentskills.io (10 min) : https://agentskills.io/specification
2. Lire les best practices Anthropic (15 min) : https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices
3. Parcourir 2-3 skills existants dans `.claude/skills/` pour comprendre le format
4. Avoir acces au repo chapsmind (ou basil pour les skills PHP/Symfony)

---

## 3. Nos conventions

### Emplacement

```
.claude/skills/{skill-name}/
    SKILL.md                    # Obligatoire - instructions principales
    references/                 # Optionnel - documentation detaillee
        examples.md
        patterns.md
```

Les skills vivent dans `.claude/skills/` (emplacement natif Claude Code, supporte par la spec agentskills.io). Ne PAS utiliser `.github/skills/` (on est sur GitLab).

### Nommage

| Regle                        | Exemple OK                                         | Exemple KO                      |
| ---------------------------- | -------------------------------------------------- | ------------------------------- |
| kebab-case uniquement        | `api-platform`                                     | `apiPlatform`, `API_Platform`   |
| Le dossier = le champ `name` | `pinia-colada/` + `name: pinia-colada`             | `pinia/` + `name: pinia-colada` |
| Max 64 caracteres            | `clean-architecture`                               | (rarement un probleme)          |
| Pas de tirets en debut/fin   | `git-commits`                                      | `-git-commits-`                 |
| Pas de tirets consecutifs    | `vue-components`                                   | `vue--components`               |
| Prefixe par domaine          | `backend-api`, `frontend-css`, `global-validation` | `api`, `css`, `validation`      |
| Noms descriptifs             | `testing-test-writing`                             | `utils`, `helper`, `tools`      |

### Deux types de skills

| Type                | Quand l'utiliser                                                  | Taille typique                        |
| ------------------- | ----------------------------------------------------------------- | ------------------------------------- |
| **Self-contained**  | Le sujet tient en < 300 lignes                                    | SKILL.md seul                         |
| **Avec references** | Le sujet necessite des exemples detailles ou des patterns avances | SKILL.md (< 300L) + `references/*.md` |

---

## 4. Creer un nouveau skill - 5 etapes

### Etape 1 - Identifier le besoin

Avant de creer un skill, repondre a ces 3 questions :

- [ ] **Aucun skill existant ne couvre le sujet ?** Parcourir `.claude/skills/`
- [ ] **Aucun skill marketplace ne fait mieux ?** Chercher sur https://skills.sh
- [ ] **Le sujet justifie un skill dedie ?** Si c'est 3 lignes de config, les ajouter a un skill existant

### Etape 2 - Creer la structure

```bash
mkdir -p .claude/skills/mon-skill/references
touch .claude/skills/mon-skill/SKILL.md
```

### Etape 3 - Rediger le SKILL.md

Utiliser le template ci-dessous (section 5). Points cles :

- **Description** : Phrase d'accroche + "Use when..." + "Activates when..." + "CRITICAL -" (si applicable)
- **Body** : Instructions concretes, pas de theorie. Claude est deja intelligent - ne lui expliquer que ce qu'il ne peut pas deviner
- **Exemples** : Minimum 2, copies depuis le code reel du projet (pas des exemples generiques)
- **References** : Deporter les details dans `references/` si le body depasse 300 lignes

### Etape 4 - Valider

```bash
# Validation automatique
npx skills-ref validate .claude/skills/mon-skill/

# Checklist manuelle (voir section 8)
```

### Etape 5 - Tester

1. Ouvrir Claude Code dans le projet
2. Donner une tache couverte par le skill
3. Verifier que le skill est active automatiquement (visible dans le contexte)
4. Verifier que les instructions sont suivies correctement
5. Tester avec un cas limite / edge case
6. Bonus : tester avec un autre modele (Haiku, Sonnet) si le skill est critique

---

## 5. Template SKILL.md

````yaml
---
name: skill-name
description: >
  Action-oriented description in third person. Covers X, Y, Z.
  Use when [trigger conditions]. Activates when [file patterns or contexts].
  CRITICAL - [most important rule if any].
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: php|vue|infra|global
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
---

## When to use this skill

- When [specific trigger 1]
- When [specific trigger 2]
- When working on files in `path/to/relevant/files`

# Skill Title

**CRITICAL**: [Most important rule, repeated for emphasis]

## Key Rules

1. Rule 1
2. Rule 2

## Patterns

### Pattern Name

```language
// Code example from real project
````

## Examples

### Example 1 - [Scenario]

```language
// Real code from the project
```

### Example 2 - [Scenario]

```language
// Real code from the project
```

## References

For detailed implementation patterns, see:

- [Topic A](references/topic-a.md)
- [Topic B](references/topic-b.md)

```

### Champs du frontmatter YAML

| Champ | Obligatoire | Contraintes | Notes |
|-------|------------|-------------|-------|
| `name` | **Oui** | Max 64c, kebab-case, = nom du dossier | Pas de mots reserves ("anthropic", "claude") |
| `description` | **Oui** | Max 1024c, 3eme personne, pas de XML | Inclure "Use when..." et "Activates when..." |
| `allowed-tools` | **Oui** (convention) | Liste separee par virgules | Restreindre au necessaire |
| `license` | Non | Nom de licence | `MIT` par defaut chez nous |
| `metadata` | Non | Cles/valeurs string | `author: <utilisateur-courant>`, `version: "1.0"` |
| `compatibility` | Non | Max 500c | Environnement requis |

---

## 6. Bonnes pratiques

### Progressive disclosure (3 niveaux)

| Niveau | Charge quand | Budget | Contenu |
|--------|-------------|--------|---------|
| **Description** (frontmatter) | Au demarrage, pour TOUS les skills | < 200 tokens (~800c) | Quoi + quand + critere d'activation |
| **Body** (SKILL.md) | Quand le skill est active | < 5000 tokens (~400L) | Instructions essentielles, regles, exemples cles |
| **References** (`references/`) | Sur demande | Illimite | Details, exemples exhaustifs, patterns avances |

### Ecrire une bonne description

La description est le critere d'activation principal. L'agent lit TOUTES les descriptions au demarrage pour decider quel skill activer.

**Structure recommandee :**
```

[Ce que fait le skill]. Use when [conditions de declenchement].
Activates when [patterns de fichiers ou contextes].
CRITICAL - [regle la plus importante].

```

**Exemple reel (pinia-colada) :**
```

Data fetching with Pinia Colada queries and mutations for Vue/Nuxt
applications. Use when fetching data from the API, creating query
definitions with defineQueryOptions, implementing mutations with
useMutation, handling loading/error/empty states, managing server-side
cache invalidation, or implementing optimistic updates. Activates when
working on files in src/queries/, src/mutations/, or any Vue
component that needs to fetch or mutate API data. CRITICAL - Never call
API functions directly in components; always use queries and mutations.

````

### Regles d'ecriture du body

1. **Concision** : Chaque token compete avec l'historique de conversation. Aller droit au but.
2. **Un niveau de profondeur** : SKILL.md -> references/file.md. Jamais references/a.md -> references/b.md.
3. **Exemples du projet** : Utiliser du code reel du projet, pas des exemples generiques.
4. **Pas de dates** : "Pattern actuel" au lieu de "Depuis la v2.0 (jan 2026)".
5. **Terminologie fixe** : Choisir un terme et s'y tenir (ex: toujours "Gateway", jamais "Repository").
6. **Feedback loops** : Pour les taches critiques, inclure une etape de validation (lint, test, type-check).
7. **Allowed-tools restreints** : Ne donner que les outils necessaires (Read, Grep pour review ; + Write, Edit pour generation).

---

## 7. Maintenir un skill existant

| Quand modifier | Action |
|---------------|--------|
| Upgrade de version (ex: Symfony 7.3 -> 7.4) | Mettre a jour versions + patterns obsoletes |
| Nouveau pattern decouvert | Ajouter dans `references/` (pas dans SKILL.md sauf si essentiel) |
| Pattern obsolete | Supprimer (pas de "deprecated since..." ni "anciennement...") |
| Skill trop gros (> 500L) | Extraire vers `references/` |
| Feedback negatif recurrent | Reformuler les instructions, ajouter exemples |
| Skill tiers meilleur disponible | Evaluer : remplacer ou completer |

### Ne jamais modifier

- Le champ `name` (casserait les references existantes)
- Les descriptions pour ajouter du contenu temporel ("depuis fevrier 2026...")

---

## 8. Checklist qualite (avant merge)

### Frontmatter

- [ ] `name` match le dossier, kebab-case, < 64 caracteres
- [ ] `description` < 1024 caracteres, 3eme personne
- [ ] `description` inclut "Use when..." ET "Activates when..."
- [ ] `allowed-tools` present et restreint au necessaire
- [ ] `metadata.author` = `owlint` (ou nom de l'auteur), `metadata.version` renseignee

### Body

- [ ] < 500 lignes
- [ ] Section "## When to use this skill" presente
- [ ] Minimum 2 exemples pratiques (code reel du projet)
- [ ] Pas de theorie que Claude connait deja
- [ ] Terminologie coherente dans tout le skill

### Structure

- [ ] Fichiers supplementaires dans `references/` (pas a la racine du skill)
- [ ] Pas de chemins relatifs hors du dossier skill (`../../../` interdit)
- [ ] Liens internes vers `references/` corrects
- [ ] Pas d'information temporelle

### Test

- [ ] Teste avec une tache reelle dans Claude Code
- [ ] Le skill s'active au bon moment (pas de faux positif ni negatif)
- [ ] Les instructions sont suivies correctement

---

## 9. Erreurs courantes a eviter

Ces anti-patterns ont ete identifies lors de notre audit :

### 1. Le "thin wrapper" vide

```markdown
# Bad - un SKILL.md qui ne fait que pointer ailleurs
## Instructions
For details, refer to the information provided in this file:
[backend models](../../../agent-os/standards/backend/models.md)
````

Le fichier externe contient 10 lignes de bullet points. Resultat : le skill ne fournit quasiment rien a l'agent.

**Correction** : Integrer le contenu directement dans le SKILL.md. Si le contenu externe est > 100 lignes, le copier dans `references/`.

### 2. Le `allowed-tools` manquant

```yaml
---
name: symfony-cache
description: Implement caching with Symfony Cache...
---
```

Sans `allowed-tools`, l'agent ne sait pas quels outils il peut utiliser avec ce skill.

**Correction** : Toujours inclure `allowed-tools` dans le frontmatter.

### 3. La description trop courte

```yaml
description: Git workflow with gitmoji commits and feature branches.
```

175 caracteres. L'agent n'a pas assez de contexte pour savoir QUAND activer ce skill.

**Correction** : Inclure les triggers ("Use when..."), les patterns de fichiers ("Activates when..."), et la regle critique ("CRITICAL -").

### 4. L'absence de "When to use this skill"

Un SKILL.md qui commence directement par les instructions sans section d'activation. L'agent comprend moins bien quand l'utiliser.

**Correction** : Toujours commencer par `## When to use this skill` avec 5-10 bullet points.

### 5. Les fichiers de support a la racine

```
mon-skill/
    SKILL.md
    examples.md        # A la racine, pas dans references/
    patterns.md
```

Ne suit pas la convention `references/` de la spec, rend la structure moins lisible.

**Correction** :

```
mon-skill/
    SKILL.md
    references/
        examples.md
        patterns.md
```

### 6. Les exemples generiques

```php
// Bad - exemple generique
class MyEntity {
    private string $name;
}
```

**Correction** : Utiliser du code reel du projet.

```php
// Good - exemple reel du projet
#[ORM\Entity]
#[ApiResource(
    operations: [new Get(), new GetCollection()],
    security: "is_granted('VIEW', object)"
)]
class WatchFile {
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
}
```

---

## 10. Exemple complet : le skill `pinia-colada`

### Structure

```
.claude/skills/pinia-colada/
    SKILL.md                     # 264 lignes
    references/
        data-fetching.md         # 369 lignes - architecture et patterns
        examples.md              # 633 lignes - exemples detailles
```

### SKILL.md (extrait)

```yaml
---
name: pinia-colada
description: Data fetching with Pinia Colada queries and mutations for Vue/Nuxt
  applications. Use when fetching data from the API, creating query definitions with
  defineQueryOptions, implementing mutations with useMutation, handling
  loading/error/empty states, managing server-side cache invalidation, or implementing
  optimistic updates. Activates when working on files in src/queries/,
  src/mutations/, or any Vue component that needs to fetch or mutate API data.
  CRITICAL - Never call API functions directly in components; always use queries and
  mutations.
allowed-tools: Read, Write, Edit, Glob, Grep
---

## When to use this skill

- When fetching data from the API in Vue components
- When creating query definitions in `src/queries/`
- When creating mutation definitions in `src/mutations/`
- When implementing `useQuery` or `useMutation` hooks
- When handling loading, error, and empty states in templates
- When invalidating cache after successful mutations
- When implementing optimistic updates for better UX

# Data Fetching with Pinia Colada

**CRITICAL**: Never call API functions directly in components. Always use
queries and mutations.

## Architecture (3 couches)

[... instructions essentielles ...]

## References

- [data-fetching.md](references/data-fetching.md) - Architecture and patterns
- [examples.md](references/examples.md) - Pagination, optimistic UI, cache
```

### Pourquoi ce skill fonctionne bien

1. **Description riche** (552c) avec triggers clairs et CRITICAL rule
2. **"When to use"** avec 7 scenarios concrets + paths de fichiers
3. **Body < 300 lignes** avec les regles essentielles
4. **references/** pour les 1000+ lignes de detail
5. **Exemples reels** du projet (pas de code generique)
6. **Terminologie coherente** : toujours "query", "mutation", "cache invalidation"
