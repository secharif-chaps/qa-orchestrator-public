# 🧪 CDT 4 - Scope Drift Detection

## Informations

| Champ             | Valeur                                                |
| ----------------- | ----------------------------------------------------- |
| **Version**       | 1.0                                                   |
| **Date**          | 12/12/2025                                            |
| **Prompt aligné** | v2.8                                                  |
| **Priorité**      | 🔴 Critique                                           |
| **Focus**         | Détection changement de scope + MAJ Reference Subject |

---

## 🎯 Objectif du Test

Vérifier que l'IA détecte les changements de scope en cours de conversation et met à jour le Reference Subject en conséquence.

### Indicateurs de Scope Drift

| Signal                 | Exemple                                   |
| ---------------------- | ----------------------------------------- |
| Élargissement          | "En fait je veux aussi surveiller..."     |
| Rétrécissement         | "Finalement, concentrons-nous sur..."     |
| Changement d'angle     | "Plutôt sous l'angle réglementaire"       |
| Précision géographique | "Uniquement en Europe"                    |
| Changement de tonalité | "Je m'intéresse surtout aux controverses" |

---

## 📋 Conversation de Test

### Contexte

Utilisateur qui change progressivement son besoin.

**userLanguage:** `fr`

---

### Tour 1 - Configuration Initiale (Générale)

**User Input:**

```
Je veux surveiller Shein
```

**Comportement Attendu:**

- Classification (probablement COMPETITIVE par défaut)
- Reference Subject initial : surveillance générale de Shein

**Reference Subject v1 attendu:**

```
Surveillance de Shein - Activités générales de l'entreprise, stratégie,
actualités du groupe dans le secteur de la fast fashion.
```

---

#### Checklist Tour 1

| Critère                     | Attendu | Résultat |
| --------------------------- | ------- | -------- |
| Classification effectuée    | ☐       |          |
| Reference Subject v1 généré | ☐       |          |
| Scope = général             | ☐       |          |

---

### Tour 2 - Changement de Scope (Drift → Réputationnel)

**User Input:**

```
En fait je m'intéresse surtout aux controverses et au bad buzz autour de la marque
```

---

#### Comportement Attendu

**Détection obligatoire:**

- ⚠️ Scope Drift détecté (général → réputationnel négatif)
- Mots-clés : "controverses", "bad buzz"
- Changement d'angle significatif

**Actions requises:**

1. Reclassifier → `REPUTATIONAL` (sous-type: Negative)
2. Renommer → "Veille Réputationnelle Négative - Shein"
3. **Mettre à jour Reference Subject** (OBLIGATOIRE)

**Reference Subject v2 attendu:**

```
Veille réputationnelle négative - Shein
Focus : Controverses, bad buzz, critiques, scandales concernant Shein.
Angles : Pratiques environnementales, conditions de travail, qualité produits,
accusations diverses.
Tonalité : Négative (risques réputationnels).
```

**Message attendu:**

```
Je comprends, vous souhaitez vous concentrer sur les aspects réputationnels négatifs.

J'ai mis à jour votre dossier :
- **Type :** Veille Réputationnelle Négative
- **Focus :** Controverses, bad buzz, critiques sur Shein

Souhaitez-vous que je recherche des sources spécialisées dans les enquêtes et critiques de la fast fashion ?
```

---

#### Checklist Tour 2

| Critère                                | Attendu | Résultat |
| -------------------------------------- | ------- | -------- |
| **Drift détecté**                      | ☐       |          |
| Reclassification → REPUTATIONAL        | ☐       |          |
| Renommage cohérent                     | ☐       |          |
| **Reference Subject mis à jour**       | ☐       |          |
| Nouveau scope reflété dans Ref Subject | ☐       |          |
| Confirmation du changement             | ☐       |          |

**❌ FAIL si:**

- Pas de mise à jour du Reference Subject
- Pas de reclassification
- Continue comme si rien n'avait changé

---

### Tour 3 - Élargissement du Scope

**User Input:**

```
J'aimerais aussi surveiller leurs concurrents Temu et ASOS pour comparer leur réputation
```

---

#### Comportement Attendu

**Détection:**

- ⚠️ Scope Drift détecté (élargissement acteurs)
- Ajout de Temu et ASOS
- Dimension comparative

**Actions requises:**

1. `Tool_WatchFile_BuilderActor` × 2 (Temu, ASOS)
2. **Mettre à jour Reference Subject** (inclure nouveaux acteurs)

