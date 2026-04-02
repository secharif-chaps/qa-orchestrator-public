# N8N Prompts

Prompts utilisés par les workflows N8N de l'agent Chaps-e. Toutes les versions sont conservées pour faciliter les diffs en cas de régression.

## Structure

```
prompts/
├── 1-chat-assistant/          # Prompt principal du ChatAssistant
├── 2-watchfile-builder-add-actor/  # Validation des acteurs
├── 3-watchfile-builder-add-source/ # Validation et mapping des sources
├── 4-reference-subject/       # Génération et validation du sujet de référence
├── 5-classify-watchfile/      # Classification des WatchFiles
├── 6-deepsearch/              # Extraction d'entités DeepSearch
├── 6a-deepsearch-strategic-questions/ # Génération de questions stratégiques
├── 6b-deepsearch-search-query-generator/ # Génération de requêtes de recherche
├── 6c-deepsearch-relevance-scorer/ # Scoring de pertinence des résultats
├── 7-validate-document/       # Validation des documents
├── 8-generate-document-summary/ # Génération de résumés
├── 9-extract-document-events/ # Extraction d'événements
└── test-cases/                # Cas de test conversationnels
```

## Version Map (versions en production)

| Répertoire                            | Dernière version | Workflow                             | Noeud                                 | Statut                   |
| ------------------------------------- | ---------------- | ------------------------------------ | ------------------------------------- | ------------------------ |
| 1-chat-assistant/                     | **v3.7**         | 2-chat-session-message.json          | LLM_Agent_ChatAssistant               | OK (extrait du workflow) |
| 2-watchfile-builder-add-actor/        | v1.0             | 3a-watchfile-builder-add-actor.json  | LLM_Agent_ValidateActorRelevance      | OK                       |
| 2-watchfile-builder-add-actor/        | v1.0             | 3a-watchfile-builder-add-actor.json  | LLM_Agent_ValidateActorType           | OK                       |
| 3-watchfile-builder-add-source/       | v1.0             | 3b-watchfile-builder-add-source.json | LLM_Agent_MapSourceBakus              | OK                       |
| 3-watchfile-builder-add-source/       | v1.0             | 3b-watchfile-builder-add-source.json | LLM_Agent_ValidateSourceRelevance     | OK                       |
| 3-watchfile-builder-add-source/       | v1.0             | 3b-watchfile-builder-add-source.json | LLM_Agent_MapSourceActor              | OK (extrait du workflow) |
| 4-reference-subject/                  | v4.2             | 3c-...update-reference-subject.json  | Agent_Generator_ReferenceSubject      | OK                       |
| 4-reference-subject/                  | v1.0             | 3c-...update-reference-subject.json  | Agent_Validator_ReferenceSubject      | OK (extrait du workflow) |
| 5-classify-watchfile/                 | v2.4             | 5-tool-classify-watchfile.json       | LLM_Agent_ClassifyWatchFile           | OK                       |
| 6-deepsearch/                         | v1.0             | 6-tool-deepsearch-agent.json         | LLM_Agent_ExtractEntities             | OK                       |
| 6a-deepsearch-strategic-questions/    | v2.0             | 6a-...strategic-question.json        | LLM_StrategicQuestions_GeneratorAgent | OK                       |
| 6b-deepsearch-search-query-generator/ | v1.0             | 6b-deepsearch-searchquery.json       | LLM_SearchQuery_GeneratorAgent        | OK                       |
| 6c-deepsearch-relevance-scorer/       | v1.0             | 6c-...searchqueryexecute.json        | LLM_SearchResults_RelevanceScorer     | OK                       |
| 7-validate-document/                  | v1.0             | validate-document.json               | LLM_Agent_ValidateDocument            | OK                       |
| 8-generate-document-summary/          | v1.0             | generate-document-summary.json       | LLM_Agent_GenerateSummary             | OK                       |
| 9-extract-document-events/            | v1.0             | extract-document-events.json         | LLM_Agent_ExtractEventsActors         | OK                       |

## Divergences et notes

### ChatAssistant v3.6 vs v3.7

La v3.6 (dernière version dans basiltools) divergeait du prompt en production dans le workflow `2-chat-session-message.json`. La v3.7 a été extraite directement du workflow en production et fait désormais foi comme version de référence.

### ValidateActorRelevance v1.0 vs workflow

Le fichier `ValidateActorRelevance-Prompt-v1.0.md` contient un bloc "CRITICAL LANGUAGE REQUIREMENT" (5 lignes sur la génération de texte dans la langue de l'utilisateur) qui est **absent** du workflow `3a-watchfile-builder-add-actor.json` (noeud `LLM_Agent_ValidateActorRelevance`). Le reste du prompt est identique. Le fichier versionné contient donc une instruction supplémentaire par rapport à la production.

### Prompts extraits des workflows (sans historique dans basiltools)

- **MapSourceActor-Prompt-v1.0.md** : Extrait du workflow `3b-watchfile-builder-add-source.json`, noeud `LLM_Agent_MapSourceActor`. Associe une source au meilleur acteur par matching de domaine ou similarité de label.
- **ReferenceSubjectValidator-Prompt-v1.0.md** : Extrait du workflow `3c-watchfile-builder-update-reference-subject.json`, noeud `Agent_Validator_ReferenceSubject`. Evalue et optimise les modifications du sujet de référence.

## Convention de nommage

- Répertoires : `<n>-<nom-kebab-case>/` (numéro = workflow correspondant)
- Fichiers : `<NomPrompt>-Prompt-v<major>.<minor>.md` (nom original conservé)
- Versions : semver simplifié (major = breaking change, minor = ajustement)

## Workflow de modification

1. Modifier le fichier `.md` dans le répertoire correspondant avec un nouveau numéro de version
2. Mettre à jour la version map ci-dessus
3. Copier le contenu dans le noeud N8N correspondant
4. Tester avec les cas de test dans `test-cases/`
