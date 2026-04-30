<template>
  <!-- Card View -->
  <div
    v-if="mode === 'grid'"
    class="border-primary-lighter-stroke hover:ring-primary/70 ring-offset-bg3 group cursor-pointer rounded-sm border bg-white p-4 ring-offset-2 transition-all duration-200 hover:ring-4"
    @click="$emit('viewCompany', company.id)"
  >
    <div class="mb-4 flex items-start justify-between">
      <div class="flex items-center gap-3">
        <div
          class="ring-primary-stroke flex h-12 w-12 items-center justify-center overflow-hidden rounded-sm bg-white ring-1"
        >
          <Logo
            :website="company.website"
            :name="company.name"
            :alt="company.name"
            :width="48"
            :height="48"
          />
        </div>
        <div class="min-w-0 flex-1">
          <h3
            class="group-hover:text-neutral-black-font truncate text-lg font-semibold transition-colors"
          >
            {{ company.name }}
          </h3>
          <p v-if="company.website" class="text-neutral-black-font truncate text-sm">
            {{ formatWebsiteDisplay(company.website) }}
          </p>
        </div>
      </div>
    </div>

    <!-- Company Details -->
    <div class="space-y-3">
      <!-- Website Link -->
      <div v-if="company.website" class="flex items-center gap-2">
        <i class="fas fa-globe text-neutral-black-font w-4 text-sm"></i>
        <a
          :href="formatWebsiteUrl(company.website)"
          target="_blank"
          rel="noopener noreferrer"
          class="text-neutral-black-font hover:text-sage-content/80 truncate text-sm transition-colors"
          @click.stop
        >
          {{ company.website }}
          <i class="fas fa-external-link-alt ml-1 text-xs"></i>
        </a>
      </div>

      <!-- Tasks Info -->
      <div v-if="company.tasks && company.tasks.length > 0" class="flex items-center gap-2">
        <i class="fas fa-tasks text-neutral-black-font w-4 text-sm"></i>
        <div class="flex items-center gap-2">
          <span class="text-neutral-black-font text-sm"> {{ company.tasks.length }} tasks </span>
          <Tag :intent="getTaskStatusVariant(company.tasks)" size="xs">
            {{ getTaskStatusText(company.tasks) }}
          </Tag>
        </div>
      </div>
    </div>

    <!-- Footer with creation date and owner -->
    <div class="border-primary-lighter-stroke mt-4 border-t pt-3">
      <div class="text-neutral-black-font flex items-center justify-between text-xs">
        <span
          >{{ t('screen.company.item.created') }}
          {{ formatDate(company.created_at, 'eventDate') }}</span
        >
        <span v-if="company.owner_username"
          >{{ t('screen.company.item.by') }} {{ company.owner_username }}</span
        >
      </div>
    </div>
  </div>

  <!-- List/Table View -->
  <div
    v-else
    class="hover:bg-primary-lightest cursor-pointer px-6 py-4 transition-colors"
    @click="$emit('viewCompany', company.id)"
  >
    <div class="grid grid-cols-12 items-center gap-4">
      <!-- Column 1: Company Name and Website (4 cols) -->
      <div class="col-span-4 flex min-w-0 items-center gap-3">
        <div
          class="ring-primary-stroke flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-sm bg-white ring-1"
        >
          <Logo
            :website="company.website"
            :name="company.name"
            :alt="company.name"
            :width="40"
            :height="40"
          />
        </div>

        <div class="min-w-0 flex-1">
          <h3 class="hover:text-neutral-black-font truncate font-medium transition-colors">
            {{ company.name }}
          </h3>
          <p v-if="company.website" class="text-neutral-black-font truncate text-sm">
            {{ formatWebsiteDisplay(company.website) }}
          </p>
        </div>
      </div>

      <!-- Column 2: Created Date (2 cols) -->
      <div class="col-span-2">
        <div class="text-neutral-black-font text-sm">
          {{ formatDate(company.created_at, 'eventDate') }}
        </div>
      </div>

      <!-- Column 3: Owner (2 cols) -->
      <div class="col-span-2">
        <div class="text-neutral-black-font text-sm">
          {{ company.owner_username || '—' }}
        </div>
      </div>

      <!-- Column 4: Status (2 cols) -->
      <div class="col-span-2">
        <div class="flex items-center gap-2">
          <Tag :intent="getTaskStatusVariant(company.tasks || [])" size="sm">
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
import Logo from '@/components/ui/Logo.vue'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import { useDateTime } from '@/composables/useDateTime'
import type { Company } from '@/types/company'
import { Button, Tag, type Intent } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { formatDate } = useDateTime()

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

const getTaskStatusVariant = (tasks: Array<{ status: string }>): Intent => {
  if (!tasks || tasks.length === 0) return 'accent'

  const running = tasks.filter((t) => t.status === 'running' || t.status === 'pending').length
  const failed = tasks.filter((t) => t.status === 'error' || t.status === 'failed').length
  const succeeded = tasks.filter((t) => t.status === 'succeeded').length

  if (running > 0) return 'warning'
  if (failed > 0) return 'danger'
  if (succeeded === tasks.length) return 'success'
  return 'info'
}
</script>
