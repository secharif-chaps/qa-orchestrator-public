# 🧪 CDT 7 - Reference Subject Systématique

## Informations

| Champ             | Valeur                                                  |
| ----------------- | ------------------------------------------------------- |
| **Version**       | 1.0                                                     |
| **Date**          | 12/12/2025                                              |
| **Prompt aligné** | v2.8                                                    |
| **Priorité**      | 🟠 Important                                            |
| **Focus**         | Triggers de génération/mise à jour du Reference Subject |

---

## 🎯 Objectif du Test

Vérifier que le Reference Subject est **TOUJOURS** généré/mis à jour aux moments obligatoires définis en v2.8.

### Triggers Obligatoires

| Moment                   | Trigger                | Obligatoire |
| ------------------------ | ---------------------- | ----------- |
| Après classification     | Premier appel          | **OUI**     |
| Après batch acteurs (2+) | Consolidation          | OUI         |
| Après batch sources (2+) | Consolidation          | OUI         |
| Changement de scope      | Drift detection        | **OUI**     |
| Précision géographique   | Enrichissement         | OUI         |
| Validation finale        | "c'est bon", "parfait" | OUI         |

---

## 📋 Conversation de Test

### Contexte

Conversation complète pour valider tous les triggers.

**userLanguage:** `fr`

---

### Tour 1 - Classification

**User Input:**

```
Je veux surveiller le marché des batteries électriques en Asie
```

**Classification attendue:** `COMMERCIAL` ou `TECHNOLOGICAL`

---

#### Comportement Attendu Tour 1

**Tools attendus:**

1. `Tool_WatchFile_Classify`
2. `Tool_WatchFile_Rename`
3. **`Tool_WatchFile_BuilderReferenceSubject`** ← OBLIGATOIRE

**Reference Subject v1:**

```
Veille sur le marché des batteries électriques en Asie.
Objectif : Identification des opportunités commerciales et des acteurs clés.
Périmètre géographique : Asie.
```

---

#### Checklist Tour 1

| Critère                      | Attendu | Résultat |
| ---------------------------- | ------- | -------- |
| Classification effectuée     | ☐       |          |
| **Reference Subject généré** | ☐       |          |
| Géographie "Asie" incluse    | ☐       |          |

---

### Tour 2 - Ajout Batch Acteurs (3)

**User Input:**

```
Ajoute CATL, BYD et Panasonic comme acteurs
```

---

#### Comportement Attendu Tour 2

**Tools attendus:**

1. `Tool_WatchFile_BuilderActor` × 3
2. **`Tool_WatchFile_BuilderReferenceSubject`** ← OBLIGATOIRE (batch 2+)

**Reference Subject v2:**

```
Veille sur le marché des batteries électriques en Asie.
Objectif : Identification des opportunités commerciales et des acteurs clés.
Périmètre géographique : Asie.
Acteurs prioritaires : CATL, BYD, Panasonic.
```

---

#### Checklist Tour 2

| Critère                          | Attendu | Résultat |
| -------------------------------- | ------- | -------- |
| 3 acteurs ajoutés                | ☐       |          |
| **Reference Subject mis à jour** | ☐       |          |
| Acteurs listés dans Ref Subject  | ☐       |          |

---

### Tour 3 - Précision Géographique

**User Input:**

```
Concentre-toi sur la Chine et le Japon
```

---

#### Comportement Attendu Tour 3

**Tools attendus:**

1. **`Tool_WatchFile_BuilderReferenceSubject`** ← OBLIGATOIRE (géographie)

**Reference Subject v3:**

```
Veille sur le marché des batteries électriques.
Objectif : Identification des opportunités commerciales et des acteurs clés.
Périmètre géographique : Chine et Japon.
Acteurs prioritaires : CATL, BYD, Panasonic.
```

---

#### Checklist Tour 3

| Critère                              | Attendu | Résultat |
| ------------------------------------ | ------- | -------- |
| **Reference Subject mis à jour**     | ☐       |          |
| Géographie précisée "Chine et Japon" | ☐       |          |
| "Asie" remplacé par pays spécifiques | ☐       |          |

---

### Tour 4 - Ajout Sources

**User Input:**

```
Ajoute comme sources : Nikkei Asia, South China Morning Post et le blog de BloombergNEF
```

---

#### Comportement Attendu Tour 4

**Tools attendus:**

1. `Tool_WatchFile_BuilderSource` × 3
2. **`Tool_WatchFile_BuilderReferenceSubject`** ← OBLIGATOIRE (batch 2+)

**Reference Subject v4:**

