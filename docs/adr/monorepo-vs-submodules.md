# ADR: Monorepo vs Git Submodules

**Status**: Proposed
**Date**: 2026-02-25
**Author**: Engineering Team
**Context**: ChapsMind workspace architecture

---

## 1. Contexte et Probleme

Le projet ChapsMind est actuellement organise en **4 sous-modules git** (bientot 6) au sein d'un repository workspace parent :

| Sous-module | Stack | Commits | Branches actives |
|---|---|---|---|
| `front` | Vue 3 / TypeScript | 641 | 22 |
| `back` | FastAPI / Python | 525 | 23 |
| `infra` | Docker / K8s | 56 | 11 |
| `global-service` | Python | 3 | 3 |
| *(prevu)* `target-service` | Python | - | - |
| *(prevu)* `screen-service` | Python | - | - |

**Organisation**: 2 equipes feature-oriented qui touchent a l'ensemble des repos.

### Problemes constates

1. **47% des commits du workspace sont du bruit de maintenance** (35/74 commits = "update submodule pointers")
2. **3 sous-modules sur 4 sont desynchronises** au moment de l'audit (refs en avance par rapport au workspace parent)
3. **Aucun git-flow defini** — pas de convention partagee entre les equipes
4. **Les features cross-stack necessitent 3 MRs** (front + back + workspace bump)
5. **16 scopes de features sont partages entre front et back** (admin, companies, folders, tasks, team, translation, tokens, etc.)

---

## 2. Options Evaluees

### Option A : Rester en Submodules (avec ameliorations)
### Option B : Monorepo
### Option C : Multi-repo pur (sans workspace parent)

---

## 3. Matrice de Decision

### 3.1. Criteres de Developer Experience (DX)

| Critere                             | Poids  | Submodules (A)                                   | Monorepo (B)                                           | Multi-repo (C)                     |
|-------------------------------------|--------|--------------------------------------------------|--------------------------------------------------------|------------------------------------|
| **Onboarding nouveau dev**          | Eleve  | 2/5 — `git clone --recurse`, comprendre les refs | **5/5** — un seul `git clone`                          | 3/5 — cloner N repos               |
| **Feature cross-stack (1 MR)**      | Eleve  | 1/5 — 3 MRs minimum (front + back + bump)        | **5/5** — 1 MR, 1 branche                              | 1/5 — N MRs                        |
| **Review unifiee**                  | Eleve  | 1/5 — reviewer dans 3 repos separement           | **5/5** — 1 MR, diff complet                           | 1/5 — context splitte              |
| **Pas de maintenance de refs**      | Eleve  | 1/5 — 47% de commits = maintenance               | **5/5** — elimine le probleme                          | 4/5 — pas de refs mais pas de lien |
| **Historique / git blame / bisect** | Moyen  | 2/5 — fragmente par repo                         | **5/5** — unifie                                       | 1/5 — fragmente                    |
| **Recherche de code cross-repo**    | Moyen  | 2/5 — grep dans chaque submodule                 | **5/5** — grep global                                  | 1/5 — outils externes              |
| **Complexite CI**                   | Moyen  | 3/5 — CI par repo, simple                        | 4/5 — `include:local` par app, separation des concerns | **4/5** — CI independantes         |
| **Autonomie de deploiement**        | Moyen  | 4/5 — deploy independant par repo                | 3/5 — conditionnel mais possible                       | **5/5** — naturel                  |
| **Scalabilite (50+ services)**      | Faible | 3/5 — chaque repo est petit                      | 3/5 — clone grossit                                    | **5/5** — chaque repo reste petit  |
| **Refactoring cross-stack**         | Eleve  | 1/5 — coordination manuelle                      | **5/5** — atomique                                     | 1/5 — coordination manuelle        |

### 3.2. Score pondere

|                             | Submodules (A) | Monorepo (B) | Multi-repo (C) |
|-----------------------------|----------------|--------------|----------------|
| **Score brut** (somme)      | 20             | **46**       | 26             |
| **Score pondere** (x poids) | 28             | **67**       | 35             |

