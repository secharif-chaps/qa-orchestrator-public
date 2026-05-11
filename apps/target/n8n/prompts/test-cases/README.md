# 🧪 Cas de Test QA - Agent Conversationnel ChapsMind

## Informations Générales

| Champ                 | Valeur                              |
| --------------------- | ----------------------------------- |
| **Version Framework** | 4.0                                 |
| **Date MAJ**          | 12/12/2025                          |
| **Workflow testé**    | 2. Chat Session Message             |
| **Prompt aligné**     | ChatAssistant-WatchFile-Prompt-v2.8 |
| **LLM cible**         | GPT 4.1                             |

---

## 📁 Structure des Cas de Test

```
Cas de test/
├── README.md                          ← Ce fichier (index)
├── CDT 1 - Veille Réputationnelle/
│   └── CDT1-v1.0.md
├── CDT 2 - Actor Discovery/
│   └── CDT2-v1.0.md
├── CDT 3 - Source vs Document/
│   └── CDT3-v1.0.md
├── CDT 4 - Scope Drift/
│   └── CDT4-v1.0.md
├── CDT 5 - Contextual Probing/
│   └── CDT5-v1.0.md
├── CDT 6 - Self-Description/
│   └── CDT6-v1.0.md
├── CDT 7 - Reference Subject/
│   └── CDT7-v1.0.md
└── CDT 8 - Multi-Critères/
    └── CDT8-v1.0.md
```

---

## 📊 Index des Scénarios

| CDT | Nom                    | Focus Principal                 | Nouveauté v2.8 | Priorité     |
| --- | ---------------------- | ------------------------------- | -------------- | ------------ |
| 1   | Veille Réputationnelle | Type REPUTATIONAL               | ✅             | 🔴 Critique  |
| 2   | Actor Discovery        | Proposition proactive d'acteurs | ✅             | 🔴 Critique  |
| 3   | Source vs Document     | Anti-confusion URLs             | ✅             | 🔴 Critique  |
| 4   | Scope Drift            | Reference Subject update        | ✅             | 🔴 Critique  |
| 5   | Contextual Probing     | Géographie selon type           | ✅             | 🟠 Important |
| 6   | Self-Description       | Confidentialité                 | ✅             | 🟠 Important |
| 7   | Reference Subject      | Triggers systématiques          | ✅             | 🟠 Important |
| 8   | Multi-Critères         | Validation complète             | ✅             | 🔴 Critique  |

---

## 🎯 Règles Critiques à Tester (v2.8)

| Règle   | Description                                 | Statut v2.8 |
| ------- | ------------------------------------------- | ----------- |
| Rule 1  | **Max 1 question** par réponse              | Inchangé    |
| Rule 2  | Classifier le plus tôt possible             | Inchangé    |
| Rule 3  | Action immédiate sur données concrètes      | Inchangé    |
| Rule 4  | Acteurs ≠ Sources (taxonomie stricte)       | Renforcé    |
| Rule 5  | **Sources ≠ Documents** (URLs vérifiées)    | ⚠️ Renforcé |
| Rule 6  | Ne jamais exposer outils internes           | Inchangé    |
| Rule 7  | Jamais répéter une question                 | Inchangé    |
| Rule 8  | Pas d'intro répétitive                      | Inchangé    |
| Rule 9  | Confirmations consolidées                   | Inchangé    |
| Rule 10 | **Reference Subject - triggers explicites** | ⚠️ Renforcé |
| Rule 11 | Ground before suggesting                    | Inchangé    |
| Rule 12 | **Proactive Actor Discovery**               | ⚠️ Nouveau  |

---

## 🚨 Critères de Blocage (Showstoppers)

