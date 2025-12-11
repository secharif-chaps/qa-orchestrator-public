<template>
  <!-- Card View -->
  <Card
    v-if="mode === 'grid'"
    @click="handleClick"
    hoverable
    clickable
    @mouseenter="isParentHovered = true"
    @mouseleave="isParentHovered = false"
  >
    <div class="flex items-start justify-between mb-4">
      <div class="flex items-center gap-3">
        <div
          class="w-12 h-12 rounded-lg bg-white ring-1 ring-primary-stroke overflow-hidden flex items-center justify-center"
        >
          <img
            v-if="item.type === 'company' && getCompanyDomain(item.website)"
            :src="getLogoUrl(item.website)"
            :alt="`${item.name} logo`"
            class="w-full h-full object-contain p-1"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <div
            v-show="showFallbackIcon || !getCompanyDomain(item.website) || item.type !== 'company'"
            class="w-full h-full flex items-center justify-center bg-primary/10 dark:bg-primary/20"
          >
            <i class="fas fa-building text-secondary text-xl"></i>
          </div>
        </div>
        <div class="flex-1 min-w-0 max-w-32">
          <h3 class="text-lg font-semibold transition-colors truncate">
            {{ item.name }}
          </h3>
          <p class="text-sm text-secondary truncate">
            {{ formatType(item.type) }}
          </p>
        </div>
      </div>

      <!-- Quick Actions -->
      <div
        class="flex gap-1 opacity-100 group-hover:opacity-100 transition-opacity"
        @mouseenter="isChildHovered = true"
        @mouseleave="isChildHovered = false"
      >
        <Button
          variant="tertiary"
          icon="fa fa-eye"
          icon-only
          :title="$t('folder.item.actions.view', 'View Item')"
          @click.stop="handleClick"
        />
        <Button
          v-if="item.type === 'company' && canDeleteCompany"
          variant="tertiary"
          icon="fa fa-trash"
          icon-only
          :title="$t('company.delete.title', 'Delete Company')"
          @click.stop="$emit('deleteCompany', item)"
        />
      </div>
    </div>

    <div class="flex items-center justify-between text-sm text-secondary">
      <span>{{ $t('folder.item.created', 'Created') }} {{ formatDate(item.created_at) }}</span>
      <span v-if="item.owner">{{ $t('folder.grid.by') }} @{{ item.owner }}</span>
    </div>
  </Card>

  <!-- Table Row (for table mode) -->
  <div v-else class="contents">
    <!-- This is handled by the parent table structure -->
    <slot />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@owlint/feathers-vue'
import type { FolderItem } from '@/types/folder'
import { useI18n } from 'vue-i18n'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import Card from '../ui/Card.vue'

interface Props {
  item: FolderItem
  mode: 'grid' | 'table'
}

const props = defineProps<Props>()

const emit = defineEmits<{
  viewItem: [id: string]
  removeItem: [item: FolderItem]
  deleteCompany: [item: FolderItem]
}>()

const { t, locale } = useI18n()
const { canDeleteCompany } = useCompanyPermissions()
const showFallbackIcon = ref(false)
const isParentHovered = ref(false)
const isChildHovered = ref(false)

const handleClick = () => {
  if (props.item.type === 'company') {
    emit('viewItem', props.item.id)
  }
}

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

function formatDate(dateString: string): string {
  if (!dateString) return t('common.na')
  const localeCode = locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'
  return new Date(dateString).toLocaleDateString(localeCode)
}

function formatType(type: string): string {
  if (type === 'company') {
    return t('folder.itemTypes.company')
  }
  return type.charAt(0).toUpperCase() + type.slice(1)
}
</script>
