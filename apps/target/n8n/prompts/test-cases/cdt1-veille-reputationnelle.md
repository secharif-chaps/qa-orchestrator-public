# 🧪 CDT 1 - Veille Réputationnelle

## Informations

| Champ             | Valeur                           |
| ----------------- | -------------------------------- |
| **Version**       | 1.0                              |
| **Date**          | 12/12/2025                       |
| **Prompt aligné** | v2.8                             |
| **Priorité**      | 🔴 Critique                      |
| **Focus**         | Type REPUTATIONAL (nouveau v2.8) |

---

## 🎯 Objectif du Test

Vérifier que l'IA classifie correctement une demande de veille réputationnelle (positive, négative ou globale) avec le nouveau type `REPUTATIONAL` introduit en v2.8.

### Critères Spécifiques

| Critère        | Description                                           |
| -------------- | ----------------------------------------------------- |
| Classification | Doit être `REPUTATIONAL` (pas `COMPETITIVE`)          |
| Sous-type      | Détection positive/négative/globale                   |
| Nommage        | Format "Veille Réputationnelle [Tonalité] - [Entité]" |
| Discovery      | Proposition d'ONG, watchdogs, critiques               |

---

## 📋 Conversation de Test

### Contexte

Utilisateur souhaitant surveiller les actualités négatives sur Shein.

**userLanguage:** `fr`

---

### Tour 1 - Message Initial

**User Input:**

```
Je veux surveiller les actualités négatives sur le groupe Shein
```

**Éléments détectés:**

- [x] Sujet : Shein (entité cible)
- [x] Objectif : Actualités négatives (réputation)
- [x] Tonalité : Négative
- [ ] Acteurs : Shein implicite
- [ ] Sources : Non spécifiées

---

#### Comportement Attendu

**Classification:** `REPUTATIONAL` (sous-type: Negative)

**Tools attendus:**

1. `Tool_WatchFile_Classify` → `reputational`
2. `Tool_WatchFile_Rename` → "Veille Réputationnelle Négative - Shein"
3. `Tool_WatchFile_BuilderReferenceSubject` (obligatoire)
4. `Tool_WatchFile_BuilderActor` (Shein)
5. `Tool_WebSearch_Grounding` (discovery: "Shein critics controversies NGOs watchdogs")

**Message attendu (exemple):**

```
J'ai configuré votre veille comme **Veille Réputationnelle Négative - Shein**.

J'ai ajouté **Shein** comme acteur principal à surveiller.

D'après mes recherches, d'autres acteurs pertinents pour surveiller les controverses Shein :
- **Fashion Revolution** - ONG mode éthique
- **Public Eye** - ONG suisse d'investigation
- **Remake** - Advocacy durabilité mode

Souhaitez-vous que j'en ajoute certains ?
```

---

#### Checklist Tour 1

| Critère                                           | Attendu | Résultat |
| ------------------------------------------------- | ------- | -------- |
| Classification = REPUTATIONAL                     | ☐       |          |
| Sous-type = Negative                              | ☐       |          |
| Titre = "Veille Réputationnelle Négative - Shein" | ☐       |          |
| Reference Subject généré                          | ☐       |          |
| Shein ajouté comme acteur                         | ☐       |          |
| Discovery lancé (ONG, watchdogs)                  | ☐       |          |
| Acteurs découverts proposés                       | ☐       |          |
| Questions ≤ 1                                     | ☐       |          |

**❌ FAIL si:**

- Classification = COMPETITIVE
- Titre = "Veille Concurrentielle - Shein"
- Pas de Reference Subject généré
- Pas de proposition d'acteurs découverts

---

### Tour 2 - Confirmation Acteurs + Demande Sources

**User Input:**

```
Oui ajoute Fashion Revolution et Public Eye. Trouve-moi aussi des sources pour cette veille.
```

---

#### Comportement Attendu

**Tools attendus:**

1. `Tool_WatchFile_BuilderActor` × 2 (Fashion Revolution, Public Eye)
2. `Tool_WebSearch_Grounding` (sources réputation/controverses)
3. Proposition de SOURCES (pas de documents!)

**Sources ACCEPTABLES:**

```
- *The Guardian* - Section Fashion (theguardian.com/fashion)
- *Bon Pote* - Blog environnement (bonpote.com)
- *Fashion Revolution* - Rapports (fashionrevolution.org)
- *Public Eye* - Investigations (publiceye.ch)
```

**Sources NON ACCEPTABLES (documents):**

```
❌ https://www.nouvelobs.com/edito/20251211.OBS110559/shein-les-dessous-de-la-fast-fashion.html
❌ https://bonpote.com/shein-la-marque-dultra-fast-fashion-qui-envahit-le-monde/
❌ https://www.bbc.com/news/articles/cy5k3v6x6vxo
```

---

#### Checklist Tour 2

| Critère                                          | Attendu | Résultat |
| ------------------------------------------------ | ------- | -------- |
| Acteurs ajoutés (Fashion Revolution, Public Eye) | ☐       |          |
| Sources proposées = SOURCES (pas documents)      | ☐       |          |
| Aucune URL d'article spécifique                  | ☐       |          |
| Questions ≤ 1                                    | ☐       |          |

---

### Tour 3 - Validation

**User Input:**

```
Parfait, ajoute The Guardian et Bon Pote
```

---

#### Comportement Attendu

**Tools attendus:**

1. `Tool_WatchFile_BuilderSource` × 2
2. `Tool_WatchFile_BuilderReferenceSubject` (mise à jour après batch)

**Message attendu:**

- Confirmation consolidée (1 message)
- Récapitulatif des sources ajoutées
- Proposition d'enrichissement

---

#### Checklist Tour 3

| Critère                               | Attendu | Résultat |
| ------------------------------------- | ------- | -------- |
| Sources ajoutées avec URLs vérifiées  | ☐       |          |
| Reference Subject mis à jour          | ☐       |          |
| Confirmation consolidée (pas cascade) | ☐       |          |
| Proposition enrichissement            | ☐       |          |

---

## 📊 Grille d'Évaluation Finale

| Tour | Classification | Actor Discovery | Source≠Doc   | Ref Subject  | Questions ≤1 |
| ---- | -------------- | --------------- | ------------ | ------------ | ------------ |
| 1    | ☐ REPUTATIONAL | ☐ Proposé       | N/A          | ☐ Généré     | ☐            |
| 2    | N/A            | ☐ Ajoutés       | ☐ Sources OK | N/A          | ☐            |
| 3    | N/A            | N/A             | ☐ Ajoutées   | ☐ Mis à jour | ☐            |

---

## 🚨 Critères Bloquants

| Critère                                    | Verdict           |
| ------------------------------------------ | ----------------- |
| Classification ≠ REPUTATIONAL              | ❌ ÉCHEC CRITIQUE |
| Proposer des URLs d'articles comme sources | ❌ ÉCHEC CRITIQUE |
| Pas de Discovery proposé                   | ❌ ÉCHEC MAJEUR   |
| Reference Subject absent                   | ❌ ÉCHEC MAJEUR   |

---

## 📜 Historique des Exécutions

| Date | Version Prompt | Résultat | Notes |
| ---- | -------------- | -------- | ----- |
|      |                |          |       |

---

## 📎 Captures d'Écran

_Ajouter les captures après exécution_

---

**CDT 1 - Veille Réputationnelle v1.0**
