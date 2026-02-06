<template>
  <!-- Card View -->
  <div
    v-if="mode === 'grid'"
    class="bg-base-100 rounded-lg p-4 border border-primary-stroke hover:ring-4 hover:ring-primary/70 ring-offset-2 ring-offset-bg3 transition-all duration-200 cursor-pointer group"
    @click="$emit('viewCompany', company.id)"
  >
    <div class="flex items-start justify-between mb-4">
      <div class="flex items-center gap-3">
        <div
          class="w-12 h-12 rounded-lg bg-white ring-1 ring-primary-stroke overflow-hidden flex items-center justify-center"
        >
          <img
            v-if="getCompanyDomain(company.website)"
            :src="getLogoUrl(company.website)"
            :alt="`${company.name} logo`"
            class="w-full h-full object-contain p-1"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <div
            v-show="showFallbackIcon || !getCompanyDomain(company.website)"
            class="w-full h-full flex items-center justify-center bg-primary/10 dark:bg-primary/20"
          >
            <i class="fas fa-building text-secondary text-xl"></i>
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="text-lg font-semibold group-hover:text-secondary transition-colors truncate">
            {{ company.name }}
          </h3>
          <p v-if="company.website" class="text-sm text-secondary truncate">
            {{ formatWebsiteDisplay(company.website) }}
          </p>
        </div>
      </div>
    </div>

    <!-- Company Details -->
    <div class="space-y-3">
      <!-- Website Link -->
      <div v-if="company.website" class="flex items-center gap-2">
        <i class="fas fa-globe text-secondary text-sm w-4"></i>
        <a
          :href="formatWebsiteUrl(company.website)"
          target="_blank"
          rel="noopener noreferrer"
          class="text-sm text-secondary hover:text-sage-content/80 transition-colors truncate"
          @click.stop
        >
          {{ company.website }}
          <i class="fas fa-external-link-alt ml-1 text-xs"></i>
        </a>
      </div>

      <!-- Tasks Info -->
      <div v-if="company.tasks && company.tasks.length > 0" class="flex items-center gap-2">
        <i class="fas fa-tasks text-secondary text-sm w-4"></i>
        <div class="flex items-center gap-2">
          <span class="text-sm text-secondary"> {{ company.tasks.length }} tasks </span>
          <Tag :variant="getTaskStatusVariant(company.tasks)" size="xs">
            {{ getTaskStatusText(company.tasks) }}
          </Tag>
        </div>
      </div>
    </div>

    <!-- Footer with creation date and owner -->
    <div class="mt-4 pt-3 border-t border-primary-stroke">
      <div class="flex justify-between items-center text-xs text-secondary">
        <span>{{ t('company.item.created') }} {{ formatDate(company.created_at) }}</span>
        <span v-if="company.owner">{{ t('company.item.by') }} {{ company.owner }}</span>
      </div>
    </div>
  </div>

  <!-- List/Table View -->
  <div
    v-else
    class="px-6 py-4 transition-colors cursor-pointer hover:bg-base-200"
    @click="$emit('viewCompany', company.id)"
  >
    <div class="grid grid-cols-12 gap-4 items-center">
      <!-- Column 1: Company Name and Website (4 cols) -->
      <div class="col-span-4 flex items-center gap-3 min-w-0">
        <div
          class="w-10 h-10 rounded-lg bg-white ring-1 ring-primary-stroke overflow-hidden flex items-center justify-center flex-shrink-0"
        >
          <img
            v-if="getCompanyDomain(company.website)"
            :src="getLogoUrl(company.website)"
            :alt="`${company.name} logo`"
            class="w-full h-full object-contain p-1"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <div
            v-show="showFallbackIcon || !getCompanyDomain(company.website)"
            class="w-full h-full flex items-center justify-center bg-primary/10 dark:bg-primary/20"
          >
            <i class="fas fa-building text-secondary"></i>
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <h3 class="font-medium hover:text-secondary transition-colors truncate">
            {{ company.name }}
          </h3>
          <p v-if="company.website" class="text-sm text-secondary truncate">
            {{ formatWebsiteDisplay(company.website) }}
          </p>
        </div>
      </div>

      <!-- Column 2: Created Date (2 cols) -->
      <div class="col-span-2">
        <div class="text-sm text-secondary">
          {{ formatDate(company.created_at) }}
        </div>
      </div>

      <!-- Column 3: Owner (2 cols) -->
      <div class="col-span-2">
        <div class="text-sm text-secondary">
          {{ company.owner_username || '—' }}
        </div>
      </div>

      <!-- Column 4: Status (2 cols) -->
      <div class="col-span-2">
        <div class="flex items-center gap-2">
          <Tag :variant="getTaskStatusVariant(company.tasks || [])" size="sm">
            {{ getTaskStatusText(company.tasks || []) }}
          </Tag>
        </div>
      </div>

      <!-- Column 5: Actions (2 cols) -->
      <div class="col-span-2">
        <div class="flex items-center gap-1 justify-end">
          <Button
            variant="tertiary"
            size="sm"
            icon="fa fa-eye"
            :title="$t('cards.actions.view')"
            @click.stop="$emit('viewCompany', company.id)"
          />
          <Button
            v-if="canDeleteCompany"
            variant="tertiary"
            size="sm"
            icon="fa fa-trash"
            :title="$t('cards.actions.delete')"
            @click.stop="$emit('deleteCompany', company)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Tag from '@/components/ui/Tag.vue'