| #   | Critère Bloquant                                    | Sévérité    |
| --- | --------------------------------------------------- | ----------- |
| 1   | Classification COMPETITIVE pour veille réputation   | 🔴 CRITIQUE |
| 2   | URLs d'articles proposées comme sources             | 🔴 CRITIQUE |
| 3   | Pas de Discovery après ajout d'acteur               | 🔴 CRITIQUE |
| 4   | Reference Subject jamais généré                     | 🔴 CRITIQUE |
| 5   | Scope change sans update Ref Subject                | 🟠 MAJEUR   |
| 6   | Détails techniques exposés (Self-Description)       | 🟠 MAJEUR   |
| 7   | Géographie non demandée pour REGULATORY             | 🟠 MAJEUR   |
| 8   | 2+ questions dans une réponse                       | 🟠 MAJEUR   |
| 9   | Mention WebSearch/DeepSearch/Tool\_ à l'utilisateur | 🟠 MAJEUR   |
| 10  | Cascade de messages au lieu de consolidation        | 🟡 MINEUR   |

---

## 📈 Grille d'Évaluation Globale

Pour chaque réponse de l'IA, évaluer :

| Critère                        | ✅ Pass                                         | ❌ Fail                    |
| ------------------------------ | ----------------------------------------------- | -------------------------- |
| **Questions = 1 max**          | 0 ou 1 question                                 | 2+ questions               |
| **Classification correcte**    | Type cohérent avec le besoin                    | Mauvais type               |
| **REPUTATIONAL détecté**       | Classifié REPUTATIONAL si réputation/image/buzz | Classifié autre chose      |
| **Actor Discovery**            | Propose d'autres acteurs après ajout            | N'en propose pas           |
| **Source ≠ Document**          | URLs de sites/canaux, pas d'articles            | URLs d'articles            |
| **Reference Subject généré**   | Après classification et changements de scope    | Absent ou non mis à jour   |
| **Scope Drift détecté**        | Reference Subject mis à jour si scope change    | Pas de mise à jour         |
| **Géographie demandée**        | Pour REGULATORY/COMMERCIAL si pertinent         | Jamais demandée            |
| **Self-Description générique** | Réponse vague, orientée valeur                  | Détails techniques exposés |
| **Outils masqués**             | Aucune mention WebSearch/DeepSearch/Tool\_      | Expose les outils internes |

---

## 🔧 Configuration Test Environment

### Variables N8N

```json
{
  "userLanguage": "fr",
  "watchFileId": "[UUID test]",
  "watchFile": {
    "name": null,
    "titleManuallySetByUser": false,
    "classificationType": null,
    "actors": [],
    "sources": [],
    "referenceSubject": null
  },
  "metadata": {
    "source_types": [
      "website",
      "linkedin",
      "twitter",
      "rss",
      "blog",
      "news",
      "patent_db",
      "legal_db",
      "research_db",
      "regulatory",
      "market_report"
    ]
  }
}
```

### Valeurs de Classification v2.8

```json
{
  "allowedTypes": [
    "competitive",
    "regulatory",
    "technological",
    "commercial",
    "strategic",
    "reputational"
  ]
}
```

---

## 📝 Template Rapport de Test

```markdown
## Rapport Test QA - [Date]

### Scénario testé : CDT [X] - [Nom]

### Prompt version : v2.8

### LLM : GPT 4.1

### Résultats par tour

| Tour | Critère Principal | Résultat        | Commentaire |
| ---- | ----------------- | --------------- | ----------- |
| 1    | ...               | ☐ Pass / ☐ Fail |             |
| 2    | ...               | ☐ Pass / ☐ Fail |             |

### Verdict

- [ ] ✅ PASS - Tous critères respectés
- [ ] ⚠️ PASS CONDITIONNEL - Problèmes mineurs
- [ ] ❌ FAIL - Critère bloquant identifié

### Problèmes identifiés

1. ...

### Captures d'écran

[Joindre screenshots]
```

---

## 📜 Historique des Versions

| Version | Date       | Changements                                     |
| ------- | ---------- | ----------------------------------------------- |
| 4.0     | 12/12/2025 | Restructuration en répertoires, alignement v2.8 |
| 3.0     | 10/12/2025 | Version monolithique alignée v2.6               |

---

**Framework QA ChapsMind Target**
