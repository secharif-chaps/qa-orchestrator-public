# 🍃 MINT - Market Intelligence Platform

<div align="center">

**AI-Powered Company Screening & Market Intelligence**

_The next generation of market intelligence tools - built from the ground up with AI_

[![Vue 3](https://img.shields.io/badge/Vue-3.5.18-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.8-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-4.1.11-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Vite](https://img.shields.io/badge/Vite-7.0.6-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev/)
[![Pinia](https://img.shields.io/badge/Pinia-3.0.3-FFD859?style=for-the-badge&logo=vue.js&logoColor=black)](https://pinia.vuejs.org/)

</div>

---

## 🚀 What is MINT?

**MINT** (**M**arket **INT**elligence) is the first module of an emerging modular web application platform designed to revolutionize market intelligence. The **Screen** module enables comprehensive company screening by gathering and analyzing all publicly available information about companies online.

### 🎯 Key Differentiators

- **🧠 AI-First Architecture**: Built from the ground up with AI, not legacy software with AI features bolted on
- **⚡ Superior Speed**: Faster data collection and analysis than traditional competitors
- **🔗 BAKUS Integration**: Connected to our proprietary data server for unmatched data collection capabilities
- **🏗️ Modular Design**: Part of a future ecosystem where clients can select specific modules based on their needs

---

## 🌟 Features

### 📊 **Company Intelligence Dashboard**

- Real-time company monitoring and screening
- Comprehensive company profiles with multi-source data validation
- Task-based workflow orchestration for data collection

### 🏢 **Multi-Workspace Support**

- Workspace-based multi-tenancy
- Shared company cards within workspaces
- Role-based access control with admin capabilities

### 🔄 **AI-Powered Data Collection**

- Automated task workflows using n8n integrations
- Real-time task status monitoring
- Intelligent data sourcing and validation

### 📈 **Rich Data Insights**

- **Company Profile**: Business lines, revenue, employee count, leadership
- **Digital Presence**: Social media, online services, digital strategy
- **Timeline**: Key events and milestones
- **Products & Services**: Product ranges, partnerships, private labels
- **Jobs & Hiring**: Current openings, hiring trends, department insights
- **CSR & Press**: Corporate responsibility initiatives and media coverage
- **Team Structure**: Organizational hierarchy and key personnel

### 🎨 **Modern User Experience**

- Responsive design with dark/light mode support
- Multiple theme options (Indigo, Boston, Emerald, Pink, Rose, Orange, Sage)
- Intuitive drag-and-drop interfaces
- Real-time updates and notifications

---

## 🛠️ Tech Stack

### **Frontend**

- **Vue 3** - Progressive JavaScript framework with Composition API
- **TypeScript** - Type-safe development
- **Tailwind CSS v4** - Utility-first CSS framework
- **Reka UI** - Modern component library
- **Pinia** - State management
- **Pinia Colada** - Async state management and data fetching
- **Vue Router** - Client-side routing with file-based routing
- **Vue I18n** - Internationalization (English/French)

### **Development Tools**

- **Vite** - Lightning-fast build tool
- **ESLint** - Code linting with Vue-specific rules
- **Prettier** - Code formatting
- **TypeScript** - Static type checking
- **Vue DevTools** - Development debugging

### **Authentication & Integration**

- **OIDC Client** - OpenID Connect authentication
- **Feathers Vue** - Real-time API integration
- **Vue Flow** - Interactive diagrams and workflows

---

## 🚀 Quick Start

### Prerequisites

- **Node.js** `^20.19.0 || >=22.12.0`
- **pnpm** (recommended) or npm

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd mint-front

# Install dependencies
pnpm install

# Start development server
pnpm dev
```

The application will be available at `http://localhost:3000` with hot module replacement enabled.

### Available Scripts

```bash
# Development
pnpm dev              # Start development server

# Building
pnpm build            # Build for production
pnpm build-only       # Build without type checking
pnpm preview          # Preview production build

# Code Quality
pnpm type-check       # Run TypeScript type checking
pnpm lint             # Run ESLint with auto-fix
pnpm format           # Format code with Prettier
```

---

## 📁 Project Structure

```
src/
├── api/              # API functions and HTTP client
├── components/       # Reusable Vue components
│   ├── ui/          # Base UI components
│   ├── global/      # Layout components (sidebar, appbar)
│   ├── company/     # Company-specific components
│   ├── dashboard/   # Dashboard components
│   └── helpers/     # Utility components
├── composables/      # Composition functions
├── stores/          # Pinia stores for global state
├── queries/         # Pinia Colada queries for data fetching
├── mutations/       # Data mutation logic
├── pages/           # Page components (file-based routing)
├── types/           # TypeScript type definitions
├── i18n/            # Internationalization files
├── assets/          # Static assets
└── router/          # Vue Router configuration
```

---

## 🔧 Configuration

### Environment Variables

Create a `.env.local` file in the root directory:

```env
# API Configuration
VITE_API_BASE_URL=your_api_url
VITE_OIDC_AUTHORITY=your_oidc_provider
VITE_OIDC_CLIENT_ID=your_client_id

# Feature Flags
VITE_ENABLE_DEVTOOLS=true
```

### Authentication Setup

MINT uses OpenID Connect (OIDC) for authentication. Configure your OIDC provider with:

- **Redirect URIs**: `http://localhost:3000/auth/callback`
- **Silent Refresh URI**: `http://localhost:3000/auth/silent-callback`
- **Post Logout URI**: `http://localhost:3000/login`

---

## 🎨 Theming

MINT supports multiple themes and automatic dark/light mode switching:

### Available Themes

- **Indigo** (default)
- **Boston**
- **Emerald**
- **Pink**
- **Rose**
- **Orange**
- **Sage**

### Semantic Color System

Use semantic color classes for consistent theming:

```vue
<template>
  <!-- Backgrounds -->
  <div class="bg-base-100">Primary content background</div>
  <div class="bg-base-200">Secondary background</div>
  <div class="bg-base-300">Main app background</div>

  <!-- Text -->
  <p class="text-base">Normal text</p>
  <p class="text-primary-light-content">Secondary text</p>
  <p class="text-primary">Accent text</p>

  <!-- Interactive elements -->
  <button class="bg-primary text-white hover:bg-primary/80">Primary Button</button>
</template>
```

---

## 🏗️ Architecture

### Data Flow Pattern

MINT follows a structured data flow pattern using Pinia Colada:

1. **API Layer** (`/api`): Pure functions for HTTP requests
2. **Query Layer** (`/queries`): Reactive data fetching with caching
3. **Mutation Layer** (`/mutations`): Data modifications with optimistic updates
4. **Store Layer** (`/stores`): Global state management
5. **Component Layer**: UI components with reactive data

### Key Architectural Decisions

- **Composition API Only**: No Options API usage
- **File-based Routing**: Automatic route generation
- **Type-first Development**: TypeScript interfaces for all data structures
- **Semantic Theming**: Theme-aware color system
- **Workspace Isolation**: Multi-tenant architecture

---

## 📋 Development Guidelines

### Code Standards

- ✅ **Always** use Composition API with `<script setup>`
- ✅ **Always** use TypeScript with proper typing
- ✅ **Prefer** `interface` over `type` for object definitions
- ✅ **Use** semantic color classes instead of direct Tailwind colors
- ✅ **Follow** the established project structure
- ✅ **Write** meaningful tests alongside your code

### Git Commit Convention

MINT uses Conventional Commits with Gitmoji:

```bash
# Format: <gitmoji> <type>(scope): <description>

✨ feat(companies): add job listings view
🐛 fix(auth): resolve redirect loop issue
💄 style(ui): improve button hover states
♻️ refactor(api): optimize query performance
🔧 chore(deps): update Vue to 3.5.18
```

### Testing Strategy

- **Unit Tests**: Test critical business logic
- **Integration Tests**: Test component interactions
- **E2E Tests**: Test complete user workflows
- **Visual Testing**: Use Playwright for UI testing

---

## 🤝 Contributing

### Development Workflow

1. **Plan**: Review requirements and create implementation plan
2. **Code**: Follow project standards and conventions
3. **Test**: Write and run tests for new features
4. **Review**: Ensure code quality and performance
5. **Deploy**: Stage changes and verify functionality

### Code Quality

Before submitting changes:

```bash
# Run type checking
pnpm type-check

# Run linting
pnpm lint

# Run tests (when available)
pnpm test

# Build to verify no errors
pnpm build
```

---

## 🔮 Roadmap

### Current: Screen Module

- ✅ Company screening and profiling
- ✅ Multi-workspace support
- ✅ AI-powered data collection
- ✅ Real-time task monitoring

### Future Modules

- 🔄 **Analyze Module**: Advanced analytics and insights
- 🔄 **Monitor Module**: Continuous company monitoring
- 🔄 **Compare Module**: Competitive analysis tools
- 🔄 **Report Module**: Custom reporting and exports

---

## 📄 License

This is proprietary software. All rights reserved.

---

## 📞 Support

For support and inquiries about MINT:

- 📧 **Email**: nmercier@chapsvision.com

---

<div align="center">

**Built with ❤️ for the future of Market Intelligence**

_MINT - Where AI meets Market Intelligence_

</div>
