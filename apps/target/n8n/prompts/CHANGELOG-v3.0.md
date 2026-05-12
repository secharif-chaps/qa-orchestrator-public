# Chaps-e v3.0 - Architecture Refonte Complète

## 📋 Résumé Exécutif

Cette refonte majeure du comportement de Chaps-e introduit :

1. **Phase 0 - Évaluation Initiale** : Analyse intelligente du premier message
2. **Questionnement Itératif** : Une question à la fois avec reformulation
3. **Topics Dynamiques** : Classification enrichie de thèmes contextuels
4. **Reference Subject Dual** : Version humaine + version LLM optimisée
5. **Découverte Proactive** : Acteurs d'abord, sources liées ensuite

---

## 📁 Prompts Modifiés

| Prompt              | Fichier                                                                             | Version | Statut  |
| ------------------- | ----------------------------------------------------------------------------------- | ------- | ------- |
| Chat Assistant      | `2. Chat Assistant/ChatAssistant-WatchFile-Prompt-v3.0.md`                          | 3.0     | ✅ Créé |
| Classify            | `5. Classify Watchfile/ClassifyWatchfile-Prompt-v2.0.md`                            | 2.0     | ✅ Créé |
| Reference Subject   | `3c. Reference Subject/ReferenceSubject-Prompt-v4.0.md`                             | 4.0     | ✅ Créé |
| Strategic Questions | `6a. DeepSearch - Strategic Questions/DeepSearch-StrategicQuestions-Prompt-v2.0.md` | 2.0     | ✅ Créé |

---

## 🔄 Nouveau Flow Conversationnel

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PREMIER MESSAGE UTILISATEUR                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                    PHASE 0: ÉVALUATION INITIALE                       │   │
│  │                                                                        │   │
│  │   Score 5W+H:                                                         │   │
│  │   • WHAT (25%) : Sujet identifié ?                                    │   │
│  │   • WHY (25%)  : Objectif clair ?                                     │   │
│  │   • WHO (15%)  : Acteurs mentionnés ?                                 │   │
│  │   • WHERE (15%): Géographie spécifiée ?                               │   │
│  │   • HOW (10%)  : Sources indiquées ?                                  │   │
│  │   • WHEN (10%) : Temporalité définie ?                                │   │
│  │                                                                        │   │
│  │   Score Total ≥ 70% ? ─────────────────────────┐                      │   │
│  │        │                                        │                      │   │
│  │        NO                                      YES                     │   │
│  │        │                                        │                      │   │
│  │        ▼                                        ▼                      │   │
│  │   ┌─────────────┐                    ┌─────────────────────┐          │   │
│  │   │ Explication │                    │ CLASSIFICATION      │          │   │
│  │   │ méthodologie│                    │ IMMÉDIATE           │          │   │
│  │   │ (vague)     │                    │ + Topics dynamiques │          │   │
│  │   │ + 1 question│                    └──────────┬──────────┘          │   │
│  │   └─────────────┘                               │                      │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                         MESSAGES SUIVANTS                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                 QUESTIONNEMENT ITÉRATIF                               │   │
│  │                                                                        │   │
│  │   Pour chaque réponse utilisateur :                                   │   │
│  │                                                                        │   │
│  │   1. Extraction opportuniste (toutes dimensions)                      │   │
│  │   2. Mise à jour du state 5W+H                                        │   │
│  │   3. Question posée couverte ?                                        │   │
│  │      • OUI (≥60%) → Passer à suivante                                 │   │
│  │      • NON (<60%) → Reformuler (max 2x) ou passer                     │   │
│  │   4. Score total ≥ 70% → Classification                               │   │
│  │                                                                        │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                 POST-CLASSIFICATION                                   │   │
│  │                                                                        │   │
│  │   1. Tool_WatchFile_ClassifyWithTopics                                │   │
│  │      → Type + Topics dynamiques                                       │   │
│  │                                                                        │   │
│  │   2. Tool_WatchFile_Rename                                            │   │
│  │      → Nom significatif                                               │   │
│  │                                                                        │   │
│  │   3. Tool_WatchFile_BuilderReferenceSubject                           │   │
│  │      → Version Human + Version LLM                                    │   │
│  │                                                                        │   │
│  │   4. Tool_WatchFile_GenerateStrategicQuestions                        │   │
│  │      → 3-5 questions MECE basées sur topics                           │   │
│  │                                                                        │   │
│  │   5. Tool_WebSearch_Grounding (pour chaque question)                  │   │
│  │      → Découverte acteurs/sources                                     │   │
│  │                                                                        │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                 GESTION ACTEURS/SOURCES                               │   │
│  │                                                                        │   │
│  │   Priorité : ACTEURS > SOURCES                                        │   │
│  │                                                                        │   │
│  │   Quand acteur mentionné par user :                                   │   │
│  │   1. Ajout immédiat                                                   │   │
│  │   2. Web Search découverte acteurs liés                               │   │
│  │   3. Web Search sources de l'acteur                                   │   │
│  │   4. Proposition consolidée                                           │   │
│  │                                                                        │   │
│  │   Seuils d'ajout automatique :                                        │   │
│  │   • ≥85% relevance → Ajout auto + notification                        │   │
│  │   • 60-84% → Proposition confirmation                                 │   │
│  │   • <60% → Pas de proposition                                         │   │
│  │                                                                        │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                 REFERENCE SUBJECT INCRÉMENTAL                         │   │
│  │                                                                        │   │
│  │   Construction progressive :                                          │   │
│  │                                                                        │   │
│  │   Tour 1 → Sujet                                                      │   │
│  │   Tour 2 → + Objectif, Thèmes                                         │   │
│  │   Classification → + Critères de pertinence (auto)                    │   │
│  │   Acteurs ajoutés → + Acteurs prioritaires                            │   │
│  │   Sources ajoutées → + Sources d'information                          │   │
│  │   Géographie précisée → + Périmètre géographique                      │   │
│  │                                                                        │   │
│  │   Dual Output :                                                       │   │
│  │   • Human (FR+EN) : Concis, lisible, UI                               │   │
│  │   • LLM (EN) : Critères de scoring détaillés                          │   │
│  │                                                                        │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 📊 Comparaison v2.8 vs v3.0

