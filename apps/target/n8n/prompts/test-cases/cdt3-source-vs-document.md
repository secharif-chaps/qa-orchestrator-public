# 🧪 CDT 3 - Source vs Document

## Informations

| Champ             | Valeur                                            |
| ----------------- | ------------------------------------------------- |
| **Version**       | 1.0                                               |
| **Date**          | 12/12/2025                                        |
| **Prompt aligné** | v2.8                                              |
| **Priorité**      | 🔴 Critique                                       |
| **Focus**         | Anti-confusion Source/Document (Rule 5 renforcée) |

---

## 🎯 Objectif du Test

Vérifier que l'IA ne propose **JAMAIS** d'URLs d'articles spécifiques comme sources, mais uniquement des sites/canaux qui publient régulièrement.

### Définitions Clés

| Concept      | Définition                          | Exemple ✅  | Exemple ❌                     |
| ------------ | ----------------------------------- | ----------- | ------------------------------ |
| **SOURCE**   | Site/canal qui publie régulièrement | bonpote.com | bonpote.com/article-shein-2024 |
| **DOCUMENT** | Contenu spécifique collecté         | -           | URL avec date/slug d'article   |

### Critères de Reconnaissance

**C'est une SOURCE si:**

- ✅ URL = domaine racine ou section thématique
- ✅ Site publie régulièrement
- ✅ Peut être crawlé pour collecter des documents

**C'est un DOCUMENT si:**

- ❌ URL contient un slug d'article (titre encodé)
- ❌ URL contient une date (2024, 20251211, etc.)
- ❌ URL contient un ID unique (/articles/cy5k3v6x6vxo)

---

## 📋 Conversation de Test

### Contexte

Veille sur les controverses environnementales de l'industrie textile.

**userLanguage:** `fr`

---

### Tour 1 - Setup Classification

**User Input:**

```
Veille sur les controverses environnementales de l'industrie textile, notamment la fast fashion.
```

**Classification attendue:** `REPUTATIONAL` (focus controverses)

---

### Tour 2 - Demande de Sources (Test Principal)

**User Input:**

```
Trouve-moi des sources pour cette veille
```

---

#### Comportement Attendu

**Tools attendus:**

1. `Tool_WebSearch_Grounding` (sources controverses textile)
2. Proposition de **SOURCES** uniquement

---

#### Réponse ACCEPTABLE ✅

```
Voici des sources pertinentes pour surveiller les controverses environnementales de la fast fashion :

**Médias généralistes - Section environnement :**
- *The Guardian* - Environment (theguardian.com/environment)
- *Le Monde* - Planète (lemonde.fr/planete)

**Blogs et médias spécialisés :**
- *Bon Pote* - Blog climat (bonpote.com)
- *Fashion Revolution* - ONG mode éthique (fashionrevolution.org)

**ONG et watchdogs :**
- *Greenpeace* - Actualités (greenpeace.org/france)
- *Public Eye* - Investigations (publiceye.ch)

Souhaitez-vous que j'ajoute certaines de ces sources ?
```

---

#### Réponse NON ACCEPTABLE ❌

```
Voici des sources :
- https://www.nouvelobs.com/edito/20251211.OBS110559/shein-les-dessous-de-la-fast-fashion.html
- https://bonpote.com/shein-la-marque-dultra-fast-fashion-qui-envahit-le-monde/
- https://www.wired.com/story/the-shein-gig-workers-packing-your-order/
- https://www.bbc.com/news/articles/cy5k3v6x6vxo
```

---

#### Tableau d'Analyse des URLs

| URL                                   | Caractéristiques              | Verdict     |
| ------------------------------------- | ----------------------------- | ----------- |
| `bonpote.com`                         | Domaine racine, blog régulier | ✅ SOURCE   |
| `bonpote.com/shein-la-marque-...`     | Slug d'article dans URL       | ❌ DOCUMENT |
| `theguardian.com/fashion`             | Section thématique            | ✅ SOURCE   |
| `theguardian.com/fashion/2024/...`    | Date + slug dans URL          | ❌ DOCUMENT |
| `bbc.com/news`                        | Section actualités            | ✅ SOURCE   |
| `bbc.com/news/articles/cy5k3v6x6vxo`  | ID article unique             | ❌ DOCUMENT |
| `nouvelobs.com`                       | Domaine média                 | ✅ SOURCE   |
| `nouvelobs.com/edito/20251211.OBS...` | Date + ID article             | ❌ DOCUMENT |
| `fashionrevolution.org`               | Site ONG                      | ✅ SOURCE   |
| `linkedin.com/company/shein`          | Page entreprise               | ✅ SOURCE   |
| `linkedin.com/posts/...`              | Post spécifique               | ❌ DOCUMENT |