> **Le monorepo domine sur tous les criteres a poids eleve**, qui correspondent exactement aux douleurs actuelles.

### 3.3. Criteres eliminatoires

| Critere eliminatoire                       | Submodules          | Monorepo                  | Multi-repo          |
|--------------------------------------------|---------------------|---------------------------|---------------------|
| Equipes feature touchent plusieurs repos ? | Oui → friction      | **Oui → naturel**         | Oui → friction      |
| Taille du repo bloquerait le clone ?       | Non                 | **Non (< 50 Mo)**         | Non                 |
| CI GitLab supporte le conditionnel ?       | N/A                 | **Oui (`rules:changes`)** | N/A                 |
| Plus de 2 services ajoutés bientot ?       | Aggrave le probleme | **Absorbe naturellement** | Multiplie les repos |

---

## 4. Analyse du Git Flow

### 4.1. Situation actuelle : pas de convention

Observations :
- Des branches `feat/*`, `fix/*` existent dans front et back mais sans convention commune
- Les branches ne portent pas le meme nom entre front et back pour la meme feature
- Branches mortes : `develop`, `pipeline`, `origin` trainent dans les deux repos
- Pas de protection de branche documentee
- Pas de CODEOWNERS

### 4.2. Git Flow propose (avec Monorepo)

#### Branches et environnements

```
main (protegee)                    ← Merge auto → deploy integration
  │
  ├── feat/TAR-xxx-description     ← Feature branch (front + back + infra)
  ├── fix/TAR-xxx-description      ← Bug fix
  ├── refactor/description         ← Refactoring
  └── chore/description            ← Maintenance

release/preprod                    ← Tag/branche → deploy preprod (validation metier)
release/prod                       ← Tag/branche → deploy prod (promotion manuelle)
```

#### Pipeline de promotion des environnements

```
Feature branch        main               preprod             prod
     │                  │                    │                  │
     │   MR + review    │                    │                  │
     ├─────────────────→│                    │                  │
     │                  │  deploy auto       │                  │
     │                  ├───────────────────→│                  │
     │                  │                    │  promotion       │
     │                  │                    │  manuelle        │
     │                  │                    ├─────────────────→│
     │                  │                    │                  │
   CI: lint           CI: build +         CI: build +        CI: build +
   + test             tag images          tag preprod        tag prod
   + build            + deploy integ      + deploy preprod   + deploy prod
```

| Environnement    | Branche/Trigger            | Deploy           | Objectif                         |
|------------------|----------------------------|------------------|----------------------------------|
| **Integration**  | `main` (auto sur merge)    | Automatique (CD) | Validation technique continue    |
| **Preprod**      | Tag ou branche `release/*` | Automatique (CD) | Validation metier, recette       |
| **Prod**         | Promotion manuelle         | Manuel (bouton)  | Production, zero downtime        |

**Objectif CD** : a terme, le merge sur `main` declenche la chaine complete jusqu'a la prod, avec des gates de validation (tests E2E, smoke tests) entre chaque etape.

#### Regles

| Regle                          | Description                                                                   |
|--------------------------------|-------------------------------------------------------------------------------|
| **Branche unique par feature** | `feat/TAR-xxx-description` touche `apps/front/` ET `apps/back/` si necessaire |
| **MR unique**                  | 1 feature = 1 MR, meme si elle touche 3 apps                                  |
| **Prefixe ticket**             | Toujours inclure le numero Jira/GitLab (`TAR-xxx`)                            |
| **Pas de commit sur main**     | Tout passe par MR                                                             |
| **Review CODEOWNERS**          | Approval automatique requise par domaine touche                               |
| **CI verte obligatoire**       | Merge bloque si pipeline echoue                                               |
| **Squash merge**               | Historique propre sur main                                                    |
| **main = toujours deployable** | Chaque commit sur main doit pouvoir aller en prod                             |

### 4.3. Comparaison du flow par approche

#### Feature cross-stack : "Ajouter le module traduction"

