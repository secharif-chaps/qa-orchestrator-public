<template>
  <Card class="hover:ring-4 hover:ring-primary/70 ring-offset-2 ring-offset-bg2 transition-all duration-200 cursor-pointer group" @click="$emit('viewCompany', company.id)">
    <div class="flex items-start justify-between mb-4">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-lg bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
          <i class="fas fa-building text-primary text-xl"></i>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="text-lg font-semibold group-hover:text-primary transition-colors truncate">
            {{ company.name }}
          </h3>
          <p v-if="company.website" class="text-sm text-secondary truncate">
            {{ formatWebsiteDisplay(company.website) }}
          </p>
        </div>
      </div>
      
      <!-- Quick Actions -->
      <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
        <OButton
          icon="fas fa-eye"
          type="tertiary"
          :title="$t('cards.actions.view')"
          @click.stop="$emit('viewCompany', company.id)"
        />
        <OButton
          icon="fas fa-trash"
          type="tertiary"
          color="red"
          :title="$t('cards.actions.delete')"
          @click.stop="$emit('deleteCompany', company.id, company.name)"
        />
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
          class="text-sm text-primary hover:text-primary/80 transition-colors truncate"
          @click.stop
        >
          {{ company.website }}
          <i class="fas fa-external-link-alt ml-1 text-xs"></i>
        </a>
      </div>

      <!-- Last Updated -->
      <div class="flex items-center gap-2">
        <i class="fas fa-clock text-secondary text-sm w-4"></i>
        <span class="text-sm text-secondary">
          Updated {{ formatRelativeTime(company.updated_at) }}
        </span>
      </div>

      <!-- Tasks Info -->
      <div v-if="company.tasks && company.tasks.length > 0" class="flex items-center gap-2">
        <i class="fas fa-tasks text-secondary text-sm w-4"></i>
        <div class="flex items-center gap-2">
          <span class="text-sm text-secondary">
            {{ company.tasks.length }} tasks
          </span>
          <OBadge 
            :color="getTaskStatusColor(company.tasks)"
            size="xs"
          >
            {{ getTaskStatusText(company.tasks) }}
          </OBadge>
        </div>
      </div>
    </div>

    <!-- Footer with creation date and owner -->
    <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-700">
      <div class="flex justify-between items-center text-xs text-secondary">
        <span>Created {{ formatDate(company.created_at) }}</span>
        <span v-if="company.owner_username">by {{ company.owner_username }}</span>
      </div>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { OButton, OBadge } from '@owlint/feathers-vue'
import type { Company } from '~/types/company';

interface Props {
  company: Company
}

defineProps<Props>()

defineEmits<{
  viewCompany: [id: string]
  deleteCompany: [company: Company]
}>()

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
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

const formatRelativeTime = (dateString: string) => {
  if (!dateString) return 'unknown'
  
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)
  
  if (diffInSeconds < 60) return 'just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`
  
  return formatDate(dateString)
}

const getTaskStatusText = (tasks: Array<{ status: string }>) => {
  const running = tasks.filter(t => t.status === 'running' || t.status === 'pending').length
  const failed = tasks.filter(t => t.status === 'failed').length
  
  if (running > 0) return `${running} running`
  if (failed > 0) return `${failed} failed`
  return 'completed'
}

const getTaskStatusColor = (tasks: Array<{ status: string }>) => {
  const running = tasks.filter(t => t.status === 'running' || t.status === 'pending').length
  const failed = tasks.filter(t => t.status === 'failed').length
  
  if (running > 0) return 'yellow'
  if (failed > 0) return 'red'
  return 'green'
}
</script>

<style scoped>
.company-card {
  transition: all 0.3s ease;
}

.company-card:hover {
  transform: translateY(-2px);
}
</style>