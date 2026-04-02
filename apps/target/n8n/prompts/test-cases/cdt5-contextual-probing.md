# 🧪 CDT 5 - Contextual Probing (Géographie)

## Informations

| Champ             | Valeur                                 |
| ----------------- | -------------------------------------- |
| **Version**       | 1.0                                    |
| **Date**          | 12/12/2025                             |
| **Prompt aligné** | v2.8                                   |
| **Priorité**      | 🟠 Important                           |
| **Focus**         | Probing adaptatif selon type de veille |

---

## 🎯 Objectif du Test

Vérifier que l'IA pose (ou ne pose pas) la question géographique selon le type de veille, remplaçant le 5W+H rigide par un probing contextuel.

### Règles de Probing Géographique

| Type              | Géographie      | Justification                                          |
| ----------------- | --------------- | ------------------------------------------------------ |
| **REGULATORY**    | **OBLIGATOIRE** | Juridictions différentes = réglementations différentes |
| **COMMERCIAL**    | Importante      | Marchés locaux                                         |
| **REPUTATIONAL**  | Optionnelle     | Sauf si multinational                                  |
| **COMPETITIVE**   | Optionnelle     | Sauf si multinational                                  |
| **TECHNOLOGICAL** | Non requise     | Souvent agnostique géographiquement                    |
| **STRATEGIC**     | Optionnelle     | Dépend du contexte                                     |

---

## 📋 Test A - REGULATORY (Géographie Obligatoire)

### Contexte

Veille réglementaire sur l'IA.

**userLanguage:** `fr`

---

### Tour 1

**User Input:**

```
Je veux suivre l'évolution de la réglementation sur l'IA
```

**Classification attendue:** `REGULATORY`

---

#### Comportement Attendu

**Actions requises:**

1. `Tool_WatchFile_Classify` → `regulatory`
2. `Tool_WatchFile_Rename` → "Veille Réglementaire - Intelligence Artificielle"
3. **Demander la géographie** (obligatoire pour REGULATORY)

**Message attendu:**

```
J'ai classifié votre veille comme **Veille Réglementaire - Intelligence Artificielle**.

Sur quelles juridictions souhaitez-vous vous concentrer ?
(Union Européenne, États-Unis, France, mondial...)
```

---

#### Checklist Test A - Tour 1

| Critère                           | Attendu | Résultat |
| --------------------------------- | ------- | -------- |
| Classification = REGULATORY       | ☐       |          |
| **Question géographie posée**     | ☐       |          |
| Options proposées (UE, US, FR...) | ☐       |          |
| Questions ≤ 1                     | ☐       |          |

**❌ FAIL si:**

- Pas de question sur la géographie
- Continue sans clarifier les juridictions
- Propose directement des sources sans savoir le périmètre

---

### Tour 2 - Réponse Géographique

**User Input:**

```
Union Européenne principalement, avec un œil sur les États-Unis
```

---

#### Comportement Attendu

**Actions requises:**

1. **Mettre à jour Reference Subject** avec géographie
2. Proposer des sources adaptées (EUR-Lex, Commission EU, etc.)

---

## 📋 Test B - TECHNOLOGICAL (Géographie Non Requise)

### Contexte

Veille technologique sur l'informatique quantique.

**userLanguage:** `fr`

---

### Tour 1

**User Input:**

```
Je veux surveiller les avancées en informatique quantique
```

**Classification attendue:** `TECHNOLOGICAL`

---

#### Comportement Attendu

**Actions requises:**

1. `Tool_WatchFile_Classify` → `technological`
2. `Tool_WatchFile_Rename`
3. **NE PAS demander** la géographie
4. Proposer directement acteurs/sources

**Message attendu:**

```
J'ai configuré votre veille comme **Veille Technologique - Informatique Quantique**.

Les acteurs clés dans ce domaine incluent :
- **IBM Quantum** - Leader industrie
- **Google Quantum AI** - Recherche avancée
- **IonQ** - Startup quantique majeure

Souhaitez-vous que j'en ajoute certains ?
```

---

#### Checklist Test B - Tour 1