**Submodules (actuel)** :
```
1. git checkout -b feat/translation        ← dans front/
2. ... dev + commit + push + MR front
3. git checkout -b feat/translation        ← dans back/
4. ... dev + commit + push + MR back
5. Attendre merge front
6. Attendre merge back
7. cd workspace/
8. git submodule update
9. git add front back
10. git commit -m "bump submodules"
11. git push + MR workspace
12. Merge workspace

→ 3 MRs, 3 reviews, 12 etapes, coordination manuelle
→ Risque: quelqu'un merge entre-temps et casse les refs
```

**Monorepo (propose)** :
```
1. git checkout -b feat/TAR-42-translation
2. ... modifier apps/front/ + apps/back/
3. git add + commit + push
4. MR unique avec diff complet
5. Review: front team + back team (via CODEOWNERS)
6. Merge

→ 1 MR, 1 review, 6 etapes, atomique
→ Pas de risque de desynchronisation
```

**Gain** : 50% d'etapes en moins, zero risque de refs cassees, review complete.

#### Hotfix urgent : "Bug en prod sur l'API"

**Submodules** :
```
1. cd back/ && git checkout -b fix/api-bug
2. Fix + commit + push + MR
3. Attendre review + merge
4. cd workspace/ && git submodule update
5. Bump + push + MR workspace
6. Attendre merge workspace
7. Deploy

→ Latence: 2 cycles de review
```

**Monorepo** :
```
1. git checkout -b fix/TAR-99-api-bug
2. Fix dans apps/back/ + commit + push + MR
3. Review + merge
4. Deploy

→ Latence: 1 cycle de review
```

**Gain** : Hotfix 2x plus rapide en production.

---

## 5. Impact sur la CI/CD

### 5.1. CI actuelle

Les CI front et back sont quasi identiques : build Docker + push sur main.
Pas de tests, pas de lint, pas de type-check en CI.

### 5.2. Architecture CI distribuee (`include:local`)

Chaque app possede son propre `.gitlab-ci.yml` avec sa logique metier. Le fichier racine orchestre via `include:local`. Cela isole les concerns : chaque equipe maintient sa CI independamment.

#### Structure des fichiers

```
chapsmind/
├── .gitlab-ci.yml                      ← Orchestrateur : stages + includes
├── apps/
│   ├── front/.gitlab-ci.yml            ← CI front (lint, typecheck, build, deploy)
│   ├── back/.gitlab-ci.yml             ← CI back (lint, test, build, deploy)
│   ├── global-service/.gitlab-ci.yml   ← CI global-service
│   └── ...
└── infra/.gitlab-ci.yml                ← CI infra (validation)
```

#### Fichier racine : `.gitlab-ci.yml`

```yaml
stages:
  - lint
  - test
  - build
  - deploy:integration
  - deploy:preprod
  - deploy:prod

include:
  - local: apps/front/.gitlab-ci.yml
  - local: apps/back/.gitlab-ci.yml
  - local: apps/global-service/.gitlab-ci.yml
  - local: infra/.gitlab-ci.yml
```

> Ajouter un nouveau service = ajouter une ligne `include` + creer le fichier CI dans le dossier du service.

#### Fichier app : `apps/front/.gitlab-ci.yml`

```yaml
# ─── Frontend CI ───────────────────────────────────
# Maintenu par l'equipe front, isole du reste

.front-changes: &front-changes
  changes:
    - apps/front/**/*

front:lint:
  stage: lint
  rules:
    - <<: *front-changes
  script:
    - cd apps/front && pnpm install --frozen-lockfile && pnpm lint

front:typecheck:
  stage: lint
  rules:
    - <<: *front-changes
  script:
    - cd apps/front && pnpm install --frozen-lockfile && pnpm vue-tsc --noEmit

front:build:
  stage: build
  rules:
    - <<: *front-changes
  image: docker:29-cli
  script:
    - docker build -t $CI_REGISTRY_IMAGE/mint-front:$CI_COMMIT_SHORT_SHA apps/front/
    - docker push $CI_REGISTRY_IMAGE/mint-front:$CI_COMMIT_SHORT_SHA

# ─── Deployment stages ─────────────────────────────
front:deploy:integration:
  stage: deploy:integration
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
      <<: *front-changes
  environment:
    name: integration
  script:
    - docker tag $CI_REGISTRY_IMAGE/mint-front:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/mint-front:integration
    - docker push $CI_REGISTRY_IMAGE/mint-front:integration
    # kubectl set image ... or ArgoCD sync

front:deploy:preprod:
  stage: deploy:preprod
  rules:
    - if: $CI_COMMIT_TAG =~ /^v.*/
      <<: *front-changes
  environment:
    name: preprod
  script:
    - docker tag $CI_REGISTRY_IMAGE/mint-front:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/mint-front:preprod
    - docker push $CI_REGISTRY_IMAGE/mint-front:preprod

front:deploy:prod:
  stage: deploy:prod
  rules:
    - if: $CI_COMMIT_TAG =~ /^v.*/
      <<: *front-changes
  environment:
    name: production
  when: manual  # Gate manuelle → promotion explicite
  script:
    - docker tag $CI_REGISTRY_IMAGE/mint-front:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/mint-front:prod
    - docker push $CI_REGISTRY_IMAGE/mint-front:prod
```

