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
    <div class="mb-4 flex items-start justify-between">
      <div class="flex items-center gap-3">
        <div
          class="ring-primary-stroke flex h-12 w-12 items-center justify-center overflow-hidden rounded-sm bg-white ring-1"
        >
          <img
            v-if="item.type === 'company' && getCompanyDomain(item.website)"
            :src="getLogoUrl(item.website)"
            :alt="`${item.name} logo`"
            class="h-full w-full object-contain p-1"
            @error="showFallbackIcon = true"
            v-show="!showFallbackIcon"
          />
          <div
            v-show="showFallbackIcon || !getCompanyDomain(item.website) || item.type !== 'company'"
            class="bg-primary/10 dark:bg-primary/20 flex h-full w-full items-center justify-center"
          >
            <i class="fas fa-building text-neutral-black-font text-xl"></i>
          </div>
        </div>
        <div class="max-w-32 min-w-0 flex-1">
          <h3 class="truncate text-lg font-semibold transition-colors">
            {{ item.name }}
          </h3>
          <p class="text-neutral-black-font truncate text-sm">
            {{ formatType(item.type) }}
          </p>
        </div>
      </div>

      <!-- Quick Actions -->
      <div
        class="flex gap-1 opacity-100 transition-opacity group-hover:opacity-100"
        @mouseenter="isChildHovered = true"
        @mouseleave="isChildHovered = false"
      >
        <Button
          variant="tertiary"
          icon="fa fa-eye"
          icon-only
          :title="$t('common.folder.item.actions.view')"
          @click.stop="handleClick"
        />
        <Button
          v-if="item.type === 'company' && canMoveCompany"
          variant="tertiary"
          icon="fa fa-exchange-alt"
          icon-only
          :title="$t('common.folder.moveCompany.button')"
          @click.stop="$emit('moveCompany', item)"
        />
        <Button
          v-if="item.type === 'company' && canDeleteCompany"
          variant="tertiary"
          :icon="isArchived ? 'fa fa-undo' : 'fa fa-trash'"
          icon-only
          :title="
            isArchived ? $t('screen.company.restore.title') : $t('screen.company.delete.title')
          "
          @click.stop="$emit('deleteCompany', item)"
        />
      </div>
    </div>

    <div class="text-neutral-black-font flex items-center justify-between text-sm">
      <span>{{ $t('common.folder.item.created') }} {{ formatDate(item.created_at) }}</span>
      <span v-if="item.owner">{{ $t('common.folder.grid.by') }} @{{ item.owner }}</span>
    </div>
  </Card>

  <!-- Table Row (for table mode) -->
  <div v-else class="contents">
    <!-- This is handled by the parent table structure -->
    <slot />
  </div>
</template>

<script setup lang="ts">
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'
import type { FolderItem } from '@/types/folder'
import { formatDate } from '@/utils/time'
import { Button } from '@owlint/feathers-vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '../ui/Card.vue'

interface Props {
  item: FolderItem
  mode: 'grid' | 'table'
  canMoveItems?: boolean
  isArchived?: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  viewItem: [id: string]
  removeItem: [item: FolderItem]
  deleteCompany: [item: FolderItem]
  moveCompany: [item: FolderItem]
}>()

const { t } = useI18n()
const { canDeleteCompany } = useCompanyPermissions()
const showFallbackIcon = ref(false)
const isParentHovered = ref(false)
const isChildHovered = ref(false)

// For move functionality, use the prop passed from parent (based on folder permissions)
const canMoveCompany = computed(() => props.canMoveItems ?? false)

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

function formatType(type: string): string {
  if (type === 'company') {
    return t('common.folder.itemTypes.company')
  }
  return type.charAt(0).toUpperCase() + type.slice(1)
}
</script>
