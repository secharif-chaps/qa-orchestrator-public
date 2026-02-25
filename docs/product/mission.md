# Product Mission

## Pitch

**ChapsMind** is an AI-powered market intelligence platform that helps private companies and market intelligence professionals discover, monitor, and analyze market and economic intelligence online by providing modular, AI-first tools with intelligent guidance through **Chaps-e**, our integrated AI assistant.

## Vision

Reinvent market intelligence products with AI capabilities. ChapsMind represents a complete rebuild from scratch of traditional market intelligence tools, leveraging modern AI architecture (Dify) to deliver smarter, faster, and more actionable insights. The platform goes beyond company data to encompass the full spectrum of market and economic intelligence.

## Product Strategy

### Business Model
- **Single auth multi-tenant application** with Keycloak Organizations for client isolation
- **Modular architecture**: Sell one or multiple modules to clients as independent products with synergies
- **Parent company**: Chapsvision (market intelligence software catalog)
- **Goal**: Rework existing Chapsvision products into ChapsMind modules

### Module Portfolio

| Module | Purpose | Status |
|--------|---------|--------|
| **Screen** | Get information about companies online | Production (early stage) |
| **Target** | Setup watchfiles on topics for automated monitoring | Q1 2025 early adopters |
| **Explore** | Graph-based relationship discovery | Future |
| **Stream** | Intelligence distribution (newsletter, Slack, RSS) | Future |
| **Discover** | Additional dashboard capabilities | Future |

## Users

### Primary Customers
- **Private Companies**: Organizations of all sizes working in market intelligence fields
- **Market Intelligence Teams**: Professionals who need to monitor competitors, prospects, and market trends
- **Business Development**: Teams tracking potential partners, acquisition targets, or investment opportunities

### User Personas

**Intelligence Analyst** (28-45)
- **Role:** Market Intelligence Specialist
- **Context:** Works at a consulting firm or corporate strategy department
- **Pain Points:** Manual research is time-consuming, data becomes stale quickly, difficult to track multiple companies systematically
- **Goals:** Automate company research, get AI-powered insights, maintain up-to-date intelligence on key targets

**Business Development Manager** (30-50)
- **Role:** BD Lead or Sales Director
- **Context:** Needs to understand prospects and competitors before meetings and pitches
- **Pain Points:** Scattered information across multiple sources, no single view of a company, time spent on manual research
- **Goals:** Quick access to comprehensive company profiles, understand organizational structure, identify opportunities

**Strategy Executive** (35-55)
- **Role:** VP Strategy or C-Suite
- **Context:** Makes decisions about partnerships, acquisitions, and market positioning
- **Pain Points:** Information overload, difficulty separating signal from noise, need for actionable intelligence
- **Goals:** High-level insights with drill-down capability, trend analysis, relationship mapping

## The Problem

### Fragmented Market Intelligence
Traditional market intelligence requires piecing together information from dozens of sources manually. Analysts spend 60-70% of their time gathering data instead of analyzing it.

**Our Solution:** Automated data collection via AI-powered Dify workflows (datacollector + specialized section workflows) that compile comprehensive company profiles automatically.

### Static, Outdated Information
Most company databases provide snapshot data that becomes stale quickly. Users never know if the information is current or relevant.

**Our Solution:** Continuous monitoring through the Target module with automated watchfiles that track changes and alert users to significant updates.

### Generic Tools, No Guidance
Existing tools dump data without context. Users must figure out what matters and what to do next on their own.

**Our Solution:** Chaps-e Smart Assist provides vertical AI assistance, helping users understand what the data means and suggesting logical next steps based on their goals. Users configure their AI preferences once, and smart action buttons appear contextually to help achieve their real objectives.

## Differentiators

### AI-First Architecture
Unlike legacy market intelligence tools that bolt on AI features, ChapsMind is built from the ground up with AI at its core. Dify powers intelligent analysis and orchestrates all data collection workflows. This is not an afterthought - it is the foundation.

**Result:** Smarter data collection, contextual insights, and continuous improvement as AI models evolve.

### Chaps-e AI Assistant
A global AI assistant accessible from the sidebar throughout the platform. Chaps-e adapts its context based on the page the user is viewing, providing relevant assistance for any task. It serves as both a conversational AI chatbot for querying data and will be the primary interface for setting up watchfiles in the Target module.

**Result:** Users have a consistent AI companion that understands their context and helps them accomplish tasks through natural conversation.

### Chaps-e Smart Assist
A specialized feature that verticalizes the platform without custom code. Users configure their AI preferences (goals, role, context) in settings, and ChapsMind computes the 3 most useful actions for each company card based on those preferences and the company data.

**Result:** The platform adapts to user intent. Intelligence professionals gathering data for market analysis get different suggested actions than business development teams approaching prospects - without requiring different software.

### Modular Independence with Synergies
Each module (Screen, Target, Explore, Stream, Discover) works as a standalone product but gains power when combined. Clients can start with one module and expand as needs grow.

**Result:** Lower barrier to entry, scalable value, and sticky customer relationships.

### Generic Platform, Specialized Guidance
The underlying application is industry-agnostic, but AI guidance is tailored to market intelligence use cases. This means rapid feature development without sacrificing domain expertise.

**Result:** Best of both worlds - a modern, maintainable codebase with deep market intelligence knowledge embedded in AI layers.

## Key Features

### Screen Module (Current)
- **Company Cards:** Create profiles for companies to monitor
- **Automated Data Collection:** Dify datacollector task gathers raw data, then specialized workflows process specific sections
- **Section-Based Intelligence:** Jobs, products, team, CSR, financials, news - each handled by dedicated workflows
- **Task Monitoring:** Real-time status tracking of data collection tasks
- **Organization Sharing:** Company cards shared within organization for team collaboration

### Target Module (Q1 2025)
- **Watchfiles:** Configure automated monitoring on any topic through conversational setup with Chaps-e
- **Automatic Discovery:** Watchfiles auto-detect sources, actors, and documents related to your topic
- **Alert System:** Notifications when significant changes occur
- **Trend Tracking:** Historical data to identify patterns over time

### Explore Module (Future)
- **Relationship Graphs:** Visual mapping of company connections
- **Network Analysis:** Identify hidden relationships and influence patterns
- **Discovery Engine:** Find related companies based on various criteria

### Stream Module (Future)
- **Newsletter Generation:** Automated intelligence digests
- **Multi-Channel Distribution:** Slack, RSS, email integration
- **Customizable Feeds:** Tailored content based on user preferences

### Collaboration Features
- **Organization-Based Access:** Multi-tenant isolation via Keycloak Organizations
- **Role-Based Permissions:** Granular control (view, create, delete companies; manage team)
- **Folder Organization:** Group companies into logical collections
- **Team Management:** Invite and manage organization members

### AI Features
- **Chaps-e AI Assistant:** Global conversational AI accessible from sidebar, adapts context to current page
- **Chaps-e Smart Assist:** Goal-based smart action buttons on company cards (configure in Settings → AI Preferences)
- **AI Chatbot:** Conversational interface for querying data and setting up watchfiles (Target module)
- **Intelligent Summarization:** AI-generated insights from raw data
- **Workflow Orchestration:** Dify for automated, intelligent data processing