#### Fichier app : `apps/back/.gitlab-ci.yml`

```yaml
# ─── Backend CI ────────────────────────────────────
# Maintenu par l'equipe back, isole du reste

.back-changes: &back-changes
  changes:
    - apps/back/**/*

back:lint:
  stage: lint
  rules:
    - <<: *back-changes
  script:
    - cd apps/back && ruff check .

back:test:
  stage: test
  rules:
    - <<: *back-changes
  script:
    - cd apps/back && pytest

back:build:
  stage: build
  rules:
    - <<: *back-changes
  image: docker:29-cli
  script:
    - docker build -t $CI_REGISTRY_IMAGE/mint-back:$CI_COMMIT_SHORT_SHA apps/back/
    - docker push $CI_REGISTRY_IMAGE/mint-back:$CI_COMMIT_SHORT_SHA

back:deploy:integration:
  stage: deploy:integration
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
      <<: *back-changes
  environment:
    name: integration
  script:
    - docker tag $CI_REGISTRY_IMAGE/mint-back:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/mint-back:integration
    - docker push $CI_REGISTRY_IMAGE/mint-back:integration

back:deploy:preprod:
  stage: deploy:preprod
  rules:
    - if: $CI_COMMIT_TAG =~ /^v.*/
      <<: *back-changes
  environment:
    name: preprod
  script:
    - docker tag $CI_REGISTRY_IMAGE/mint-back:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/mint-back:preprod
    - docker push $CI_REGISTRY_IMAGE/mint-back:preprod

back:deploy:prod:
  stage: deploy:prod
  rules:
    - if: $CI_COMMIT_TAG =~ /^v.*/
      <<: *back-changes
  environment:
    name: production
  when: manual
  script:
    - docker tag $CI_REGISTRY_IMAGE/mint-back:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/mint-back:prod
    - docker push $CI_REGISTRY_IMAGE/mint-back:prod
```

### 5.3. Avantages de l'architecture CI distribuee

| Avantage                           | Detail                                                                 |
|------------------------------------|------------------------------------------------------------------------|
| **Separation des concerns**        | Chaque equipe maintient sa CI dans son dossier                         |
| **Pas de fichier CI monolithique** | Le `.gitlab-ci.yml` racine reste < 15 lignes                           |
| **Ajout de service trivial**       | 1 ligne `include` + 1 fichier `.gitlab-ci.yml` dans le nouveau dossier |
| **Execution conditionnelle**       | Seuls les jobs des dossiers modifies s'executent (`rules:changes`)     |
| **3 environnements**               | integration (auto), preprod (auto sur tag), prod (promotion manuelle)  |
| **Chemin vers le CD complet**      | Remplacer `when: manual` par des gates automatiques (smoke tests, E2E) |

---

## 6. Review automatique par domaine

### Le besoin

En monorepo, une MR peut toucher `apps/front/` ET `apps/back/`. Il faut un mecanisme pour **assigner automatiquement les bons reviewers** selon les fichiers modifies, sans que l'auteur ait a y penser.

### Solution native GitLab : CODEOWNERS (Premium uniquement)