```
Veille sur le marché des batteries électriques.
Objectif : Identification des opportunités commerciales et des acteurs clés.
Périmètre géographique : Chine et Japon.
Acteurs prioritaires : CATL, BYD, Panasonic.
Sources : Médias économiques asiatiques (Nikkei Asia, SCMP), analyse énergie (BloombergNEF).
```

---

#### Checklist Tour 4

| Critère                              | Attendu | Résultat |
| ------------------------------------ | ------- | -------- |
| 3 sources ajoutées                   | ☐       |          |
| **Reference Subject mis à jour**     | ☐       |          |
| Sources mentionnées dans Ref Subject | ☐       |          |

---

### Tour 5 - Validation Finale

**User Input:**

```
C'est parfait, on valide
```

---

#### Comportement Attendu Tour 5

**Tools attendus:**

1. **`Tool_WatchFile_BuilderReferenceSubject`** ← OBLIGATOIRE (validation finale)
2. Récapitulatif complet
3. Proposition d'enrichissement

**Reference Subject v5 (final):**

```
Veille commerciale - Marché des batteries électriques (Chine, Japon)

Objectif : Identification des opportunités commerciales, suivi des acteurs clés
et des tendances du marché des batteries pour véhicules électriques.

Acteurs prioritaires :
- CATL (Contemporary Amperex Technology) - Leader mondial, Chine
- BYD - Constructeur intégré, Chine
- Panasonic - Partenaire Tesla, Japon

Périmètre géographique : Chine et Japon.

Thèmes clés :
- Capacités de production et expansions
- Innovations technologiques (chimie, densité énergétique)
- Partenariats et contrats avec constructeurs automobiles
- Prix des matières premières (lithium, cobalt, nickel)
- Politiques gouvernementales et subventions

Critères de pertinence :
- Document mentionne au moins un acteur prioritaire
- Information sur le marché batteries/EV en Asie
- Actualité datant de moins de 6 mois
```

---

#### Checklist Tour 5

| Critère                         | Attendu | Résultat |
| ------------------------------- | ------- | -------- |
| **Reference Subject consolidé** | ☐       |          |
| Version complète et détaillée   | ☐       |          |
| Récapitulatif fourni            | ☐       |          |
| Proposition enrichissement      | ☐       |          |

---

## 📊 Évolution du Reference Subject

| Version | Tour | Déclencheur          | Contenu Ajouté                |
| ------- | ---- | -------------------- | ----------------------------- |
| v1      | 1    | Classification       | Sujet, objectif, géo initiale |
| v2      | 2    | Batch acteurs (3)    | Liste acteurs                 |
| v3      | 3    | Précision géographie | Chine, Japon                  |
| v4      | 4    | Batch sources (3)    | Liste sources                 |
| v5      | 5    | Validation finale    | Thèmes, critères              |

---

## 📊 Grille d'Évaluation Finale

| Tour | Trigger             | Ref Subject Appelé | Obligatoire |
| ---- | ------------------- | ------------------ | ----------- |
| 1    | Classification      | ☐                  | **OUI**     |
| 2    | Batch acteurs (3)   | ☐                  | OUI         |
| 3    | Géographie précisée | ☐                  | OUI         |
| 4    | Batch sources (3)   | ☐                  | OUI         |
| 5    | Validation finale   | ☐                  | OUI         |

**Score:** \_\_\_/5 triggers respectés

---

## 🔍 Vérification du Contenu

### Tour 5 - Le Reference Subject final doit contenir :

| Élément                 | Présent | Contenu              |
| ----------------------- | ------- | -------------------- |
| Titre descriptif        | ☐       |                      |
| Objectif                | ☐       |                      |
| Acteurs prioritaires    | ☐       | CATL, BYD, Panasonic |
| Géographie              | ☐       | Chine, Japon         |
| Sources contextualisées | ☐       |                      |
| Thèmes/mots-clés        | ☐       |                      |
| Critères de pertinence  | ☐       |                      |

---

## 🚨 Critères Bloquants

| Critère                                       | Verdict           |
| --------------------------------------------- | ----------------- |
| Reference Subject absent après classification | ❌ ÉCHEC CRITIQUE |
| Pas de MAJ après batch acteurs/sources        | ❌ ÉCHEC MAJEUR   |
| Pas de MAJ après changement géographie        | ❌ ÉCHEC MAJEUR   |
| Pas de version finale consolidée              | ❌ ÉCHEC MAJEUR   |
| Contenu non synchronisé avec config           | ❌ ÉCHEC MAJEUR   |

---

## 📜 Historique des Exécutions

| Date | Version Prompt | Triggers OK | Score | Notes |
| ---- | -------------- | ----------- | ----- | ----- |
|      |                |             | /5    |       |

---

## 📎 Captures d'Écran

_Ajouter les captures après exécution_

---

**CDT 7 - Reference Subject Systématique v1.0**
