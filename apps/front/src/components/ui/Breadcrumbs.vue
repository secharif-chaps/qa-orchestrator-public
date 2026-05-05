<template>
  <nav
    class="relative z-10 flex"
    :aria-label="t('common.breadcrumb.ariaLabel')"
    v-if="breadcrumbs.length"
  >
    <ol role="list" class="flex items-center space-x-4">
      <!-- Home icon - always first -->
      <li>
        <div>
          <RouterLink
            to="/"
            class="text-almond-600 transition-colors hover:text-gray-500 dark:text-gray-300 dark:hover:text-gray-200"
          >
            <Icon icon="fa-home" class="text-lg" aria-hidden="true" />
            <span class="sr-only">{{ t('common.sidebar.home') }}</span>
          </RouterLink>
        </div>
      </li>

      <!-- Breadcrumb items -->
      <li v-for="item in breadcrumbs" :key="item.name">
        <div class="flex items-center">
          <Icon
            icon="fa-chevron-right"
            class="text-sm text-black dark:text-gray-300"
            aria-hidden="true"
          />

          <!-- Clickable link -->
          <RouterLink
            v-if="item.to"
            :to="item.to"
            :class="[
              'ml-4 text-sm font-medium transition-colors',
              item.current
                ? 'text-almond-600 dark:text-gray-100'
                : 'text-almond-600 hover:text-gray-700 dark:text-gray-300 dark:hover:text-gray-200',
            ]"
            :aria-current="item.current ? 'page' : undefined"
          >
            {{ item.name }}
          </RouterLink>

          <!-- Non-clickable text (no link provided) -->
          <Tag
            v-else
            class="ml-4"
            size="sm"
            color="indigo"
            :aria-current="item.current ? 'page' : undefined"
          >
            {{ item.name }}
          </Tag>
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
 * - /admin/organizations → 🏠 Home > Adminorganizationaces
 * - /settings/appearance → 🏠 Home > Settings > Appearance
 */
import { useBreadcrumbs } from '@/composables/useBreadcrumbs'
import { Icon, Tag } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { breadcrumbs } = useBreadcrumbs()
</script>
