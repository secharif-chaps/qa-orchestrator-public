<template>
  <div class="bg-bg1 border border-border-2 rounded-xl overflow-hidden">
    <!-- Loading State -->
    <div v-if="loading" class="p-8 text-center">
      <div class="flex items-center justify-center space-x-2">
        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary"></div>
        <span class="text-secondary">{{ $t('company.list.table.loading') }}</span>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-8">
      <OAlert 
        type="error"
        :title="'Error loading companies'"
        :description="error"
      />
    </div>

    <!-- Table Content -->
    <div v-else>
      <OTable 
        :fields="tableFields"
        :items="filteredCompanies"
        :empty-text="$t('cards.noResults')"
      >
        <!-- Custom name column with click handler -->
        <template #cell(name)="{ item }">
          <td class="p-3">
            <button 
              @click="$emit('viewCompany', item.id)"
              class="text-left font-medium text-secondary hover:text-primary transition-colors"
            >
              {{ item.name }}
            </button>
          </td>
        </template>

        <!-- Custom website column -->
        <template #cell(website)="{ item }">
          <td class="p-3">
            <a 
              v-if="item.website" 
              :href="formatWebsiteUrl(item.website)" 
              target="_blank" 
              rel="noopener noreferrer"
              class="text-primary hover:text-primary/80"
              @click.stop
            >
              {{ formatWebsiteDisplay(item.website) }}
              <i class="fas fa-external-link-alt ml-1 text-xs"></i>
            </a>
            <span v-else class="text-secondary">-</span>
          </td>
        </template>

        <!-- Custom owner_username column -->
        <template #cell(owner_username)="{ item }">
          <td class="p-3">
            <span class="text-secondary">{{ item.owner_username || 'N/A' }}</span>
          </td>
        </template>

        <!-- Custom created_at column -->
        <template #cell(created_at)="{ item }">
          <td class="p-3">
            <span class="text-secondary">{{ formatDate(item.created_at) }}</span>
          </td>
        </template>

        <!-- Custom updated_at column -->
        <template #cell(updated_at)="{ item }">
          <td class="p-3">
            <span class="text-secondary">{{ formatDate(item.updated_at) }}</span>
          </td>
        </template>

        <!-- Custom actions column -->
        <template #cell(actions)="{ item }">
          <td class="p-3">
            <div class="flex items-center justify-end space-x-2">
              <OButton
                icon="fas fa-eye"
                type="tertiary"
                size="sm"
                :title="$t('cards.actions.view')"
                @click="$emit('viewCompany', item.id)"
              />
              <OButton
                icon="fas fa-trash"
                type="tertiary"
                color="red"
                size="sm"
                :title="$t('cards.actions.delete')"
                @click="$emit('deleteCompany', item)"
              />
            </div>
          </td>
        </template>
      </OTable>
    </div>
  </div>
</template>

<script setup lang="ts">
import { OButton, OTable, OAlert } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import type { Company } from '~/types/company'

interface Props {
  companies: Company[]
  filteredCompanies: Company[]
  loading: boolean
  error: string | null
}

defineProps<Props>()

defineEmits<{
  viewCompany: [id: string]
  deleteCompany: [company: Company]
}>()

const {t} = useI18n()

// Table configuration
const tableFields = [
  { key: 'name', label: t('cards.table.name') },
  { key: 'website', label: t('company.list.table.website') },
  { key: 'owner_username', label: t('company.list.table.owner') },
  { key: 'created_at', label: t('cards.table.created') },
  { key: 'updated_at', label: t('cards.table.lastModification') },
  { key: 'actions', label: t('cards.table.actions'), sortable: false }
]

// Methods
const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString()
}

const formatWebsiteUrl = (website: string) => {
  if (!website) return '#'
  return website.startsWith('http') ? website : `https://${website}`
}

const formatWebsiteDisplay = (website: string) => {
  if (!website) return ''
  return website.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, '')
}
</script>