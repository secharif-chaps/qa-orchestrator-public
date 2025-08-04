# File-Based Routing Guide

This project uses `unplugin-vue-router` for automatic file-based routing. Routes are generated based on the file structure in the `src/pages` directory.

## 📁 Basic File Structure

```
src/pages/
├── index.vue                    → "/"
├── about.vue                    → "/about"
├── users.vue                    → "/users"
├── users/
│   ├── index.vue               → "/users" (same as users.vue)
│   ├── [id].vue                → "/users/:id"
│   ├── [id]/
│   │   ├── index.vue           → "/users/:id" (same as [id].vue)
│   │   └── edit.vue            → "/users/:id/edit"
│   └── create.vue              → "/users/create"
└── [...path].vue               → "/*" (catch-all)
```

## 🔧 Route Patterns

### **Static Routes**
```
about.vue              → "/about"
contact.vue            → "/contact"
```

### **Dynamic Routes**
```
[id].vue               → "/:id"
users/[id].vue         → "/users/:id"
[slug]/index.vue       → "/:slug"
posts/[...slug].vue    → "/posts/*" (catch-all)
```

### **Optional Parameters**
```
[[id]].vue             → "/:id?" (optional)
users/[[id]].vue       → "/users/:id?"
```

### **Repeatable Parameters**
```
[id]+.vue              → "/:id+" (one or more)
posts/[[slug]]+.vue    → "/posts/:slug*" (zero or more)
```

## 🎭 Route Groups

Route groups organize files without affecting URLs:

```
src/pages/
├── (admin)/
│   ├── dashboard.vue          → "/dashboard"
│   ├── users.vue              → "/users"
│   └── settings.vue           → "/settings"
├── (public)/
│   ├── login.vue              → "/login"
│   └── register.vue           → "/register"
└── (marketing)/
    ├── pricing.vue            → "/pricing"
    └── features.vue           → "/features"
```

**Benefits of Route Groups:**
- Organize related routes logically
- Share layouts between grouped routes
- Apply common middleware/guards
- Better code organization

## 🏗️ Layouts and Nesting

### **Automatic Nesting**
```
src/pages/
├── users.vue                  → Layout for /users/*
└── users/
    ├── index.vue             → "/users" content
    ├── [id].vue              → "/users/:id" content
    └── create.vue            → "/users/create" content
```

### **Breaking Out of Layouts**
Use dot notation to bypass parent layouts:

```
src/pages/
├── users.vue                  → Layout
├── users/
│   ├── index.vue             → Uses users.vue layout
│   └── [id].vue              → Uses users.vue layout
└── users.create.vue          → "/users/create" (no layout)
```

## 🎯 Best Practices for Our Project

### **1. Admin Routes Structure**
```
src/pages/
├── (admin)/
│   ├── workspaces/
│   │   ├── (workspaces).vue   → "/workspaces" (better naming)
│   │   ├── create.vue         → "/workspaces/create"
│   │   └── [id].vue           → "/workspaces/:id"
│   └── users/
│       ├── (users).vue        → "/users" 
│       ├── create.vue         → "/users/create"
│       └── [id].vue           → "/users/:id"
```

### **2. Company Routes Structure (Current)**
```
src/pages/
└── companies/
    ├── (list).vue             → "/companies"
    ├── [companyId].vue        → Layout for /companies/:companyId/*
    └── [companyId]/
        ├── index.vue          → "/companies/:companyId" (dashboard)
        ├── profile.vue        → "/companies/:companyId/profile"
        ├── products.vue       → "/companies/:companyId/products"
        ├── jobs.vue           → "/companies/:companyId/jobs"
        ├── timeline.vue       → "/companies/:companyId/timeline"
        └── team.vue           → "/companies/:companyId/team"
```

### **3. Settings Routes Structure**
```
src/pages/
└── settings/
    ├── (settings).vue         → "/settings" (overview)
    ├── profile.vue            → "/settings/profile"
    ├── security.vue           → "/settings/security"
    └── appearance.vue         → "/settings/appearance"
```

## 🛡️ Route Meta and Permissions

### **Using Route Blocks**
```vue
<template>
  <!-- Your component -->
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
  requiresAuth: true
  title: "Workspace Management"
</route>

<script setup lang="ts">
// Your script
</script>
```

### **TypeScript Route Block**
```vue
<route lang="ts">
{
  meta: {
    permissions: ['admin.users'],
    requiresAuth: true,
    layout: 'admin'
  }
}
</route>
```

## 📋 Common Patterns

### **CRUD Operations**
```
src/pages/
└── admin/
    └── users/
        ├── (users).vue        → List all users
        ├── create.vue         → Create new user
        ├── [id].vue           → View user details
        └── [id]/
            └── edit.vue       → Edit user
```

### **Nested Resources**
```
src/pages/
└── companies/
    └── [companyId]/
        ├── index.vue          → Company dashboard
        ├── users/
        │   ├── (users).vue    → Company users list
        │   └── [userId].vue   → Specific user
        └── projects/
            ├── (projects).vue → Company projects
            └── [projectId].vue → Specific project
```

### **Authentication Pages**
```
src/pages/
├── (auth)/
│   ├── login.vue              → "/login"
│   ├── register.vue           → "/register"
│   └── forgot-password.vue    → "/forgot-password"
└── auth/
    ├── callback.vue           → "/auth/callback"
    └── silent-callback.vue    → "/auth/silent-callback"
```

## 🔍 Debugging Routes

### **Check Generated Routes**
Look at `typed-router.d.ts` to see all generated routes:

```typescript
export interface RouteNamedMap {
  '/users': RouteRecordInfo<'/users', '/users', ...>,
  '/users/[id]': RouteRecordInfo<'/users/[id]', '/users/:id', ...>,
  '/users/create': RouteRecordInfo<'/users/create', '/users/create', ...>,
}
```

### **Common Issues**
1. **Missing index.vue**: Creates layout-only routes
2. **Wrong file naming**: `[id]` vs `[userId]` affects parameter names
3. **Nested conflicts**: File and folder with same name
4. **Route groups**: Missing parentheses in folder names

## ✅ Naming Conventions

### **Files**
- Use `kebab-case` for file names: `user-profile.vue`
- Use `camelCase` for parameters: `[userId].vue`
- Group related routes with `(groupName)/`

### **Parameters**
- Be specific: `[userId]` instead of `[id]`
- Use descriptive names: `[companySlug]` instead of `[slug]`
- Match backend API parameter names

### **Route Groups**
- Use descriptive names: `(admin)`, `(public)`, `(marketing)`
- Keep groups focused on single responsibility
- Use nested groups for complex hierarchies

## 🔄 Migration Tips

### **From Manual Routes**
1. Move route components to `src/pages`
2. Rename files to match desired URLs
3. Use route groups for organization
4. Add route blocks for meta properties
5. Update router imports to use auto-generated routes

### **Refactoring Existing Structure**
1. Identify route patterns and hierarchies
2. Group related routes logically
3. Use proper file naming conventions
4. Leverage nested layouts for shared UI
5. Test all route transitions and parameters

This file-based routing system provides powerful organization capabilities while maintaining clear URL structures and type safety throughout the application.