# Basil Glossary

This glossary defines the key concepts and terms used in the Basil project. It serves as a reference for understanding the application's architecture and functionalities.

## 📖 Core Concepts

### Actor

An **Actor** represents an entity (legal person or individual) that is relevant for monitoring purposes. Actors are stakeholders identified as important in the context of information monitoring and intelligence gathering.

**Technical characteristics:**

- Uniquely declared within the platform (global entity)
- Identified by a unique ID and descriptive label
- Has a primary domain (`primaryDomain`) characterizing their activity sector
- Can be associated with multiple WatchFiles
- Timestamped with `createdAt` and `updatedAt` for change tracking

**Key features:**

- **Global scope**: Each actor is declared once and can be reused across multiple WatchFiles
- **Reusability**: The same actor can be monitored in different surveillance contexts
- **Centralized management**: Actor information is maintained in a single location

**Usage examples:**

- Competitor companies to monitor
- Key political or economic figures
- Partner organizations or important institutions
- Any entity relevant to defined surveillance objectives

---

### WatchFile

A **WatchFile** is a comprehensive set of parameters and criteria that define a specific surveillance mission. It represents the core unit for information collection and analysis according to precise business needs.

**Core components:**

- **Surveillance objectives**: Clear definition of information to be gathered
- **Relevance criteria**: Parameters determining the interest level of collected documents
- **Associated actors**: List of entities to monitor specifically
- **Configured sources**: Information channels to consult
- **Search queries**: Keywords and expressions for automated collection
- **Contextual analysis**: Framework for evaluating collected information

**Status levels:**

- `DRAFT`: Under development
- `ENABLED`: Active and operational
- `DISABLED`: Temporarily deactivated

**Relationships:**

- Can be grouped into one or more Folders
- Generates Collected Documents during information gathering
- References global Actors for surveillance purposes

---

### Folder

A **Folder** is a simple organizational container that groups multiple WatchFiles together. It serves as a manual grouping mechanism created by users to organize their surveillance activities.

**Key characteristics:**

- **Manual grouping**: Users manually assign WatchFiles to folders
- **No thematic constraint**: Grouping is based on user preference, not automatic thematic classification
- **Organizational purpose**: Helps users structure and manage their surveillance missions
- **Flexible structure**: WatchFiles can be moved between folders as needed

**Usage scenarios:**

- Project-based organization (e.g., "Project Alpha surveillance")
- Client-specific grouping (e.g., "Client X monitoring")
- Temporal organization (e.g., "Q1 2024 surveillance")
- Priority-based classification (e.g., "High priority monitoring")

**Technical implementation:**

- Simple container without complex business logic
- Maintains references to grouped WatchFiles
- Provides organizational metadata (name, description, creation date)

---

### Collected Document

A **Collected Document** represents an information item gathered by data sources that meets the needs and criteria defined in WatchFiles. It constitutes the concrete result of automated surveillance activities.

**Essential properties:**

- **Structured content**: Title, excerpt, full content
- **Metadata**: Publication date, document type, language
- **Validation status**: Validated/rejected with decision reasoning
- **Traceability**: Link to source actor and collection context
- **Analysis results**: Interest indicator and generated insights

**Validation statuses:**

- `validated`: Document approved as relevant
- `rejected`: Document dismissed with justification

**Lifecycle:**

1. **Collection**: Automated retrieval from configured sources
2. **Analysis**: Relevance evaluation according to WatchFile criteria
3. **Validation**: Human or automated verification
4. **Archiving**: Storage for future consultation
5. **Insight generation**: Analysis and pattern detection

**Quality indicators:**

- **Relevance score**: Numerical assessment of document importance
- **Actor association**: Link to monitored entities
- **Context matching**: Alignment with WatchFile objectives
- **Temporal relevance**: Timeliness of the information

---

### Collect

**Collect** refers to the comprehensive system and process of gathering information from external sources based on defined surveillance criteria. It encompasses the entire workflow from request initiation to data retrieval and processing.

**Core components:**

- **CollectTask**: Individual collection jobs that execute specific data gathering operations
- **Collector**: Configurable components that define how to retrieve information from specific source types
- **Provider**: Infrastructure services that handle communication with external data sources
- **Collection workflow**: Automated process managing task creation, execution, and result processing

**Key features:**

