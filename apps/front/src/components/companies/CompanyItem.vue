<template>
  <!-- Card View -->
  <div
    v-if="mode === 'grid'"
    class="bg-base-100 border-primary-stroke hover:ring-primary/70 ring-offset-bg3 group cursor-pointer rounded-lg border p-4 ring-offset-2 transition-all duration-200 hover:ring-4"
    @click="$emit('viewCompany', company.id)"
  >
    <div class="mb-4 flex items-start justify-between">
      <div class="flex items-center gap-3">
        <div
          class="ring-primary-stroke flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-white ring-1"
        >
          <img
            v-if="getCompanyDomain(company.website)"
            :src="getLogoUrl(company.website)"
            :alt="`${company.name} logo`"
            class="h-full w-full object-contain p-1"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <div
            v-show="showFallbackIcon || !getCompanyDomain(company.website)"
            class="bg-primary/10 dark:bg-primary/20 flex h-full w-full items-center justify-center"
          >
            <i class="fas fa-building text-secondary text-xl"></i>
          </div>
        </div>
        <div class="min-w-0 flex-1">
          <h3 class="group-hover:text-secondary truncate text-lg font-semibold transition-colors">
            {{ company.name }}
          </h3>
          <p v-if="company.website" class="text-secondary truncate text-sm">
            {{ formatWebsiteDisplay(company.website) }}
          </p>
        </div>
      </div>
    </div>

    <!-- Company Details -->
    <div class="space-y-3">
      <!-- Website Link -->
      <div v-if="company.website" class="flex items-center gap-2">
        <i class="fas fa-globe text-secondary w-4 text-sm"></i>
        <a
          :href="formatWebsiteUrl(company.website)"
          target="_blank"
          rel="noopener noreferrer"
          class="text-secondary hover:text-sage-content/80 truncate text-sm transition-colors"
          @click.stop
        >
          {{ company.website }}
          <i class="fas fa-external-link-alt ml-1 text-xs"></i>
        </a>
      </div>

      <!-- Tasks Info -->
      <div v-if="company.tasks && company.tasks.length > 0" class="flex items-center gap-2">
        <i class="fas fa-tasks text-secondary w-4 text-sm"></i>
        <div class="flex items-center gap-2">
          <span class="text-secondary text-sm"> {{ company.tasks.length }} tasks </span>
          <Tag :variant="getTaskStatusVariant(company.tasks)" size="xs">
            {{ getTaskStatusText(company.tasks) }}
          </Tag>
        </div>
      </div>
    </div>

    <!-- Footer with creation date and owner -->
    <div class="border-primary-stroke mt-4 border-t pt-3">
      <div class="text-secondary flex items-center justify-between text-xs">
        <span>{{ t('screen.company.item.created') }} {{ formatFullDate(company.created_at) }}</span>
        <span v-if="company.owner_username"
          >{{ t('screen.company.item.by') }} {{ company.owner_username }}</span
        >
      </div>
    </div>
  </div>

  <!-- List/Table View -->
  <div
    v-else
    class="hover:bg-base-200 cursor-pointer px-6 py-4 transition-colors"
    @click="$emit('viewCompany', company.id)"
  >
    <div class="grid grid-cols-12 items-center gap-4">
      <!-- Column 1: Company Name and Website (4 cols) -->
      <div class="col-span-4 flex min-w-0 items-center gap-3">
        <div
          class="ring-primary-stroke flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white ring-1"
        >
          <img
            v-if="getCompanyDomain(company.website)"
            :src="getLogoUrl(company.website)"
            :alt="`${company.name} logo`"
            class="h-full w-full object-contain p-1"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <div
            v-show="showFallbackIcon || !getCompanyDomain(company.website)"
            class="bg-primary/10 dark:bg-primary/20 flex h-full w-full items-center justify-center"
          >
            <i class="fas fa-building text-secondary"></i>
          </div>
        </div>

        <div class="min-w-0 flex-1">
          <h3 class="hover:text-secondary truncate font-medium transition-colors">
            {{ company.name }}
          </h3>
          <p v-if="company.website" class="text-secondary truncate text-sm">
            {{ formatWebsiteDisplay(company.website) }}
          </p>
        </div>
      </div>

      <!-- Column 2: Created Date (2 cols) -->
      <div class="col-span-2">
        <div class="text-secondary text-sm">
          {{ formatFullDate(company.created_at) }}
        </div>
      </div>

      <!-- Column 3: Owner (2 cols) -->
      <div class="col-span-2">
        <div class="text-secondary text-sm">
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
        <div class="flex items-center justify-end gap-1">
          <Button
            variant="tertiary"
            size="sm"
            icon="fa fa-eye"
            :title="$t('screen.cards.actions.view')"
            @click.stop="$emit('viewCompany', company.id)"
          />
          <Button
            v-if="canDeleteCompany"
            variant="tertiary"
            size="sm"
            icon="fa fa-trash"
            :title="$t('screen.cards.actions.delete')"
            @click.stop="$emit('deleteCompany', company)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Tag, { type BadgeVariant } from '@/components/ui/Tag.vue'
import { Button } from '@owlint/feathers-vue'
import type { Company } from '@/types/company'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatFullDate } from '@/utils/time'

const { t } = useI18n()

interface Props {
  company: Company
  mode: 'grid' | 'table'
}

defineProps<Props>()

defineEmits<{
  viewCompany: [id: number | undefined]
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

const getTaskStatusText = (tasks: Array<{ status: string }>) => {
  if (!tasks || tasks.length === 0) return t('screen.company.item.tasks.status.new')

  const running = tasks.filter((t) => t.status === 'running' || t.status === 'pending').length
  const failed = tasks.filter((t) => t.status === 'error' || t.status === 'failed').length
  const succeeded = tasks.filter((t) => t.status === 'succeeded').length

  if (running > 0) return t('screen.company.item.tasks.status.processing')
  if (failed > 0) return t('screen.company.item.tasks.status.issues')
  if (succeeded === tasks.length) return t('screen.company.item.tasks.status.complete')
  return t('screen.company.item.tasks.status.partial')
}

const getTaskStatusVariant = (tasks: Array<{ status: string }>): BadgeVariant => {
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