| Aspect                | v2.8                       | v3.0                                  |
| --------------------- | -------------------------- | ------------------------------------- |
| **Premier message**   | Classification si possible | Évaluation 5W+H + décision adaptative |
| **Questionnement**    | Max 1 question             | Max 1 question + reformulation (2x)   |
| **Classification**    | Type seul                  | Type + Topics dynamiques              |
| **Topics**            | Non existants              | Générés dynamiquement selon contexte  |
| **Reference Subject** | Version unique             | Dual (Human + LLM optimized)          |
| **Sections vides**    | Affichées avec placeholder | Non affichées du tout                 |
| **Priorité**          | Sources et Acteurs égaux   | Acteurs d'abord                       |
| **Découverte**        | Après demande              | Proactive via Strategic Questions     |
| **State tracking**    | Implicite                  | Explicite (5W+H en mémoire)           |

---

## 🔧 Nouveaux Tools Requis

### Tool_WatchFile_ClassifyWithTopics

**Input :**

```json
{
  "watchFile": {...},
  "conversation": {...},
  "userLanguage": "fr"
}
```

**Output :**

```json
{
  "primaryType": "reputational",
  "primarySubtype": "negative",
  "confidenceScore": 85,
  "topics": [
    {
      "label": "Labor Practice Controversies",
      "labelFR": "Controverses Pratiques de Travail",
      "keywords": ["sweatshop", "forced labor"],
      "keywordsFR": ["atelier clandestin", "travail forcé"],
      "relevanceScore": 95,
      "searchQueryTemplate": "{entity} labor controversy investigation"
    }
  ],
  "suggestions": {...}
}
```

### Tool_WatchFile_BuilderReferenceSubject (modifié)

**Output :**

```json
{
  "referenceSubject": {
    "human": {
      "fr": "## Sujet de surveillance\n...",
      "en": "## Monitoring Subject\n..."
    },
    "llm": "# DOCUMENT RELEVANCE SCORING CRITERIA\n..."
  }
}
```