| Critère                        | Attendu | Résultat |
| ------------------------------ | ------- | -------- |
| Classification = TECHNOLOGICAL | ☐       |          |
| **Pas de question géographie** | ☐       |          |
| Proposition acteurs directe    | ☐       |          |
| Discovery lancé                | ☐       |          |

**❌ FAIL si:**

- Question "Dans quelle région ?" pour une veille techno
- Attente inutile avant de proposer

---

## 📋 Test C - COMMERCIAL (Géographie Importante)

### Contexte

Veille commerciale sur le marché des véhicules électriques.

**userLanguage:** `fr`

---

### Tour 1

**User Input:**

```
Je veux surveiller le marché des véhicules électriques pour identifier des opportunités commerciales
```

**Classification attendue:** `COMMERCIAL`

---

#### Comportement Attendu

La géographie est **importante** (pas obligatoire) pour COMMERCIAL car les marchés sont souvent locaux.

**Deux comportements acceptables:**

**Option A - Question géographie:**

```
J'ai classifié votre veille comme **Veille Commerciale - Véhicules Électriques**.

Sur quels marchés géographiques souhaitez-vous vous concentrer ?
(Europe, Amérique du Nord, Asie, mondial...)
```

**Option B - Proposition + mention géographie:**

```
J'ai classifié votre veille comme **Veille Commerciale - Véhicules Électriques**.

Les acteurs clés incluent **Tesla**, **BYD**, **Volkswagen ID**.
Souhaitez-vous vous concentrer sur un marché géographique particulier ?
```

---

#### Checklist Test C - Tour 1

| Critère                           | Attendu | Résultat |
| --------------------------------- | ------- | -------- |
| Classification = COMMERCIAL       | ☐       |          |
| Géographie mentionnée ou demandée | ☐       |          |
| Questions ≤ 1                     | ☐       |          |

---

## 📊 Matrice Récapitulative

| Type          | Géo Demandée  | Attendu                  | Test |
| ------------- | ------------- | ------------------------ | ---- |
| REGULATORY    | ☐ Oui / ☐ Non | **Oui** (obligatoire)    | A    |
| TECHNOLOGICAL | ☐ Oui / ☐ Non | **Non** (optionnel)      | B    |
| COMMERCIAL    | ☐ Oui / ☐ Non | Oui (important)          | C    |
| COMPETITIVE   | -             | Non (sauf multinational) | -    |
| REPUTATIONAL  | -             | Non (sauf précisé)       | -    |
| STRATEGIC     | -             | Optionnel                | -    |

---

## 🔍 Cas Particuliers

### Multinational Mentionné → Géographie Pertinente

**User Input:**

```
Je veux surveiller les activités de Nestlé
```

**Comportement acceptable:**

- Nestlé = multinationale suisse → géographie pertinente
- Question acceptable : "Souhaitez-vous surveiller Nestlé globalement ou sur certains marchés ?"

### Contexte Local Évident → Pas de Question

**User Input:**

```
Je veux surveiller la CNIL et ses décisions
```

**Comportement attendu:**

- CNIL = autorité française → contexte local évident
- **Pas de question géographie** (implicitement France)

---

## 🚨 Critères Bloquants

| Critère                                 | Verdict           |
| --------------------------------------- | ----------------- |
| Pas de question géo pour REGULATORY     | ❌ ÉCHEC CRITIQUE |
| Question géo inutile pour TECHNOLOGICAL | ❌ ÉCHEC MAJEUR   |
| Plus d'1 question dans la réponse       | ❌ ÉCHEC MAJEUR   |
| Ignorer contexte local évident (CNIL)   | ❌ ÉCHEC MINEUR   |

---

## 📜 Historique des Exécutions

| Date | Version Prompt | Test | Résultat | Notes |
| ---- | -------------- | ---- | -------- | ----- |
|      |                | A    |          |       |
|      |                | B    |          |       |
|      |                | C    |          |       |

---

## 📎 Captures d'Écran

_Ajouter les captures après exécution_

---

**CDT 5 - Contextual Probing v1.0**
