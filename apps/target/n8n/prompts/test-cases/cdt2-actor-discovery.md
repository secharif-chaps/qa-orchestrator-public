# 🧪 CDT 2 - Actor Discovery

## Informations

| Champ             | Valeur                                             |
| ----------------- | -------------------------------------------------- |
| **Version**       | 1.0                                                |
| **Date**          | 12/12/2025                                         |
| **Prompt aligné** | v2.8                                               |
| **Priorité**      | 🔴 Critique                                        |
| **Focus**         | Proactive Actor Discovery (Rule 12 - nouveau v2.8) |

---

## 🎯 Objectif du Test

Vérifier que l'IA propose **automatiquement** des acteurs similaires après l'ajout d'un premier acteur, sans attendre qu'on lui demande.

### Critères Spécifiques

| Critère               | Description                              |
| --------------------- | ---------------------------------------- |
| Discovery automatique | Recherche lancée après ajout d'acteur    |
| Proposition proactive | Acteurs découverts proposés sans demande |
| Pertinence            | Acteurs du même secteur/domaine          |
| Queries adaptées      | Selon le type de veille                  |

---

## 📋 Conversation de Test

### Contexte

Product Manager voulant surveiller les concurrents CRM.

**userLanguage:** `fr`

---

### Tour 1 - Message Initial avec Acteur

**User Input:**

```
Je veux surveiller Salesforce pour comprendre leur stratégie produit, je suis PM chez un éditeur CRM concurrent.
```

**Éléments détectés:**

- [x] Sujet : Stratégie produit CRM
- [x] Objectif : Surveillance concurrentielle
- [x] Acteur : Salesforce (mentionné)
- [ ] Sources : Non spécifiées

---

#### Comportement Attendu

**Classification:** `COMPETITIVE`

**Tools attendus (ORDRE CRITIQUE):**

1. `Tool_WatchFile_Classify` → `competitive`
2. `Tool_WatchFile_Rename` → "Veille Concurrentielle - CRM/Salesforce"
3. `Tool_WatchFile_BuilderReferenceSubject`
4. `Tool_WatchFile_BuilderActor` (Salesforce)
5. `Tool_WebSearch_Grounding` → **"Salesforce competitors alternatives CRM 2024"** ← DISCOVERY
6. **Proposition d'acteurs découverts** (SANS qu'on demande)

**Message attendu (exemple):**

```
J'ai configuré votre veille concurrentielle sur le marché CRM.

J'ai ajouté **Salesforce** à votre surveillance.

Dans le même secteur, j'ai identifié d'autres acteurs potentiellement pertinents :
- **HubSpot** - CRM concurrent, forte croissance PME
- **Microsoft Dynamics 365** - Concurrent enterprise
- **Pipedrive** - CRM orienté ventes
- **Zoho CRM** - Alternative économique

Souhaitez-vous que j'en ajoute certains ?
```

---

#### Checklist Tour 1

| Critère                         | Attendu | Résultat |
| ------------------------------- | ------- | -------- |
| Classification = COMPETITIVE    | ☐       |          |
| Salesforce ajouté comme acteur  | ☐       |          |
| **Discovery automatique lancé** | ☐       |          |
| **Acteurs découverts proposés** | ☐       |          |
| Au moins 3 acteurs proposés     | ☐       |          |
| Acteurs pertinents (CRM)        | ☐       |          |
| Questions ≤ 1                   | ☐       |          |

**❌ FAIL si:**

- Salesforce ajouté SANS proposition d'autres acteurs
- Question "Qui sont vos concurrents ?" au lieu de proposer
- Discovery non lancé automatiquement

---

### Tour 2 - Ajout d'Acteurs Découverts

**User Input:**

```
Oui, ajoute HubSpot et Pipedrive
```

---

#### Comportement Attendu

**Tools attendus:**

1. `Tool_WatchFile_BuilderActor` × 2 (HubSpot, Pipedrive)
2. Confirmation consolidée

**Message attendu:**

```
J'ai ajouté **HubSpot** et **Pipedrive** à votre surveillance.

Souhaitez-vous que je trouve leurs blogs produits et pages LinkedIn comme sources ?
```

---

#### Checklist Tour 2

| Critère                              | Attendu | Résultat |
| ------------------------------------ | ------- | -------- |
| Acteurs ajoutés (HubSpot, Pipedrive) | ☐       |          |
| Confirmation consolidée (1 message)  | ☐       |          |
| Suggestion pour sources              | ☐       |          |
| Questions ≤ 1                        | ☐       |          |

---

### Tour 3 - Demande de Sources

**User Input:**

```
Oui, trouve-moi leurs sources officielles
```

---

#### Comportement Attendu

**Tools attendus:**

1. `Tool_WebSearch_Grounding` (blogs, LinkedIn des acteurs)
2. Proposition de sources avec URLs vérifiées

**Sources attendues (exemples):**

```
- *Salesforce Blog* (salesforce.com/blog)
- *HubSpot Blog* (blog.hubspot.com)
- *Pipedrive Blog* (pipedrive.com/blog)
- *LinkedIn Salesforce* (linkedin.com/company/salesforce)
- *LinkedIn HubSpot* (linkedin.com/company/hubspot)
```

---

#### Checklist Tour 3

| Critère                               | Attendu | Résultat |
| ------------------------------------- | ------- | -------- |
| Sources officielles trouvées          | ☐       |          |
| URLs spécifiques (pas génériques)     | ☐       |          |
| Sources = sites/canaux (pas articles) | ☐       |          |

---

## 📊 Grille d'Évaluation Finale

| Tour | Discovery Lancé | Acteurs Proposés | Confirmation Consolidée |
| ---- | --------------- | ---------------- | ----------------------- |
| 1    | ☐ Automatique   | ☐ 3+ proposés    | N/A                     |
| 2    | N/A             | ☐ Ajoutés        | ☐ 1 message             |
| 3    | N/A             | N/A              | ☐ Sources proposées     |

---

## 🔍 Queries Discovery par Type de Veille

Ce test valide le query pattern pour COMPETITIVE. Référence pour autres types :

| Type          | Query Pattern                                            |
| ------------- | -------------------------------------------------------- |
| COMPETITIVE   | `"{actor}" competitors rivals alternatives market`       |
| REGULATORY    | `"{domain}" regulatory bodies authorities agencies`      |
| TECHNOLOGICAL | `"{technology}" research labs companies patents leaders` |
| COMMERCIAL    | `"{market}" key players distributors partners`           |
| STRATEGIC     | `"{company}" investors partners M&A targets`             |
| REPUTATIONAL  | `"{entity}" critics watchdogs NGOs media coverage`       |

---

## 🚨 Critères Bloquants

| Critère                                                  | Verdict           |
| -------------------------------------------------------- | ----------------- |
| Pas de Discovery automatique après ajout acteur          | ❌ ÉCHEC CRITIQUE |
| Question "Qui sont vos concurrents?" au lieu de proposer | ❌ ÉCHEC CRITIQUE |
| Moins de 3 acteurs proposés                              | ❌ ÉCHEC MAJEUR   |
| Acteurs non pertinents (hors secteur)                    | ❌ ÉCHEC MAJEUR   |

---

## 📜 Historique des Exécutions

| Date | Version Prompt | Résultat | Notes |
| ---- | -------------- | -------- | ----- |
|      |                |          |       |

---

## 📎 Captures d'Écran

_Ajouter les captures après exécution_

---

**CDT 2 - Actor Discovery v1.0**