**Reference Subject v3 attendu:**

```
Veille réputationnelle négative comparative - Fast Fashion
Focus : Controverses, bad buzz, critiques sur Shein, Temu et ASOS.
Acteurs prioritaires : Shein, Temu, ASOS.
Angles : Pratiques environnementales, conditions de travail, comparaison
des polémiques entre les marques.
Objectif : Analyse comparative des risques réputationnels.
```

---

#### Checklist Tour 3

| Critère                           | Attendu | Résultat |
| --------------------------------- | ------- | -------- |
| Acteurs ajoutés (Temu, ASOS)      | ☐       |          |
| **Drift détecté (élargissement)** | ☐       |          |
| **Reference Subject mis à jour**  | ☐       |          |
| Nouveaux acteurs dans Ref Subject | ☐       |          |
| Dimension comparative mentionnée  | ☐       |          |

---

### Tour 4 - Précision Géographique

**User Input:**

```
Concentre-toi sur l'Europe et les États-Unis, pas l'Asie
```

---

#### Comportement Attendu

**Détection:**

- ⚠️ Scope Drift détecté (précision géographique)
- Périmètre : Europe + USA
- Exclusion : Asie

**Actions requises:**

1. **Mettre à jour Reference Subject** (géographie)

**Reference Subject v4 attendu:**

```
Veille réputationnelle négative comparative - Fast Fashion
Focus : Controverses, bad buzz, critiques sur Shein, Temu et ASOS.
Acteurs prioritaires : Shein, Temu, ASOS.
Périmètre géographique : Europe et États-Unis (exclure Asie).
Angles : Pratiques environnementales, conditions de travail, couverture
médiatique occidentale.
```

---

#### Checklist Tour 4

| Critère                               | Attendu | Résultat |
| ------------------------------------- | ------- | -------- |
| **Drift détecté (géographie)**        | ☐       |          |
| **Reference Subject mis à jour**      | ☐       |          |
| Géographie explicite dans Ref Subject | ☐       |          |
| Exclusion Asie mentionnée             | ☐       |          |

---

## 📊 Évolution du Reference Subject

| Version | Déclencheur              | Changements             |
| ------- | ------------------------ | ----------------------- |
| v1      | Classification initiale  | Scope général           |
| v2      | "controverses, bad buzz" | → Réputationnel négatif |
| v3      | "+Temu, ASOS"            | + Acteurs, comparatif   |
| v4      | "Europe, USA, pas Asie"  | + Géographie            |

---

## 🔍 Signaux de Drift à Détecter

### Expressions d'élargissement

```
"j'aimerais aussi..."
"on pourrait ajouter..."
"et également..."
"en plus de ça..."
```

### Expressions de rétrécissement

```
"finalement..."
"en fait, concentrons-nous sur..."
"uniquement..."
"seulement..."
"pas besoin de..."
```

### Expressions de changement d'angle

```
"plutôt sous l'angle..."
"je m'intéresse surtout à..."
"le plus important c'est..."
"ce qui m'intéresse vraiment..."
```

---

## 📊 Grille d'Évaluation Finale

| Tour | Scope Change   | Drift Détecté | Ref Subject Updated | Classification MAJ |
| ---- | -------------- | ------------- | ------------------- | ------------------ |
| 1    | Non            | N/A           | ☐ Initial créé      | N/A                |
| 2    | ☐ → Réputation | ☐             | ☐ v2                | ☐ → REPUTATIONAL   |
| 3    | ☐ + Acteurs    | ☐             | ☐ v3                | N/A                |
| 4    | ☐ + Géographie | ☐             | ☐ v4                | N/A                |

---

## 🚨 Critères Bloquants

| Critère                                             | Verdict           |
| --------------------------------------------------- | ----------------- |
| Pas de mise à jour Reference Subject après drift    | ❌ ÉCHEC CRITIQUE |
| Pas de reclassification quand nécessaire            | ❌ ÉCHEC CRITIQUE |
| Reference Subject non synchronisé avec scope actuel | ❌ ÉCHEC MAJEUR   |
| Ignorer les précisions géographiques                | ❌ ÉCHEC MAJEUR   |

---

## 📜 Historique des Exécutions

| Date | Version Prompt | Résultat | Notes |
| ---- | -------------- | -------- | ----- |
|      |                |          |       |

---

## 📎 Captures d'Écran

_Ajouter les captures après exécution_

---

**CDT 4 - Scope Drift Detection v1.0**