### Tool_WatchFile_GenerateStrategicQuestions

**Input :**

```json
{
  "watchFile": {...},
  "classificationType": "reputational",
  "topics": [...]
}
```

**Output :**

```json
{
  "strategicQuestions": [
    {
      "questionEN": "...",
      "questionFR": "...",
      "mappedTopics": ["Topic 1"],
      "discoveryTarget": "actors",
      "priority": 95,
      "searchQueries": ["query 1", "query 2"]
    }
  ]
}
```

---

## 📋 Checklist Implémentation N8N

### Workflow 2. Chat Session Message

- [ ] Ajouter node de détection premier message (`isFirstMessage`)
- [ ] Ajouter node Phase 0 Assessment (si premier message)
- [ ] Modifier node LLM principal pour utiliser v3.0
- [ ] Ajouter gestion state 5W+H (en mémoire conversation)
- [ ] Connecter Tool_WatchFile_ClassifyWithTopics
- [ ] Connecter Tool_WatchFile_GenerateStrategicQuestions
- [ ] Modifier Tool_WatchFile_BuilderReferenceSubject pour dual output

### Workflow 5. Classify Watchfile

- [ ] Mettre à jour le prompt vers v2.0
- [ ] Modifier output schema pour inclure topics
- [ ] Ajouter génération dynamique des topics

### Workflow 3c. Reference Subject

- [ ] Mettre à jour le prompt vers v4.0
- [ ] Modifier output schema pour dual (human + llm)
- [ ] Implémenter logique de sections vides non affichées

### Workflow 6a. Strategic Questions

- [ ] Mettre à jour le prompt vers v2.0
- [ ] Ajouter mapping topics → questions
- [ ] Ajouter coverageAnalysis

---

## 🧪 Cas de Test à Mettre à Jour

| CDT   | Changements Requis                                 |
| ----- | -------------------------------------------------- |
| CDT 1 | Tester Phase 0 sur besoin flou                     |
| CDT 2 | Tester découverte proactive post-classification    |
| CDT 3 | Inchangé (Source vs Document)                      |
| CDT 4 | Tester détection drift avec dual Reference Subject |
| CDT 5 | Tester questionnement itératif avec reformulation  |
| CDT 6 | Inchangé (Self-Description)                        |
| CDT 7 | Tester dual Reference Subject (Human + LLM)        |
| CDT 8 | Tester flow complet avec topics dynamiques         |

### Nouveau CDT 9 - Phase 0 Assessment

**Objectif :** Valider l'évaluation initiale du premier message

**Scénarios :**

1. Message clair (≥70%) → Classification immédiate
2. Message moyen (50-69%) → 1-2 questions puis classification
3. Message flou (<50%) → Explication + question prioritaire

---

## 📝 Notes Techniques

### Persistence du State 5W+H

Le state 5W+H est maintenu **en mémoire de conversation uniquement** :

- Pas de persistence en base
- Reconstruit à partir du contexte si nécessaire
- Permet de tracker les questions posées/répondues

### Topics Dynamiques vs Statiques

Les topics sont générés **dynamiquement** par le LLM de classification :

- Plus contextuels que des topics fixes
- Adaptés aux entités mentionnées
- Incluent des keywords bilingues
- Contiennent des templates de recherche

### Reference Subject Dual

- **Human version** : Affichée dans l'UI, toujours bilingue
- **LLM version** : Utilisée par le workflow de validation documents, EN uniquement
- Les deux sont mises à jour ensemble lors des triggers

---

## 🚀 Prochaines Étapes

1. **Validation des prompts** par l'équipe
2. **Implémentation N8N** des modifications de workflow
3. **Mise à jour des output schemas** dans les nodes
4. **Tests unitaires** des nouveaux prompts
5. **Tests d'intégration** du flow complet
6. **Mise à jour des cas de test QA**

---

**Version document :** 1.0
**Date :** 17/12/2025
**Auteur :** BasilTools Assistant
