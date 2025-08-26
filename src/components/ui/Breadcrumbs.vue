<template>
  <nav class="flex" aria-label="Breadcrumb" v-if="breadcrumbs.length > 0">
    <ol role="list" class="flex items-center space-x-4">
      <!-- Home icon - always first -->
      <li>
        <div>
          <RouterLink 
            to="/" 
            class="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
          >
            <i class="fa fa-home text-lg" aria-hidden="true"></i>
            <span class="sr-only">Home</span>
          </RouterLink>
        </div>
      </li>
      
      <!-- Breadcrumb items -->
      <li v-for="(item, index) in breadcrumbs" :key="item.name">
        <div class="flex items-center">
          <i class="fa fa-chevron-right text-sm text-gray-400 dark:text-gray-500" aria-hidden="true"></i>
          
          <!-- Clickable link -->
          <RouterLink 
            v-if="item.to && !item.current"
            :to="item.to" 
            class="ml-4 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
          >
            {{ item.name }}
          </RouterLink>
          
          <!-- Current page (non-clickable) -->
          <span 
            v-else
            class="ml-4 text-sm font-medium text-gray-900 dark:text-gray-100"
            :aria-current="item.current ? 'page' : undefined"
          >
            {{ item.name }}
          </span>
        </div>
      </li>
    </ol>
  </nav>
</template>

<script lang="ts" setup>
/**
 * Breadcrumbs Component
 * 
 * Automatically generates breadcrumbs based on the current route.
 * Features:
 * - Home icon always first, linking to "/"
 * - Dynamic company names for company pages
 * - Clickable navigation links for all non-current items
 * - Current page highlighted and non-clickable
 * - Responsive and accessible design
 * 
 * Examples:
 * - /companies/123/profile → 🏠 Home > Companies > Acme Corp > Profile
 * - /admin/workspaces → 🏠 Home > Admin > Workspaces
 * - /settings/appearance → 🏠 Home > Settings > Appearance
 */
import { useBreadcrumbs } from '@/composables/useBreadcrumbs'

const { breadcrumbs } = useBreadcrumbs()
</script>