import { Button } from '@owlint/feathers-vue'
import type { Company } from '@/types/company'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  company: Company
  mode: 'grid' | 'table'
}

defineProps<Props>()

defineEmits<{
  viewCompany: [id: string]
  deleteCompany: [company: Company]
}>()

// Permissions
const { canDeleteCompany } = useCompanyPermissions()

// Logo state
const showFallbackIcon = ref(false)

// Helper function to extract domain from website URL
const getCompanyDomain = (website?: string) => {
  if (!website) return null
  try {
    // Remove protocol and www
    let domain = website.replace(/^https?:\/\//, '').replace(/^www\./, '')
    // Remove trailing slash and any path
    domain = domain.split('/')[0]
    return domain
  } catch {
    return null
  }
}

// Helper function to get logo URL from logo.dev
const getLogoUrl = (website?: string) => {
  const domain = getCompanyDomain(website)
  if (!domain) return ''
  return `https://img.logo.dev/${domain}?token=pk_Buf4yyXmRC2HMagyfO0jrg&retina=true`
}

// Methods
const formatWebsiteUrl = (website: string) => {
  if (!website) return '#'
  return website.startsWith('http') ? website : `https://${website}`
}

const formatWebsiteDisplay = (website: string) => {
  if (!website) return ''
  return website.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, '')
}

const formatDate = (dateString: string) => {
  if (!dateString) return t('common.na', 'N/A')
  return new Date(dateString).toLocaleDateString()
}

const formatRelativeTime = (dateString: string) => {
  if (!dateString) return t('common.na', 'N/A')

  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)

  if (diffInSeconds < 60) return t('company.item.time.justNow', 'just now')
  if (diffInSeconds < 3600)
    return t('company.item.time.minutesAgo', '{minutes}m ago', {
      minutes: Math.floor(diffInSeconds / 60),
    })
  if (diffInSeconds < 86400)
    return t('company.item.time.hoursAgo', '{hours}h ago', {
      hours: Math.floor(diffInSeconds / 3600),
    })
  if (diffInSeconds < 2592000)
    return t('company.item.time.daysAgo', '{days}d ago', {
      days: Math.floor(diffInSeconds / 86400),
    })

  return formatDate(dateString)
}

const getTaskStatusText = (tasks: Array<{ status: string }>) => {
  if (!tasks || tasks.length === 0) return t('company.item.tasks.status.new', 'New')

  const running = tasks.filter((t) => t.status === 'running' || t.status === 'pending').length
  const failed = tasks.filter((t) => t.status === 'error' || t.status === 'failed').length
  const succeeded = tasks.filter((t) => t.status === 'succeeded').length

  if (running > 0) return t('company.item.tasks.status.processing', 'Processing')
  if (failed > 0) return t('company.item.tasks.status.issues', 'Issues')
  if (succeeded === tasks.length) return t('company.item.tasks.status.complete', 'Complete')
  return t('company.item.tasks.status.partial', 'Partial')
}

const getTaskStatusVariant = (tasks: Array<{ status: string }>) => {
  if (!tasks || tasks.length === 0) return 'primary'

  const running = tasks.filter((t) => t.status === 'running' || t.status === 'pending').length
  const failed = tasks.filter((t) => t.status === 'error' || t.status === 'failed').length
  const succeeded = tasks.filter((t) => t.status === 'succeeded').length

  if (running > 0) return 'warning'
  if (failed > 0) return 'error'
  if (succeeded === tasks.length) return 'success'
  return 'info'
}
</script>

<style scoped></style>
