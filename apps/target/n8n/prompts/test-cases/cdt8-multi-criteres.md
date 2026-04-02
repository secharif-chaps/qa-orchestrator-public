# 🧪 CDT 8 - Test Multi-Critères Complet

## Informations

| Champ             | Valeur                                       |
| ----------------- | -------------------------------------------- |
| **Version**       | 1.0                                          |
| **Date**          | 12/12/2025                                   |
| **Prompt aligné** | v2.8                                         |
| **Priorité**      | 🔴 Critique                                  |
| **Focus**         | Validation globale de tous les critères v2.8 |

---

## 🎯 Objectif du Test

Scénario complexe testant **simultanément** plusieurs critères v2.8 dans une conversation réaliste.

### Critères Testés

| #   | Critère                        | CDT Référence |
| --- | ------------------------------ | ------------- |
| 1   | Classification correcte        | CDT 1         |
| 2   | Actor Discovery proactif       | CDT 2         |
| 3   | Source ≠ Document              | CDT 3         |
| 4   | Scope Drift Detection          | CDT 4         |
| 5   | Reference Subject systématique | CDT 7         |
| 6   | Max 1 question par réponse     | -             |
| 7   | Outils internes masqués        | -             |

---

## 📋 Conversation de Test

### Contexte

Responsable RSE dans un groupe textile, besoin de veille ESG/réputation.

**userLanguage:** `fr`

---

### Tour 1 - Besoin Complexe Initial

**User Input:**

```
Je suis responsable RSE dans un groupe textile. Je dois surveiller les pratiques ESG de nos concurrents et les controverses du secteur pour notre rapport annuel.
```

**Analyse:**

- Rôle : Responsable RSE
- Sujet : Pratiques ESG textile + controverses
- Objectif : Rapport annuel
- Dualité : concurrents (COMPETITIVE) + controverses (REPUTATIONAL)

---

#### Comportement Attendu Tour 1

**Classification probable:** `REPUTATIONAL` (focus controverses/ESG)

**Tools attendus:**

1. `Tool_WatchFile_Classify` → probablement `reputational`
2. `Tool_WatchFile_Rename`
3. `Tool_WatchFile_BuilderReferenceSubject`
4. `Tool_WebSearch_Grounding` (discovery ESG textile)

**Message attendu (exemple):**

```
J'ai configuré votre veille comme **Veille Réputationnelle ESG - Secteur Textile**.

Pour identifier les acteurs clés à surveiller, pouvez-vous me préciser quels sont
vos principaux concurrents ?
```

OU (si discovery direct):

```
J'ai configuré votre veille comme **Veille Réputationnelle ESG - Secteur Textile**.

Les acteurs majeurs du secteur souvent scrutés sur les pratiques ESG incluent :
- **H&M** - Fast fashion, engagements durabilité
- **Zara/Inditex** - Supply chain, controverses usines
- **Primark** - Prix bas, questions éthiques

Souhaitez-vous surveiller certains de ces acteurs ?
```

---

#### Checklist Tour 1

| Critère                                                | Attendu | Résultat |
| ------------------------------------------------------ | ------- | -------- |
| Classification cohérente (REPUTATIONAL ou COMPETITIVE) | ☐       |          |
| Reference Subject généré                               | ☐       |          |
| Discovery lancé OU question concurrents                | ☐       |          |
| **Questions ≤ 1**                                      | ☐       |          |
| Outils masqués                                         | ☐       |          |

---

### Tour 2 - Fourniture d'Acteurs

**User Input:**

```
Nos principaux concurrents sont H&M, Zara et Primark. Je veux surtout voir les critiques et les rapports des ONG sur leurs pratiques.
```

---

#### Comportement Attendu Tour 2

**Tools attendus:**

1. `Tool_WatchFile_BuilderActor` × 3 (H&M, Zara, Primark)
2. `Tool_WebSearch_Grounding` (discovery ONG, watchdogs ESG)
3. Proposition d'acteurs découverts

**Message attendu:**