GitLab propose nativement le fichier `CODEOWNERS` qui associe des chemins a des owners et bloque le merge sans leur approbation. **Cette fonctionnalite requiert GitLab Premium** — non disponible dans notre cas.

### Alternative retenue : auto-assign reviewers via CI

GitLab met a disposition un utilitaire officiel [`codeowners-as-reviewers`](https://gitlab.com/gitlab-org/professional-services-automation/tools/utilities/codeowners-as-reviewers) qui reproduit le comportement CODEOWNERS sur GitLab Free :

1. On definit un fichier `CODEOWNERS` dans le repo (meme syntaxe que Premium)
2. Un job CI se declenche a chaque MR
3. Le job analyse les fichiers modifies, match les owners, et **assigne les reviewers via l'API GitLab**

#### Fichier `CODEOWNERS`

```
# .gitlab/CODEOWNERS
# Meme syntaxe que GitLab Premium — utilise par le job CI

# Frontend
apps/front/              @chapsmind/team-front

# Backend
apps/back/               @chapsmind/team-back

# Services
apps/global-service/     @chapsmind/team-back
apps/target-service/     @chapsmind/team-back
apps/screen-service/     @chapsmind/team-back

# Infrastructure
infra/                   @chapsmind/team-devops
```

#### Job CI d'auto-assignation

```yaml
# Dans .gitlab-ci.yml racine (ou un include dedie)

assign-reviewers:
  stage: lint
  image: python:3.11-slim
  rules:
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"
  script:
    - pip install requests
    - python scripts/ci/assign_reviewers.py
  variables:
    GITLAB_TOKEN: $REVIEWER_BOT_TOKEN  # Token d'un bot avec acces API
```

Le script parse le fichier `CODEOWNERS`, recupere les fichiers modifies via l'API MR, et appelle `PUT /projects/:id/merge_requests/:iid` pour ajouter les `reviewer_ids`.

> Alternatives possibles :
> - [Axolo](https://axolo.co/auto-assign-reviewer-for-gitlab) — SaaS gratuit qui lit CODEOWNERS et assigne les reviewers automatiquement via Slack
> - Script bash maison avec `curl` + API GitLab (approche decrite dans [cet article Medium](https://medium.com/@prajeethspt/automated-reviewer-assignment-for-gitlab-merge-requests-5b903b87aa86))

#### Exemple concret

Une MR `feat/TAR-42-translation` modifie :
- `apps/front/src/components/TranslationPanel.vue`
- `apps/back/app/api/translation.py`

Le job CI detecte que `apps/front/` et `apps/back/` sont touches → assigne automatiquement `@chapsmind/team-front` et `@chapsmind/team-back` comme reviewers sur la MR.

#### Limites par rapport a GitLab Premium

| Fonctionnalite | GitLab Premium | Alternative CI |
|---|---|---|
| Assignation auto des reviewers | Natif | Via job CI ou Axolo |
| **Blocage du merge** sans approval owner | Natif | Non — convention d'equipe uniquement |
| Configuration | Fichier CODEOWNERS seul | CODEOWNERS + job CI + token bot |

Le blocage du merge n'est pas possible sans Premium. On compense par une **convention d'equipe** : ne pas merger tant que tous les reviewers assignes n'ont pas approuve. A terme, si l'equipe passe a GitLab Premium, le fichier CODEOWNERS est deja en place — il suffit d'activer la feature.

---

## 7. Outillage monorepo (Nx, Turborepo, Bazel) : pas necessaire aujourd'hui

### Pourquoi on n'en a pas besoin

Les outils de monorepo (Nx, Turborepo, Bazel, Pants) resolvent trois problemes principaux : le **dependency graph** entre packages partages, le **task caching** intelligent, et la **detection des projets affectes** par un changement.

Dans notre cas, ces trois problemes sont deja couverts ou inexistants :

| Probleme                    | Notre situation                                          | Couvert par                                |
|-----------------------------|----------------------------------------------------------|--------------------------------------------|
| Dependency graph inter-apps | Aucun code partage entre front (Vue/TS) et back (Python) | N/A                                        |
| Task caching                | Chaque app = `docker build`                              | Docker layer cache                         |
| Affected detection          | Quel service rebuilder ?                                 | GitLab CI `rules:changes` (natif, gratuit) |
| Build performance           | < 50 Mo de code, 5-6 services                            | Pas de probleme de performance             |

Ajouter Nx impliquerait d'installer Node.js comme prerequis pour les devs Python (juste pour faire tourner Nx), d'ecrire des plugins custom pour FastAPI, et de maintenir une configuration supplementaire — le tout sans gain mesurable.

Turborepo est exclusivement JS/TS, donc incompatible avec nos backends Python. Bazel resout des problemes de scale (builds hermetiques, distributed execution) qui ne se posent pas a notre echelle.

### Quand reevaluer

Reconsiderer l'ajout d'un outil si l'un de ces signaux apparait :

| Signal                                                                               | Outil a evaluer                            |
|--------------------------------------------------------------------------------------|--------------------------------------------|
| Creation de **libs partagees entre services Python** (schemas Pydantic, SDK interne) | Nx avec plugin Python, ou Pants            |
| **20+ services** et CI > 15 min                                                      | Nx pour le remote caching + affected graph |
| Besoin de **builds hermetiques reproductibles** (compliance, securite)               | Bazel                                      |
| **Types TypeScript partages** entre front et un eventuel BFF Node                    | Nx ou Turborepo                            |

> **Refs** :
> - [Nx 2026 Roadmap — polyglot improvements](https://nx.dev/blog/nx-2026-roadmap)
> - [Comparatif monorepo tools 2025](https://www.aviator.co/blog/monorepo-tools/)
> - [Turborepo vs Nx vs Bazel — analyse](https://medium.com/@piyalidas.it/monorepo-nx-vs-turborepo-vs-bazel-200504067d4b)

---

## 8. Risques et Mitigations

### 8.1. Risques lies a la migration

| Risque                        | Probabilite | Impact | Mitigation                                                                                                                                                                                                                       |
|-------------------------------|-------------|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Perte d'historique git**    | Faible      | Eleve  | `git subtree add` preserve l'integralite de l'historique (commits, auteurs, dates). Valider sur un repo de test avant la migration reelle. Les anciens repos sont archives, pas supprimes — on peut toujours y revenir.          |
| **Branches en cours perdues** | Moyenne     | Eleve  | Planifier la migration apres un "gel" : toutes les MRs en cours sont mergees ou mises en pause. Les branches actives sont recrees dans le monorepo apres import.                                                                 |
| **Dockerfiles/paths casses**  | Elevee      | Moyen  | Les `COPY`, `context:` et volumes dans les Dockerfiles et docker-compose changent (ex: `./` → `./apps/back/`). Preparer une checklist de tous les paths a mettre a jour. Tester le `docker compose up` avant la bascule.         |
| **Resistance au changement**  | Moyenne     | Moyen  | Presenter cet ADR en equipe. Faire une demo live de la DX monorepo (1 clone, 1 branche, 1 MR cross-stack). Migration progressive : commencer par travailler dans le monorepo en parallele des anciens repos pendant 1-2 sprints. |

### 8.2. Risques lies au monorepo au quotidien

| Risque                                    | Probabilite | Impact | Mitigation                                                                                                                                                                                                         |
|-------------------------------------------|-------------|--------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **CI plus complexe**                      | Moyenne     | Faible | Architecture `include:local` : chaque app gere sa propre CI dans son dossier. Le fichier racine reste < 15 lignes. Chaque equipe est autonome sur sa CI.                                                           |
| **Repo trop gros a terme**                | Tres faible | Faible | Repos actuels < 50 Mo cumules. Le probleme apparait au-dela de ~1 Go. Avec 6 services, on en est tres loin. Si necessaire a terme : `git sparse-checkout` pour ne cloner qu'un sous-ensemble.                      |
| **Conflits de merge plus frequents**      | Faible      | Faible | Les equipes touchent des dossiers differents (`apps/front/` vs `apps/back/`). Les conflits sur des fichiers partages (docker-compose, CI racine) sont rares et faciles a resoudre.                                 |
| **Perte d'autonomie de deploiement**      | Faible      | Moyen  | CI conditionnelle (`rules:changes`) : seuls les services modifies sont rebuildes et deployes. Un merge qui ne touche que `apps/front/` ne declenche aucun job back. Meme isolation de facto que des repos separes. |
| **MRs trop grosses (cross-stack)**        | Moyenne     | Faible | Convention : si une MR depasse ~500 lignes, la decouper en MRs sequentielles (ex: MR1 = back API, MR2 = front UI). Le monorepo n'empeche pas de faire des MRs single-scope.                                        |
| **Pas de blocage merge par owner (Free)** | -           | Moyen  | L'assignation auto des reviewers est geree par un job CI (voir section 6). Le blocage du merge reste une convention d'equipe. Le fichier CODEOWNERS est pret si migration vers Premium a terme.                    |

### 8.3. Risques de ne PAS migrer (statu quo)

| Risque                                              | Probabilite | Impact | Detail                                                                                                                                                                           |
|-----------------------------------------------------|-------------|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Refs submodules perpetuellement desynchronisees** | Elevee      | Moyen  | Constate aujourd'hui : 3/4 submodules en avance sur le workspace. Personne ne maintient les refs. Le probleme s'aggrave avec chaque nouveau service.                             |
| **Overhead de maintenance croissant**               | Elevee      | Moyen  | Aujourd'hui 47% des commits = maintenance de refs. Avec 6 services : ~60% de bruit previsible.                                                                                   |
| **Features cross-stack ralenties**                  | Elevee      | Eleve  | 3 MRs par feature, coordination manuelle, risque de desync. Avec 16 scopes partages entre front et back, c'est la majorite des features.                                         |
| **Pas de git flow possible**                        | Elevee      | Eleve  | Impossible de definir un flow unique quand chaque repo a ses propres branches. Les environnements integration/preprod/prod necessitent une synchronisation manuelle entre repos. |
| **Onboarding penible**                              | Moyenne     | Moyen  | Chaque nouveau dev doit comprendre les submodules, les refs, le bump. Source d'erreurs frequentes.                                                                               |

---

## 9. Plan de Migration

### Phase 1 : Preparation (non-bloquant)

- [ ] Valider la decision en equipe
- [ ] Creer le repo monorepo sur GitLab
- [ ] Definir les groupes CODEOWNERS

### Phase 2 : Import des historiques

```bash
# Depuis un repo vide
git init chapsmind && cd chapsmind

# Importer chaque repo avec son historique
git subtree add --prefix=apps/front <url-front> main
git subtree add --prefix=apps/back <url-back> main
git subtree add --prefix=apps/infra <url-infra> main
git subtree add --prefix=apps/global-service <url-global> main

# Copier docs, .claude, agent-os depuis workspace actuel
cp -r ../chapsmind-workspace/docs ./docs
cp -r ../chapsmind-workspace/.claude ./.claude
cp -r ../chapsmind-workspace/agent-os ./agent-os
```

### Phase 3 : CI et protections

- [ ] Ecrire le `.gitlab-ci.yml` unifie
- [ ] Configurer la protection de branche `main`
- [ ] Activer CODEOWNERS
- [ ] Ajouter les regles de merge (CI verte, approvals)

### Phase 4 : Bascule

- [ ] Gel des anciens repos (read-only)
- [ ] Communication equipe : "tout se fait dans le monorepo"
- [ ] Archiver les anciens repos sur GitLab
- [ ] Mettre a jour le README avec le nouveau workflow

---

## 10. Decision

**Recommandation : Option B — Monorepo**

**Justification** :
1. Elimine 47% de commits de maintenance inutile
2. Reduit de 3 a 1 le nombre de MRs par feature cross-stack
3. Permet un git flow unique et clair pour les 2 equipes
4. Absorbe naturellement l'ajout des futurs services
5. Ameliore la vitesse de hotfix (1 cycle de review au lieu de 2)
6. Rend possible la review unifiee et le CODEOWNERS
7. Score DX pondere : **67/75** vs 28/75 (submodules) vs 35/75 (multi-repo)