---

#### Checklist Tour 2

| Critère                                    | Attendu | Résultat |
| ------------------------------------------ | ------- | -------- |
| Toutes URLs = domaines ou sections         | ☐       |          |
| Aucune URL avec date                       | ☐       |          |
| Aucune URL avec slug d'article             | ☐       |          |
| Aucune URL avec ID unique                  | ☐       |          |
| Sources = sites qui publient régulièrement | ☐       |          |

---

### Tour 3 - Test Clarification (Si User Fournit Documents)

**User Input:**

```
Ajoute cette source : https://www.nouvelobs.com/edito/20251211.OBS110559/shein-les-dessous-de-la-fast-fashion.html
```

---

#### Comportement Attendu

L'IA doit **refuser poliment** et expliquer la différence :

```
Cette URL pointe vers un article spécifique (un document), pas vers une source.

Une source est un site ou canal qui publie régulièrement et que nous pouvons surveiller pour collecter de nouveaux documents.

Souhaitez-vous que j'ajoute *Le Nouvel Obs* (nouvelobs.com) comme source à la place ? Cela permettra de collecter automatiquement leurs futurs articles sur le sujet.
```

---

#### Checklist Tour 3

| Critère                          | Attendu | Résultat |
| -------------------------------- | ------- | -------- |
| Article NON ajouté comme source  | ☐       |          |
| Explication Source vs Document   | ☐       |          |
| Proposition du domaine racine    | ☐       |          |
| Ton pédagogique (pas accusateur) | ☐       |          |

---

## 📊 Grille d'Évaluation Finale

| Tour | Sources Proposées   | Documents Évités | Explication Fournie |
| ---- | ------------------- | ---------------- | ------------------- |
| 2    | ☐ Domaines/sections | ☐ Aucun article  | N/A                 |
| 3    | N/A                 | ☐ Refusé         | ☐ Oui               |

---

## 🔍 Patterns d'URLs à Détecter

### URLs de DOCUMENTS (à rejeter)

```regex
# Patterns typiques d'articles
/\d{4}\/\d{2}\/\d{2}/          # Date dans URL: 2024/12/11
/\d{8}/                         # Date compacte: 20251211
/articles?\/[a-z0-9]+/i         # ID article: /article/abc123
/\.(html|htm|php)\?/            # Extension + query
/story\/[a-z-]+/                # Slug narratif: /story/the-shein-workers
/[a-z]+-[a-z]+-[a-z]+-[a-z]+/   # Slug long: /shein-la-marque-dultra-fast
```

### URLs de SOURCES (acceptables)

```regex
# Patterns typiques de sources
^https?:\/\/[^\/]+\/?$          # Domaine racine: example.com/
^https?:\/\/[^\/]+\/[a-z]+\/?$  # Section simple: example.com/fashion/
/company\/[a-z-]+\/?$/          # LinkedIn company: /company/shein
/blog\/?$/                      # Blog racine: /blog/
```

---

## 🚨 Critères Bloquants

| Critère                                        | Verdict           |
| ---------------------------------------------- | ----------------- |
| Proposer une URL d'article comme source        | ❌ ÉCHEC CRITIQUE |
| Accepter un article fourni par l'utilisateur   | ❌ ÉCHEC CRITIQUE |
| Pas d'explication si user fournit article      | ❌ ÉCHEC MAJEUR   |
| URLs génériques (linkedin.com sans /company/X) | ❌ ÉCHEC MAJEUR   |

---

## 📜 Historique des Exécutions

| Date | Version Prompt | Résultat | Notes |
| ---- | -------------- | -------- | ----- |
|      |                |          |       |

---

## 📎 Captures d'Écran

_Ajouter les captures après exécution_

---

**CDT 3 - Source vs Document v1.0**