```
J'ai ajouté **H&M**, **Zara** et **Primark** à votre surveillance.

J'ai également identifié des acteurs clés qui analysent leurs pratiques ESG :
- **Fashion Revolution** - ONG mode éthique, classements transparence
- **Clean Clothes Campaign** - Droits des travailleurs textile
- **Remake** - Advocacy durabilité mode
- **Public Eye** - Investigations supply chain

Souhaitez-vous que j'en ajoute certains ?
```

---

#### Checklist Tour 2

| Critère                                    | Attendu | Résultat |
| ------------------------------------------ | ------- | -------- |
| 3 concurrents ajoutés (H&M, Zara, Primark) | ☐       |          |
| **Actor Discovery automatique** (ONG)      | ☐       |          |
| Acteurs découverts proposés                | ☐       |          |
| **Questions ≤ 1**                          | ☐       |          |

---

### Tour 3 - Confirmation + Demande Sources

**User Input:**

```
Oui ajoute Fashion Revolution. Trouve-moi des sources fiables pour cette veille.
```

---

#### Comportement Attendu Tour 3

**Tools attendus:**

1. `Tool_WatchFile_BuilderActor` (Fashion Revolution)
2. `Tool_WebSearch_Grounding` (sources ESG textile)
3. Proposition de **SOURCES** (pas de documents!)
4. `Tool_WatchFile_BuilderReferenceSubject` (après batch)

**Sources ACCEPTABLES:**

```
- *The Guardian* - Sustainable Business (theguardian.com/sustainable-business)
- *Fashion Revolution* - Reports (fashionrevolution.org)
- *Business & Human Rights Resource Centre* (business-humanrights.org)
- *Clean Clothes Campaign* (cleanclothes.org)
```

**Sources NON ACCEPTABLES:**

```
❌ https://theguardian.com/fashion/2024/dec/10/hm-greenwashing-scandal
❌ https://fashionrevolution.org/resources/fashion-transparency-index-2024.pdf
```

---

#### Checklist Tour 3

| Critère                                         | Attendu | Résultat |
| ----------------------------------------------- | ------- | -------- |
| Fashion Revolution ajouté                       | ☐       |          |
| **Sources proposées = SOURCES (pas documents)** | ☐       |          |
| Aucune URL d'article/PDF spécifique             | ☐       |          |
| Reference Subject mis à jour                    | ☐       |          |
| **Questions ≤ 1**                               | ☐       |          |

---

### Tour 4 - Changement de Scope (Drift)

**User Input:**

```
Ajoute aussi un focus sur l'Asie du Sud-Est où sont les usines
```

---

#### Comportement Attendu Tour 4

**Drift détecté:** Ajout dimension géographique

**Tools attendus:**

1. **`Tool_WatchFile_BuilderReferenceSubject`** (géographie ajoutée)
2. Éventuellement suggestion sources locales

**Message attendu:**

```
J'ai ajouté le focus sur l'Asie du Sud-Est à votre veille.

Souhaitez-vous que je recherche des sources locales spécialisées dans les
conditions de travail en Asie (médias vietnamiens, bangladais, etc.) ?
```

**Reference Subject mis à jour:**

```
Veille Réputationnelle ESG - Secteur Textile

Acteurs prioritaires : H&M, Zara, Primark, Fashion Revolution
Focus : Pratiques ESG, controverses, rapports ONG
Périmètre géographique : Global avec focus Asie du Sud-Est (supply chain)
...
```

---

#### Checklist Tour 4

| Critère                                       | Attendu | Résultat |
| --------------------------------------------- | ------- | -------- |
| **Scope Drift détecté**                       | ☐       |          |
| **Reference Subject mis à jour**              | ☐       |          |
| Géographie "Asie du Sud-Est" dans Ref Subject | ☐       |          |
| Suggestion sources locales                    | ☐       |          |
| **Questions ≤ 1**                             | ☐       |          |

---

### Tour 5 - Validation Finale

**User Input:**

```
C'est parfait, on valide comme ça
```

---

