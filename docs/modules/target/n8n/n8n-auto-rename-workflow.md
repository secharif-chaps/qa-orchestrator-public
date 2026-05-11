# Workflow N8N : Renommage Automatique des WatchFiles

## 🎯 Objectif

Ce workflow implémente le **renommage automatique des watchfiles** basé sur l'analyse des conversations. Quand une conversation dépasse **3 messages**, le système génère automatiquement un titre descriptif et professionnel pour le watchfile.

## 📋 Architecture

### Workflow Principal : `conversation-monitor-auto-rename.json`

```text
RabbitMQ Trigger → Unserialize Data → Filter Conversation Monitor → Check Message Count > 3 → Generate New Title → Execute Rename → Send Notification
```

### Flux de Données

1. **Déclenchement** : Écoute la queue `agent_commands`
2. **Filtrage** : Ne traite que les messages `N8nConversationMonitor`
3. **Vérification** : Compte les messages (> 3)
4. **Génération** : IA génère un nouveau titre basé sur l'historique
5. **Exécution** : Renomme le watchfile via le workflow existant
6. **Notification** : Envoie une confirmation

## 🔧 Composants

### 1. **RabbitMQ Trigger**

- Queue : `agent_commands`
- Écoute tous les messages de commande

### 2. **Unserialize Command Data**

- Parse le JSON reçu
- Extrait : `command_name`, `data`, `watchFileId`, `responseType`

### 3. **Filter Conversation Monitor**

- Condition : `command_name == "N8nConversationMonitor"`
- Filtre uniquement les messages de monitoring de conversation

### 4. **Check Message Count > 3**

- Condition : `messageCount > 3`
- Déclenche le renommage automatique

### 5. **Generate New Title**

- **Modèle** : GPT-4.1 Sweden
- **Contexte** : Historique des 5 derniers messages + titre actuel
- **Prompt** : Génère un titre descriptif et professionnel
- **Exemples** :
  - "Competitive Intelligence: Tech Startup Landscape"
  - "Market Trends: Renewable Energy Sector"
  - "Technology Innovation: AI in Healthcare"

### 6. **Execute Rename Watchfile**

- **Workflow** : `Rename Watchfile automaticly`
- **Paramètres** : `watchFileId`, `newTitle`, `BusNameStamp`, `RouterContextStamp`

### 7. **Send Auto Rename Notification**

- Queue : `agent_responses`
- Type : `auto_rename_notification`
- Contenu : Confirmation du renommage

## 🧪 Workflow de Test : `test-conversation-monitor.json`

### Objectif

Simuler un message de conversation monitor pour tester le système.

### Données de Test

```json
{
  "name": "N8nConversationMonitor",
  "data": {
    "messageCount": 4,
    "conversationHistory": [
      {
        "role": "user",
        "content": "Je veux surveiller les startups dans le secteur de l'IA"
      },
      {
        "role": "assistant",
        "content": "Je peux vous aider à configurer une veille sur les startups IA."
      },
      {
        "role": "user",
        "content": "Les entreprises qui développent des solutions de traitement du langage naturel"
      },
      {
        "role": "assistant",
        "content": "Parfait ! Je vais configurer une veille sur les startups spécialisées en NLP."
      }
    ],
    "watchFileId": "test-watch-file-id",
    "currentTitle": "Veille IA"
  },
  "watchFileId": "test-watch-file-id",
  "responseType": "conversation_monitor"
}
```

## 🚀 Installation

### 1. Import des Workflows

```bash
# Option 1 : Script Python
python scripts/import-n8n-workflows.py

# Option 2 : Import manuel via interface N8N
# - Ouvrir http://localhost:5678
# - Importer les fichiers JSON
```

### 2. Activation

1. **Activer** le workflow `Conversation Monitor Auto Rename`
2. **Vérifier** les credentials RabbitMQ
3. **Tester** avec le workflow `Test Conversation Monitor`

### 3. Configuration

#### Credentials Requis

- **RabbitMQ** : `3qG3pn5Y4jG2mbfF`
- **OpenAI** : `oU4yd3xQy86eyvNH`

#### Variables d'Environnement

```bash
N8N_BASE_URL=http://localhost:5678
N8N_API_KEY=your-api-key-here
```

## 🔄 Intégration avec le Système Existant

### Workflow Direct Message

Le workflow `direct-messageNew.json` envoie déjà des messages de monitoring :

```json
{
  "name": "N8nConversationMonitor",
  "data": {
    "messageCount": 4,
    "conversationHistory": [...],
    "watchFileId": "...",
    "currentTitle": "..."
  },
  "watchFileId": "...",
  "responseType": "conversation_monitor"
}
```

### Déclenchement Automatique

1. **Utilisateur** envoie un message
2. **Direct Message** workflow répond
3. **Direct Message** envoie un `N8nConversationMonitor`
4. **Auto Rename** workflow traite le message
5. **Si > 3 messages** → Renommage automatique

## 📊 Monitoring et Logs

### Queues RabbitMQ

- **Input** : `agent_commands`
- **Output** : `agent_responses`

### Types de Messages

- `conversation_monitor` : Messages de monitoring
- `auto_rename_notification` : Confirmations de renommage

### Logs N8N

- Vérifier les logs dans l'interface N8N
- Surveiller les exécutions du workflow
- Contrôler les erreurs de génération de titre

## 🛠 Dépannage

### Problèmes Courants

1. **Workflow ne se déclenche pas**
   - Vérifier que `command_name == "N8nConversationMonitor"`
   - Contrôler la queue `agent_commands`

2. **Génération de titre échoue**
   - Vérifier les credentials OpenAI
   - Contrôler le format des données de conversation

3. **Renommage ne s'exécute pas**
   - Vérifier le workflow `Rename Watchfile automaticly`
   - Contrôler les paramètres d'entrée

### Tests

```bash
# Test manuel via N8N
1. Activer le workflow de test
2. Déclencher manuellement
3. Vérifier les logs d'exécution
4. Contrôler la queue agent_responses
```

## 📈 Évolutions Futures

### Améliorations Possibles

1. **Seuil configurable** : Paramètre pour le nombre de messages
2. **Historique plus long** : Analyser plus de messages
3. **Validation utilisateur** : Demander confirmation avant renommage
4. **Métriques** : Suivre les renommages automatiques
5. **Règles métier** : Logique spécifique par type de veille

### Intégrations

1. **Notifications** : Alertes dans l'interface utilisateur
2. **Historique** : Log des renommages automatiques
3. **Analytics** : Statistiques d'utilisation
4. **A/B Testing** : Comparer différents algorithmes de génération

## 📝 Notes Techniques

### Sécurité

- Workflow désactivé par défaut
- Validation des données d'entrée
- Gestion des erreurs robuste

### Performance

- Traitement asynchrone
- Timeout sur les appels IA
- Retry automatique en cas d'échec

### Maintenance

- Logs détaillés
- Monitoring des queues
- Tests automatisés