- **Multi-source support**: Ability to gather information from various external APIs and services
- **Configurable collection**: Flexible parameter system for customizing data retrieval
- **Status tracking**: Real-time monitoring of collection progress and results
- **Error handling**: Robust management of failures and retry mechanisms

---

### Collector

A **Collector** is a configurable component that defines the specific methodology and parameters for retrieving information from a particular type of external source. Each collector encapsulates the knowledge of how to interact with a specific service or API.

**Technical characteristics:**

- **Type-specific**: Each collector is designed for a particular source type (e.g., web search, social media, news feeds)
- **Parameterizable**: Accepts configuration parameters to customize collection behavior
- **Reusable**: Same collector definition can be used across multiple CollectTasks
- **Versioned**: Collectors have versions to manage evolution and compatibility

**Configuration parameters:**

- **String parameters**: Text-based configuration (keywords, URLs, filters)
- **Boolean parameters**: On/off settings (include metadata, follow redirects)
- **Integer parameters**: Numeric settings (result limits, timeout values)
- **Choice parameters**: Selection from predefined options (language, format, priority)

**Supported features:**

- **Batch processing**: Ability to handle multiple requests simultaneously
- **Streaming results**: Real-time result delivery for long-running collections
- **Return type specification**: Definition of expected result formats

---

### Provider

A **Provider** is the infrastructure layer that handles the actual communication and integration with external data sources. It acts as an adapter between the internal collect system and external APIs or services.
Our primary provider is **Bakus**.

**Responsibilities:**

- **Authentication management**: Handle API keys, OAuth tokens, and access credentials
- **HTTP communication**: Manage requests, responses, and error handling
- **Rate limiting**: Respect external service limits and implement retry strategies
- **Data transformation**: Convert external data formats to internal representations
- **Monitoring**: Track usage, performance, and service availability

**Key features:**

- **Protocol abstraction**: Standardized interface regardless of underlying service
- **Error resilience**: Automatic retry mechanisms and failure handling
- **Security**: Secure credential management and encrypted communications
- **Scalability**: Support for concurrent requests and load distribution

**Examples:**

- Bakus Provider for web search and data collection
- Social media APIs (Twitter, LinkedIn, Facebook)
- News aggregation services
- Government data portals

---

### CollectTask

A **CollectTask** represents an individual, executable job that performs a specific data collection operation using a configured collector through a provider. It's the atomic unit of work in the collect system.

**Core properties:**

- **Unique identifier**: Each task has a UUID for tracking and reference
- **Status tracking**: Current state (pending, running, completed, failed)
- **Configuration**: Specific parameters and settings for the collection
- **Results**: Collected data and metadata from the operation
- **Timestamps**: Creation, start, completion, and update times

**Associated data:**

- **Source configuration**: Details about the target data source
- **Collection parameters**: Specific settings for the gathering operation
- **Result metadata**: Information about retrieved data (count, format, quality)
- **Error details**: Failure reasons and diagnostic information when applicable

**Integration points:**

- **WatchFile association**: Tasks are created to fulfill WatchFile surveillance needs
- **Event generation**: Status changes trigger events for workflow coordination
- **Result processing**: Collected data feeds into document analysis and validation

---

## 🔄 Relationships Between Concepts

```
Platform-wide Actors (Global)
├── Actor A
├── Actor B
└── Actor C

Folder (Manual Grouping)
├── WatchFile 1
│   ├── References Actor A
│   ├── References Actor B
│   └── Generates Collected Documents
├── WatchFile 2
│   ├── References Actor C
│   └── Generates Collected Documents
└── WatchFile 3
    ├── References Actor A
    ├── References Actor C
    └── Generates Collected Documents
```

## 🎯 Intelligence Gathering Process

1. **Actor Management**: Declare and maintain actors at platform level
2. **WatchFile Configuration**: Create surveillance missions with specific criteria
3. **Folder Organization**: Group WatchFiles for better management
4. **Automated Collection**: Retrieve Collected Documents from configured sources
5. **Filtering & Analysis**: Apply relevance criteria and context analysis
6. **Validation**: Verify quality and interest of Collected Documents
7. **Insight Generation**: Produce analysis and actionable intelligence

## 🏗️ Architecture Overview

- **Actors**: Global entities shared across the platform
- **WatchFiles**: Core surveillance units with complex business logic
- **Folders**: Simple organizational containers
- **Collected Documents**: Information items generated by surveillance activities