#### Comportement Attendu Tour 5

**Tools attendus:**

1. `Tool_WatchFile_BuilderReferenceSubject` (consolidation finale)
2. Récapitulatif complet
3. Proposition enrichissement (sans dire "DeepSearch")

**Message attendu:**

```
Votre veille est configurée :

**Type :** Veille Réputationnelle ESG - Secteur Textile
**Acteurs surveillés :**
- H&M, Zara, Primark (concurrents)
- Fashion Revolution (ONG watchdog)

**Sources configurées :**
- *The Guardian* - Sustainable Business
- *Fashion Revolution* - Reports
- *Business & Human Rights Resource Centre*

**Focus :** Pratiques ESG, controverses, supply chain Asie du Sud-Est

Souhaitez-vous que je recherche des sources et acteurs complémentaires
pour enrichir cette veille ?
```

---

#### Checklist Tour 5

| Critère                             | Attendu | Résultat |
| ----------------------------------- | ------- | -------- |
| Reference Subject consolidé (final) | ☐       |          |
| Récapitulatif complet               | ☐       |          |
| Proposition enrichissement          | ☐       |          |
| Pas de mention "DeepSearch"         | ☐       |          |

---

## 📊 Grille d'Évaluation Multi-Critères

### Par Tour

| Tour | Classification | Discovery | Source≠Doc | Ref Subject | Scope Drift | Q≤1 | Outils Masqués |
| ---- | -------------- | --------- | ---------- | ----------- | ----------- | --- | -------------- |
| 1    | ☐              | ☐         | N/A        | ☐           | N/A         | ☐   | ☐              |
| 2    | N/A            | ☐         | N/A        | N/A         | N/A         | ☐   | ☐              |
| 3    | N/A            | N/A       | ☐          | ☐           | N/A         | ☐   | ☐              |
| 4    | N/A            | N/A       | N/A        | ☐           | ☐           | ☐   | ☐              |
| 5    | N/A            | N/A       | N/A        | ☐           | N/A         | ☐   | ☐              |

### Score Global

| Critère                 | Tours Concernés | Réussis | Score |
| ----------------------- | --------------- | ------- | ----- |
| Classification correcte | 1               | /1      |       |
| Actor Discovery         | 1, 2            | /2      |       |
| Source ≠ Document       | 3               | /1      |       |
| Scope Drift Detection   | 4               | /1      |       |
| Reference Subject MAJ   | 1, 3, 4, 5      | /4      |       |
| Max 1 question          | 1-5             | /5      |       |
| Outils masqués          | 1-5             | /5      |       |

**Score Total:** \_\_\_/19

---

## 🚨 Critères Bloquants

| Critère                         | Tour | Verdict  |
| ------------------------------- | ---- | -------- |
| Mauvaise classification         | 1    | ❌ ÉCHEC |
| Pas de Discovery après acteurs  | 2    | ❌ ÉCHEC |
| URLs d'articles comme sources   | 3    | ❌ ÉCHEC |
| Drift non détecté               | 4    | ❌ ÉCHEC |
| Reference Subject jamais généré | 1    | ❌ ÉCHEC |
| 2+ questions dans une réponse   | Tout | ❌ ÉCHEC |
| Mention WebSearch/DeepSearch    | Tout | ❌ ÉCHEC |

---

## 📈 Seuils de Validation

| Score    | Verdict              |
| -------- | -------------------- |
| 19/19    | ✅ PASS PARFAIT      |
| 16-18/19 | ✅ PASS              |
| 12-15/19 | ⚠️ PASS CONDITIONNEL |
| < 12/19  | ❌ FAIL              |

**Note:** Un seul critère bloquant = FAIL immédiat

---

## 📜 Historique des Exécutions

| Date | Version Prompt | Score | Verdict | Notes |
| ---- | -------------- | ----- | ------- | ----- |
|      |                | /19   |         |       |

---

## 📎 Captures d'Écran

_Ajouter les captures après exécution_

---

**CDT 8 - Multi-Critères v1.0**
